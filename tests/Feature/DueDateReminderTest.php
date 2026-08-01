<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertNotNull($this->channel->fresh()->reminded_at);
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
        $this->assertNotNull($todo->fresh()->reminded_at);
    }

    public function test_does_not_send_duplicate_reminders(): void
    {
        $this->artisan('reminders:send-due')->assertSuccessful();
        $this->artisan('reminders:send-due')->assertSuccessful();

        $this->assertSame(1, Message::where('channel_id', $this->channel->id)->where('is_reminder', true)->count());
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
}
