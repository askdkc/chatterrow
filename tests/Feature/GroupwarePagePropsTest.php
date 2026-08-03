<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GroupwarePagePropsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $outsider;

    private Server $server;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->server = Server::factory()->create(['created_by' => $this->owner->id]);
        $this->server->members()->attach([$this->owner->id, $this->member->id]);
        $this->channel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->owner->id,
        ]);
        Message::factory()->create(['channel_id' => $this->channel->id, 'user_id' => $this->owner->id]);
        Todo::factory()->create(['channel_id' => $this->channel->id, 'created_by' => $this->owner->id]);
    }

    public function test_server_index_has_shared_auth_servers(): void
    {
        $this->actingAs($this->member)
            ->get('/servers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Index')
                ->has('servers')
                ->has('auth.servers', 1)
                ->where('auth.user.id', $this->member->id));
    }

    public function test_server_show_has_server_channels_members_and_shared_rail(): void
    {
        $this->actingAs($this->member)
            ->get(route('servers.show', $this->server))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Show')
                ->where('server.id', $this->server->id)
                ->has('server.channels', 1)
                ->has('members', 2)
                ->has('auth.servers', 1));
    }

    public function test_chat_page_has_server_channel_members_messages_and_shared_rail(): void
    {
        $rootMessage = Message::query()
            ->where('channel_id', $this->channel->id)
            ->whereNull('parent_id')
            ->firstOrFail();
        Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
            'parent_id' => $rootMessage->id,
        ]);
        $this->server->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ]);
        $this->channel->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-07',
        ]);

        $this->actingAs($this->member)
            ->get(route('servers.channels.show', [$this->server, $this->channel]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('chat/Chat')
                ->where('server.id', $this->server->id)
                ->where('server.starts_on', '2026-08-01')
                ->where('server.ends_on', '2026-08-31')
                ->where('server.channels.0.starts_on', '2026-08-01')
                ->where('server.channels.0.ends_on', '2026-08-07')
                ->where('channel.id', $this->channel->id)
                ->where('channel.starts_on', '2026-08-01')
                ->where('channel.ends_on', '2026-08-07')
                ->has('members', 2)
                ->has('initialMessages', 1)
                ->where('initialMessages.0.reply_count', 1)
                ->has('auth.servers', 1));
    }

    public function test_tasks_page_has_server_channels_members_todos_and_shared_rail(): void
    {
        $this->actingAs($this->member)
            ->get(route('servers.tasks.index', $this->server))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Tasks')
                ->where('server.id', $this->server->id)
                ->has('channels', 1)
                ->has('members', 2)
                ->has('todos', 1)
                ->has('auth.servers', 1));
    }

    public function test_gantt_page_has_server_channels_members_tasks_and_shared_rail(): void
    {
        $this->actingAs($this->member)
            ->get(route('servers.gantt', $this->server))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Gantt')
                ->where('server.id', $this->server->id)
                ->has('channels', 1)
                ->has('members', 2)
                ->has('tasks')
                ->has('auth.servers', 1));
    }

    public function test_gantt_todo_payload_preserves_absolute_timestamps_for_local_calendar_projection(): void
    {
        $todo = Todo::query()->where('channel_id', $this->channel->id)->firstOrFail();
        $todo->update([
            'starts_at' => '2026-08-02T15:30:00Z',
            'due_at' => '2026-08-02T15:30:00Z',
        ]);

        $this->actingAs($this->member)
            ->get(route('servers.gantt', $this->server))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.1.id', "todo-{$todo->id}")
                ->where('tasks.1.start', '2026-08-02T15:30:00.000000Z')
                ->where('tasks.1.end', '2026-08-02T15:30:00.000000Z'));
    }

    public function test_files_page_has_server_channels_members_files_and_shared_rail(): void
    {
        $this->actingAs($this->member)
            ->get(route('servers.files.index', $this->server))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Files')
                ->where('server.id', $this->server->id)
                ->has('channels', 1)
                ->has('members', 2)
                ->has('files')
                ->has('auth.servers', 1));
    }

    public function test_channel_files_page_includes_active_channel(): void
    {
        $this->actingAs($this->member)
            ->get(route('servers.channels.files.index', [$this->server, $this->channel]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Files')
                ->where('server.id', $this->server->id)
                ->where('channel.id', $this->channel->id)
                ->has('auth.servers', 1));
    }

    public function test_channel_gantt_route_renders_the_gantt_page(): void
    {
        $this->actingAs($this->member)
            ->get(route('servers.channels.gantt', [$this->server, $this->channel]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Gantt')
                ->where('server.id', $this->server->id)
                ->has('channels', 1)
                ->has('members', 2)
                ->has('tasks')
                ->where('tasks.0.channel_id', $this->channel->id)
                ->has('auth.servers', 1));
    }

    public function test_outsider_gets_403_before_props_are_resolved(): void
    {
        $this->actingAs($this->outsider)
            ->get(route('servers.show', $this->server))
            ->assertForbidden();

        $this->actingAs($this->outsider)
            ->get(route('servers.channels.show', [$this->server, $this->channel]))
            ->assertForbidden();

        $this->actingAs($this->outsider)
            ->get(route('servers.tasks.index', $this->server))
            ->assertForbidden();

        $this->actingAs($this->outsider)
            ->get(route('servers.gantt', $this->server))
            ->assertForbidden();

        $this->actingAs($this->outsider)
            ->get(route('servers.files.index', $this->server))
            ->assertForbidden();
    }
}
