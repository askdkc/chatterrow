<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\Todo;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DueDateReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Server $server;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->server = Server::factory()->create(['created_by' => $this->owner->id]);
        $this->server->members()->attach($this->owner->id);
        $this->channel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->owner->id,
            'ends_on' => now()->today(),
        ]);
    }

    public function test_reminds_channels_due_today_and_marks_reminded(): void
    {
        $this->artisan('reminders:send-due')->assertSuccessful();

        $this->assertDatabaseHas('messages', [
            'channel_id' => $this->channel->id,
            'is_reminder' => true,
        ]);
        $this->assertInstanceOf(CarbonInterface::class, $this->channel->fresh()->reminded_at);
    }

    public function test_broadcast_failure_does_not_stop_later_due_channels(): void
    {
        $secondChannel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->owner->id,
            'ends_on' => now()->today(),
        ]);

        $this->app->instance(BroadcastFactory::class, new class implements BroadcastFactory
        {
            public function connection($name = null)
            {
                throw new \RuntimeException('broadcast connection failed');
            }

            public function event($event = null)
            {
                throw new \RuntimeException('broadcast event failed');
            }
        });

        $this->artisan('reminders:send-due')
            ->expectsOutput('Sent 2 due-date reminder(s).')
            ->assertSuccessful();

        $this->assertSame(2, Message::where('is_reminder', true)->count());
        $this->assertNotNull($this->channel->fresh()->reminded_at);
        $this->assertNotNull($secondChannel->fresh()->reminded_at);
    }

    public function test_reminds_todos_due_on_the_target_day_in_their_saved_timezone(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00Z');

        try {
            $this->channel->update(['ends_on' => null]);
            $tokyoChannel = Channel::factory()->create([
                'server_id' => $this->server->id,
                'created_by' => $this->owner->id,
                'ends_on' => null,
            ]);
            $losAngelesChannel = Channel::factory()->create([
                'server_id' => $this->server->id,
                'created_by' => $this->owner->id,
                'ends_on' => null,
            ]);

            Todo::factory()->create([
                'channel_id' => $tokyoChannel->id,
                'assignee_id' => $this->owner->id,
                'starts_at' => null,
                'due_at' => '2026-08-01 15:30:00',
                'due_timezone' => 'Asia/Tokyo',
            ]);
            Todo::factory()->create([
                'channel_id' => $losAngelesChannel->id,
                'assignee_id' => $this->owner->id,
                'starts_at' => null,
                'due_at' => '2026-08-03 06:30:00',
                'due_timezone' => 'America/Los_Angeles',
            ]);

            $this->artisan('reminders:send-due')
                ->expectsOutput('Sent 2 due-date reminder(s).')
                ->assertSuccessful();

            $this->assertSame(2, Message::where('is_reminder', true)->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_todo_target_day_uses_each_saved_timezone_at_utc_date_boundaries(): void
    {
        Carbon::setTestNow('2026-08-03 01:00:00Z');

        try {
            $this->channel->update(['ends_on' => null]);
            $tokyoChannel = Channel::factory()->create([
                'server_id' => $this->server->id,
                'created_by' => $this->owner->id,
                'ends_on' => null,
            ]);
            $losAngelesTodayChannel = Channel::factory()->create([
                'server_id' => $this->server->id,
                'created_by' => $this->owner->id,
                'ends_on' => null,
            ]);
            $losAngelesTomorrowChannel = Channel::factory()->create([
                'server_id' => $this->server->id,
                'created_by' => $this->owner->id,
                'ends_on' => null,
            ]);

            $tokyoToday = Todo::factory()->create([
                'channel_id' => $tokyoChannel->id,
                'assignee_id' => $this->owner->id,
                'starts_at' => null,
                'due_at' => '2026-08-02 15:30:00',
                'due_timezone' => 'Asia/Tokyo',
            ]);
            $losAngelesToday = Todo::factory()->create([
                'channel_id' => $losAngelesTodayChannel->id,
                'assignee_id' => $this->owner->id,
                'starts_at' => null,
                'due_at' => '2026-08-02 08:30:00',
                'due_timezone' => 'America/Los_Angeles',
            ]);
            $losAngelesTomorrow = Todo::factory()->create([
                'channel_id' => $losAngelesTomorrowChannel->id,
                'assignee_id' => $this->owner->id,
                'starts_at' => null,
                'due_at' => '2026-08-03 07:30:00',
                'due_timezone' => 'America/Los_Angeles',
            ]);

            $this->artisan('reminders:send-due')
                ->expectsOutput('Sent 2 due-date reminder(s).')
                ->assertSuccessful();

            $tokyoMessage = Message::where('channel_id', $tokyoChannel->id)->where('is_reminder', true)->firstOrFail();
            $losAngelesTodayMessage = Message::where('channel_id', $losAngelesTodayChannel->id)->where('is_reminder', true)->firstOrFail();

            $this->assertStringContainsString('2026-08-03', $tokyoMessage->body);
            $this->assertStringContainsString('2026-08-02', $losAngelesTodayMessage->body);
            $this->assertNotNull($tokyoToday->fresh()->reminded_at);
            $this->assertNotNull($losAngelesToday->fresh()->reminded_at);
            $this->assertNull($losAngelesTomorrow->fresh()->reminded_at);

            $this->artisan('reminders:send-due', ['--days-ahead' => 1])
                ->expectsOutput('Sent 1 due-date reminder(s).')
                ->assertSuccessful();

            $this->assertNotNull($losAngelesTomorrow->fresh()->reminded_at);
            $this->assertStringContainsString(
                '2026-08-03',
                Message::where('channel_id', $losAngelesTomorrowChannel->id)->where('is_reminder', true)->firstOrFail()->body,
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reminds_todos_due_today(): void
    {
        $channel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->owner->id,
            'ends_on' => null,
        ]);
        $todo = Todo::factory()->create([
            'channel_id' => $channel->id,
            'assignee_id' => $this->owner->id,
            'due_at' => now()->today()->setHour(17),
        ]);

        $this->artisan('reminders:send-due')->assertSuccessful();

        $this->assertDatabaseHas('messages', [
            'channel_id' => $channel->id,
            'is_reminder' => true,
        ]);
        $this->assertStringContainsString($todo->title, Message::where('channel_id', $channel->id)->where('is_reminder', true)->first()->body);
        $this->assertInstanceOf(CarbonInterface::class, $todo->fresh()->reminded_at);
    }

    public function test_does_not_send_duplicate_reminders(): void
    {
        $this->artisan('reminders:send-due')->assertSuccessful();
        $this->artisan('reminders:send-due')->assertSuccessful();

        $this->assertSame(1, Message::where('channel_id', $this->channel->id)->where('is_reminder', true)->count());
    }

    public function test_clearing_reminded_marker_does_not_duplicate_the_same_deadline_reminder(): void
    {
        $this->artisan('reminders:send-due')
            ->expectsOutput('Sent 1 due-date reminder(s).')
            ->assertSuccessful();
        $this->channel->update(['reminded_at' => null]);

        $this->artisan('reminders:send-due')
            ->expectsOutput('Sent 0 due-date reminder(s).')
            ->assertSuccessful();

        $this->assertSame(1, Message::where('channel_id', $this->channel->id)->where('is_reminder', true)->count());
    }

    public function test_new_deadline_creates_a_distinct_reminder_key(): void
    {
        $this->artisan('reminders:send-due')->assertSuccessful();
        $first = Message::where('channel_id', $this->channel->id)->where('is_reminder', true)->firstOrFail();

        $this->channel->update([
            'ends_on' => now()->addDay()->toDateString(),
            'reminded_at' => null,
        ]);

        $this->artisan('reminders:send-due', ['--days-ahead' => 1])->assertSuccessful();
        $reminders = Message::where('channel_id', $this->channel->id)->where('is_reminder', true)->orderBy('id')->get();

        $this->assertCount(2, $reminders);
        $this->assertNotNull($first->reminder_key);
        $this->assertNotNull($reminders->last()->reminder_key);
        $this->assertNotSame($first->reminder_key, $reminders->last()->reminder_key);
    }

    public function test_todo_reminder_key_uses_the_exact_due_timestamp(): void
    {
        $channel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->owner->id,
            'ends_on' => null,
        ]);
        $dueAt = now()->today()->setHour(17)->setMinute(30)->setSecond(0);
        $todo = Todo::factory()->create([
            'channel_id' => $channel->id,
            'assignee_id' => $this->owner->id,
            'starts_at' => null,
            'due_at' => $dueAt,
        ]);

        $this->artisan('reminders:send-due')->assertSuccessful();
        $first = Message::where('channel_id', $channel->id)->where('is_reminder', true)->firstOrFail();
        $storedTodo = $todo->fresh();
        $storedDueAt = $storedTodo->due_at;

        $this->assertSame(
            'todo:'.$todo->id.':'.$storedTodo->due_timezone.':'.$storedDueAt->utc()->format('Y-m-d\\TH:i:s.u\\Z'),
            $first->reminder_key,
        );

        $todo->update([
            'due_at' => $dueAt->copy()->addHour(),
            'reminded_at' => null,
        ]);

        $this->artisan('reminders:send-due')->assertSuccessful();
        $reminders = Message::where('channel_id', $channel->id)->where('is_reminder', true)->orderBy('id')->get();

        $this->assertCount(2, $reminders);
        $this->assertNotSame($reminders[0]->reminder_key, $reminders[1]->reminder_key);
    }

    public function test_rescheduling_clears_the_previous_reminder_marker(): void
    {
        $this->channel->update([
            'ends_on' => now()->toDateString(),
            'reminded_at' => now(),
        ]);
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'created_by' => $this->owner->id,
            'starts_at' => null,
            'due_at' => now()->endOfDay(),
            'reminded_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'ends_on' => now()->addDay()->toDateString(),
            ])
            ->assertOk();

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'due_at' => now()->addDay()->endOfDay()->toIso8601String(),
            ])
            ->assertOk();

        $this->assertNull($this->channel->fresh()->reminded_at);
        $this->assertNull($todo->fresh()->reminded_at);
    }

    public function test_unrelated_edits_preserve_reminder_markers(): void
    {
        $this->channel->update(['reminded_at' => now()]);
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'created_by' => $this->owner->id,
            'starts_at' => null,
            'due_at' => now()->endOfDay(),
            'reminded_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'description' => '更新された説明',
            ])
            ->assertOk();

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'title' => '更新されたタスク',
            ])
            ->assertOk();

        $this->assertNotNull($this->channel->fresh()->reminded_at);
        $this->assertNotNull($todo->fresh()->reminded_at);
    }

    public function test_same_deadline_saves_preserve_reminder_markers(): void
    {
        $this->channel->update(['reminded_at' => now()]);
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'created_by' => $this->owner->id,
            'starts_at' => null,
            'due_at' => now()->endOfDay(),
            'reminded_at' => now(),
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'ends_on' => $this->channel->ends_on->toDateString(),
            ])
            ->assertOk();

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'due_at' => $todo->due_at->toIso8601String(),
            ])
            ->assertOk();

        $this->assertNotNull($this->channel->fresh()->reminded_at);
        $this->assertNotNull($todo->fresh()->reminded_at);
    }

    public function test_completed_todos_are_not_reminded(): void
    {
        Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'due_at' => now()->today()->setHour(17),
            'completed_at' => now(),
        ]);

        $this->artisan('reminders:send-due')->assertSuccessful();

        $this->assertSame(1, Message::where('channel_id', $this->channel->id)->count());
    }

    public function test_stale_todo_candidate_is_not_claimed_after_timezone_change(): void
    {
        Carbon::setTestNow('2026-08-02 12:00:00Z');

        try {
            $this->channel->update(['ends_on' => null]);
            $todoChannel = Channel::factory()->create([
                'server_id' => $this->server->id,
                'created_by' => $this->owner->id,
                'ends_on' => null,
            ]);
            $todo = Todo::factory()->create([
                'channel_id' => $todoChannel->id,
                'assignee_id' => $this->owner->id,
                'starts_at' => null,
                'due_at' => '2026-08-02 00:30:00',
                'due_timezone' => 'UTC',
            ]);

            $candidateMutated = false;
            $listenerArmed = true;
            DB::listen(function (QueryExecuted $query) use (
                $todo,
                &$candidateMutated,
                &$listenerArmed,
            ): void {
                if (! $listenerArmed
                    || $candidateMutated
                    || ! str_contains($query->sql, 'from "todos"')
                    || ! str_contains($query->sql, '"due_at"')
                    || ! str_contains($query->sql, '"reminded_at"')) {
                    return;
                }

                $candidateMutated = true;
                $todo->update(['due_timezone' => 'America/Los_Angeles']);
            });

            $this->artisan('reminders:send-due')
                ->expectsOutput('Sent 0 due-date reminder(s).')
                ->assertSuccessful();
            $listenerArmed = false;

            $this->assertTrue($candidateMutated);
            $this->assertSame(0, Message::where('is_reminder', true)->count());
            $this->assertNull($todo->fresh()->reminded_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_stale_channel_and_todo_candidates_are_not_claimed_after_rescheduling(): void
    {
        $todoChannel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->owner->id,
            'ends_on' => null,
        ]);
        $todo = Todo::factory()->create([
            'channel_id' => $todoChannel->id,
            'assignee_id' => $this->owner->id,
            'created_by' => $this->owner->id,
            'starts_at' => null,
            'due_at' => now()->today()->setHour(17),
        ]);

        $channelCandidateMutated = false;
        $todoCandidateMutated = false;
        $listenerArmed = true;

        DB::listen(function (QueryExecuted $query) use (
            $todo,
            &$channelCandidateMutated,
            &$todoCandidateMutated,
            &$listenerArmed,
        ): void {
            if (! $listenerArmed) {
                return;
            }

            if (! $channelCandidateMutated
                && str_contains($query->sql, 'from "channels"')
                && str_contains($query->sql, '"ends_on"')
                && str_contains($query->sql, '"reminded_at"')) {
                $channelCandidateMutated = true;
                $this->channel->update(['ends_on' => now()->addDay()->toDateString()]);
            }

            if (! $todoCandidateMutated
                && str_contains($query->sql, 'from "todos"')
                && str_contains($query->sql, '"due_at"')
                && str_contains($query->sql, '"reminded_at"')) {
                $todoCandidateMutated = true;
                $todo->update(['due_at' => now()->addDay()->setHour(17)]);
            }
        });

        $this->artisan('reminders:send-due')
            ->expectsOutput('Sent 0 due-date reminder(s).')
            ->assertSuccessful();
        $listenerArmed = false;

        $this->assertTrue($channelCandidateMutated);
        $this->assertTrue($todoCandidateMutated);
        $this->assertSame(0, Message::where('is_reminder', true)->count());
        $this->assertNull($this->channel->fresh()->reminded_at);
        $this->assertNull($todo->fresh()->reminded_at);
    }
}
