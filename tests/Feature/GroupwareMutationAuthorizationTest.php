<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class GroupwareMutationAuthorizationTest extends TestCase
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
    }

    public function test_member_can_create_a_channel_as_json_and_receives_201_with_channel_resource(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.channels.store', $this->server), [
                'name' => 'new-channel',
            ])
            ->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('channel', fn (AssertableJson $c) => $c
                    ->whereType('id', 'integer')
                    ->where('name', 'new-channel')
                    ->etc())
                ->etc());

        $this->assertDatabaseHas('channels', [
            'server_id' => $this->server->id,
            'name' => 'new-channel',
            'created_by' => $this->member->id,
        ]);
    }

    public function test_owner_can_update_project_details_as_json(): void
    {
        $this->actingAs($this->owner)
            ->patchJson(route('servers.update', $this->server), [
                'name' => 'Project Alpha',
                'description' => 'Project content',
                'starts_on' => '2026-08-01',
                'ends_on' => '2026-08-31',
            ])
            ->assertOk()
            ->assertJsonPath('server.name', 'Project Alpha')
            ->assertJsonPath('server.description', 'Project content')
            ->assertJsonPath('server.starts_on', '2026-08-01')
            ->assertJsonPath('server.ends_on', '2026-08-31');

        $this->assertDatabaseHas('servers', [
            'id' => $this->server->id,
            'name' => 'Project Alpha',
            'description' => 'Project content',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-31',
        ]);
    }

    public function test_member_can_create_a_message_in_a_channel(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.channels.messages.store', [$this->server, $this->channel]), [
                'body' => 'hello from member',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
            'body' => 'hello from member',
        ]);
    }

    public function test_member_can_create_a_todo_in_a_channel(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.channels.todos.store', [$this->server, $this->channel]), [
                'title' => 'ship it',
                'starts_at' => '2026-08-02T09:00',
                'due_at' => '2026-08-02T17:30',
                'priority' => 'high',
                'details' => 'release memo',
            ])
            ->assertCreated()
            ->assertJsonPath('todo.title', 'ship it')
            ->assertJsonPath('todo.priority', 'high')
            ->assertJsonPath('todo.details', 'release memo');

        $this->assertDatabaseHas('todos', [
            'channel_id' => $this->channel->id,
            'created_by' => $this->member->id,
            'title' => 'ship it',
            'starts_at' => '2026-08-02 09:00:00',
            'due_at' => '2026-08-02 17:30:00',
            'priority' => 'high',
            'details' => 'release memo',
        ]);
    }

    public function test_todo_due_datetime_cannot_be_before_its_start_datetime(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.channels.todos.store', [$this->server, $this->channel]), [
                'title' => 'invalid schedule',
                'starts_at' => '2026-08-02T17:30',
                'due_at' => '2026-08-02T09:00',
                'priority' => 'normal',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_at');
    }

    public function test_outsider_cannot_create_channel_message_or_todo(): void
    {
        $this->actingAs($this->outsider)
            ->postJson(route('servers.channels.store', $this->server), ['name' => 'x'])
            ->assertForbidden();

        $this->actingAs($this->outsider)
            ->postJson(route('servers.channels.messages.store', [$this->server, $this->channel]), ['body' => 'x'])
            ->assertForbidden();

        $this->actingAs($this->outsider)
            ->postJson(route('servers.channels.todos.store', [$this->server, $this->channel]), ['title' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseCount('channels', 1);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('todos', 0);
    }

    public function test_channel_from_another_server_cannot_be_used_under_this_server_route(): void
    {
        $otherServer = Server::factory()->create(['created_by' => $this->owner->id]);
        $otherServer->members()->attach($this->owner->id);
        $otherChannel = Channel::factory()->create([
            'server_id' => $otherServer->id,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->member)
            ->postJson(route('servers.channels.messages.store', [$this->server, $otherChannel]), ['body' => 'x'])
            ->assertNotFound();
    }

    public function test_validation_failures_return_422_json_when_requested_as_json(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.channels.store', $this->server), ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_member_cannot_create_a_duplicate_channel_name_in_the_same_server(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.channels.store', $this->server), ['name' => $this->channel->name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_message_create_is_scoped_to_its_channel_and_rejects_invalid_parent(): void
    {
        $otherServer = Server::factory()->create(['created_by' => $this->owner->id]);
        $otherServer->members()->attach($this->owner->id);
        $otherChannel = Channel::factory()->create([
            'server_id' => $otherServer->id,
            'created_by' => $this->owner->id,
        ]);
        $foreignParent = Message::factory()->create([
            'channel_id' => $otherChannel->id,
            'user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->member)
            ->postJson(route('servers.channels.messages.store', [$this->server, $this->channel]), [
                'body' => 'reply',
                'parent_id' => $foreignParent->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_message_index_separates_root_messages_and_thread_replies(): void
    {
        $root = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->owner->id,
        ]);
        $reply = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
            'parent_id' => $root->id,
        ]);

        $this->actingAs($this->member)
            ->getJson(route('servers.channels.messages.index', [$this->server, $this->channel]))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $root->id)
            ->assertJsonPath('messages.0.reply_count', 1);

        $this->actingAs($this->member)
            ->getJson(route('servers.channels.messages.index', [$this->server, $this->channel]).'?parent_id='.$root->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $reply->id)
            ->assertJsonPath('messages.0.parent_id', $root->id);
    }

    public function test_message_attachments_include_preview_urls(): void
    {
        $message = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
        ]);
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->member->id,
            'attachable_type' => Message::class,
            'attachable_id' => $message->id,
            'original_name' => 'report.pdf',
        ]);
        $file->forceFill([
            'preview_status' => 'ready',
            'preview_path' => 'previews/report.webp',
        ])->save();

        $this->actingAs($this->member)
            ->getJson(route('servers.channels.messages.index', [$this->server, $this->channel]))
            ->assertOk()
            ->assertJsonPath('messages.0.attachments.0.stream_url', route('servers.files.stream', [$this->server, $file]))
            ->assertJsonPath('messages.0.attachments.0.download_url', route('servers.files.download', [$this->server, $file]))
            ->assertJsonPath('messages.0.attachments.0.thumbnail_url', route('servers.files.thumbnail', [$this->server, $file]));
    }

    public function test_replies_cannot_create_nested_threads(): void
    {
        $root = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->owner->id,
        ]);
        $reply = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
            'parent_id' => $root->id,
        ]);

        $this->actingAs($this->member)
            ->postJson(route('servers.channels.messages.store', [$this->server, $this->channel]), [
                'body' => 'nested reply',
                'parent_id' => $reply->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_message_author_can_edit_and_server_owner_can_delete_messages(): void
    {
        $message = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
            'body' => 'before',
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.messages.update', [$this->server, $this->channel, $message]), [
                'body' => 'after',
            ])
            ->assertOk()
            ->assertJsonPath('message.body', 'after');

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.messages.update', [$this->server, $this->channel, $message]), [
                'body' => 'owner edit',
            ])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->deleteJson(route('servers.channels.messages.destroy', [$this->server, $this->channel, $message]))
            ->assertNoContent();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }
}
