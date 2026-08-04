<?php

namespace Tests\Feature;

use App\Events\MessageCreated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use RuntimeException;
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
        $this->server->members()->attach([
            $this->owner->id => ['role' => Server::ROLE_ADMIN],
            $this->member->id => ['role' => Server::ROLE_MEMBER],
        ]);
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

    public function test_owner_can_invite_a_registered_user_without_immediate_membership(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('servers.invitations.store', $this->server), [
                'email' => $this->outsider->email,
            ])
            ->assertCreated()
            ->assertJsonPath('invitation.user.id', $this->outsider->id)
            ->assertJsonPath('invitation.status', 'pending')
            ->assertJsonPath('delivery', 'in_app');

        $this->assertDatabaseHas('server_invitations', [
            'server_id' => $this->server->id,
            'user_id' => $this->outsider->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->outsider->id,
        ]);
    }

    public function test_non_owner_cannot_add_or_remove_project_members(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.invitations.store', $this->server), [
                'email' => $this->outsider->email,
            ])
            ->assertForbidden();

        $this->actingAs($this->member)
            ->deleteJson(route('servers.members.destroy', [$this->server, $this->owner]))
            ->assertForbidden();

        $this->assertDatabaseMissing('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->outsider->id,
        ]);
    }

    public function test_owner_can_promote_a_member_to_project_administrator(): void
    {
        $this->actingAs($this->owner)
            ->patchJson(route('servers.members.role.update', [
                $this->server,
                $this->member,
            ]), [
                'role' => Server::ROLE_ADMIN,
            ])
            ->assertOk()
            ->assertJsonPath('user.id', $this->member->id)
            ->assertJsonPath('user.pivot.role', Server::ROLE_ADMIN);

        $this->assertDatabaseHas('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->member->id,
            'role' => Server::ROLE_ADMIN,
        ]);
    }

    public function test_regular_member_cannot_change_project_roles(): void
    {
        $this->actingAs($this->member)
            ->patchJson(route('servers.members.role.update', [
                $this->server,
                $this->member,
            ]), [
                'role' => Server::ROLE_ADMIN,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->member->id,
            'role' => Server::ROLE_MEMBER,
        ]);
    }

    public function test_project_creator_cannot_be_demoted_or_removed(): void
    {
        $this->actingAs($this->owner)
            ->patchJson(route('servers.members.role.update', [
                $this->server,
                $this->owner,
            ]), [
                'role' => Server::ROLE_MEMBER,
            ])
            ->assertUnprocessable();

        $this->actingAs($this->owner)
            ->deleteJson(route('servers.members.destroy', [
                $this->server,
                $this->owner,
            ]))
            ->assertUnprocessable();

        $this->assertDatabaseHas('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->owner->id,
            'role' => Server::ROLE_ADMIN,
        ]);
    }

    public function test_additional_administrator_has_full_project_management_permissions(): void
    {
        $this->server->members()->updateExistingPivot($this->member->id, [
            'role' => Server::ROLE_ADMIN,
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.update', $this->server), [
                'name' => 'Managed by second admin',
            ])
            ->assertOk()
            ->assertJsonPath('server.name', 'Managed by second admin');

        $this->actingAs($this->member)
            ->postJson(route('servers.invitations.store', $this->server), [
                'email' => $this->outsider->email,
            ])
            ->assertCreated();

        $this->actingAs($this->member)
            ->patchJson(route('servers.archive', $this->server))
            ->assertOk();

        $this->actingAs($this->member)
            ->patchJson(route('servers.restore', $this->server))
            ->assertOk();

        $this->actingAs($this->member)
            ->deleteJson(route('servers.destroy', $this->server))
            ->assertOk();

        $this->assertDatabaseMissing('servers', ['id' => $this->server->id]);
    }

    public function test_owner_cannot_remove_the_project_owner_membership(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson(route('servers.members.destroy', [$this->server, $this->owner]))
            ->assertUnprocessable();

        $this->assertDatabaseHas('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_owner_can_archive_and_restore_a_project_while_members_cannot(): void
    {
        $this->actingAs($this->member)
            ->patchJson(route('servers.archive', $this->server))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->patchJson(route('servers.archive', $this->server))
            ->assertOk()
            ->assertJsonPath('server.id', $this->server->id);

        $this->assertNotNull($this->server->fresh()->archived_at);

        $this->actingAs($this->member)
            ->postJson(route('servers.channels.store', $this->server), [
                'name' => 'archived-project-channel',
            ])
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->patchJson(route('servers.update', $this->server), [
                'name' => 'Cannot update while archived',
            ])
            ->assertForbidden();

        $this->actingAs($this->member)
            ->patchJson(route('servers.restore', $this->server))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->patchJson(route('servers.restore', $this->server))
            ->assertOk();

        $this->assertNull($this->server->fresh()->archived_at);
    }

    public function test_owner_can_permanently_delete_a_project_as_json(): void
    {
        Storage::fake('local');
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'path' => "uploads/{$this->server->id}/project-data.bin",
            'original_name' => 'project-data.bin',
        ]);
        Storage::disk('local')->put($file->path, 'project data');

        $this->actingAs($this->member)
            ->deleteJson(route('servers.destroy', $this->server))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->deleteJson(route('servers.destroy', $this->server))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('servers', ['id' => $this->server->id]);
        $this->assertDatabaseMissing('channels', ['id' => $this->channel->id]);
        $this->assertDatabaseMissing('stored_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_owner_cannot_remove_a_member_assigned_to_a_todo_in_the_server(): void
    {
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'assignee_id' => $this->member->id,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson(route('servers.members.destroy', [$this->server, $this->member]))
            ->assertConflict();

        $this->assertDatabaseHas('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->member->id,
        ]);
        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'assignee_id' => $this->member->id,
        ]);
    }

    public function test_owner_can_remove_an_unassigned_non_owner_member(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson(route('servers.members.destroy', [$this->server, $this->member]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->member->id,
        ]);
    }

    public function test_channel_start_only_update_rejects_an_effective_invalid_date_range(): void
    {
        $this->channel->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'starts_on' => '2026-08-10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->assertDatabaseHas('channels', [
            'id' => $this->channel->id,
            'name' => $this->channel->name,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_channel_end_only_update_rejects_an_effective_invalid_date_range(): void
    {
        $this->channel->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'ends_on' => '2026-07-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->assertDatabaseHas('channels', [
            'id' => $this->channel->id,
            'name' => $this->channel->name,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_channel_create_rejects_an_offset_start_date_without_persisting(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('servers.channels.store', $this->server), [
                'name' => 'offset-start-channel',
                'starts_on' => '2026-08-02T23:00:00-10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_on');

        $this->assertDatabaseMissing('channels', [
            'server_id' => $this->server->id,
            'name' => 'offset-start-channel',
        ]);
    }

    public function test_channel_create_rejects_an_offset_end_date_without_persisting(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('servers.channels.store', $this->server), [
                'name' => 'offset-end-channel',
                'ends_on' => '2026-08-02T23:00:00-10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->assertDatabaseMissing('channels', [
            'server_id' => $this->server->id,
            'name' => 'offset-end-channel',
        ]);
    }

    public function test_channel_update_rejects_an_offset_start_date_without_changing_the_stored_range(): void
    {
        $this->channel->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'starts_on' => '2026-08-02T23:00:00-10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_on');

        $this->assertDatabaseHas('channels', [
            'id' => $this->channel->id,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_channel_update_rejects_an_offset_end_date_without_changing_the_stored_range(): void
    {
        $this->channel->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'ends_on' => '2026-08-02T23:00:00-10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->assertDatabaseHas('channels', [
            'id' => $this->channel->id,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_channel_update_preserves_explicit_null_clearing_and_equal_dates(): void
    {
        $this->channel->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'starts_on' => null,
                'ends_on' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('channels', [
            'id' => $this->channel->id,
            'starts_on' => null,
            'ends_on' => null,
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.channels.update', [$this->server, $this->channel]), [
                'name' => $this->channel->name,
                'starts_on' => '2026-08-05',
                'ends_on' => '2026-08-05',
            ])
            ->assertOk();

        $this->assertDatabaseHas('channels', [
            'id' => $this->channel->id,
            'starts_on' => '2026-08-05',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_server_start_only_update_rejects_an_effective_invalid_date_range(): void
    {
        $this->server->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.update', $this->server), [
                'name' => $this->server->name,
                'starts_on' => '2026-08-10',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->assertDatabaseHas('servers', [
            'id' => $this->server->id,
            'name' => $this->server->name,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_server_end_only_update_rejects_an_effective_invalid_date_range(): void
    {
        $this->server->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.update', $this->server), [
                'name' => $this->server->name,
                'ends_on' => '2026-07-31',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->assertDatabaseHas('servers', [
            'id' => $this->server->id,
            'name' => $this->server->name,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_server_create_rejects_an_offset_start_date_without_persisting(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('servers.store'), [
                'name' => 'offset-start-server',
                'starts_on' => '2026-08-02T23:00:00-10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_on');

        $this->assertDatabaseMissing('servers', [
            'name' => 'offset-start-server',
        ]);
    }

    public function test_server_creator_is_attached_as_an_administrator(): void
    {
        $serverId = $this->actingAs($this->owner)
            ->postJson(route('servers.store'), [
                'name' => 'New managed project',
            ])
            ->assertCreated()
            ->json('server.id');

        $this->assertDatabaseHas('server_user', [
            'server_id' => $serverId,
            'user_id' => $this->owner->id,
            'role' => Server::ROLE_ADMIN,
        ]);
    }

    public function test_server_create_rejects_an_offset_end_date_without_persisting(): void
    {
        $this->actingAs($this->owner)
            ->postJson(route('servers.store'), [
                'name' => 'offset-end-server',
                'ends_on' => '2026-08-02T23:00:00-10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->assertDatabaseMissing('servers', [
            'name' => 'offset-end-server',
        ]);
    }

    public function test_server_update_rejects_an_offset_start_date_without_changing_the_stored_range(): void
    {
        $this->server->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.update', $this->server), [
                'name' => $this->server->name,
                'starts_on' => '2026-08-02T23:00:00-10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_on');

        $this->assertDatabaseHas('servers', [
            'id' => $this->server->id,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_server_update_rejects_an_offset_end_date_without_changing_the_stored_range(): void
    {
        $this->server->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.update', $this->server), [
                'name' => $this->server->name,
                'ends_on' => '2026-08-02T23:00:00-10:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_on');

        $this->assertDatabaseHas('servers', [
            'id' => $this->server->id,
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);
    }

    public function test_server_update_preserves_explicit_null_clearing_and_equal_dates(): void
    {
        $this->server->update([
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-08-05',
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.update', $this->server), [
                'name' => $this->server->name,
                'starts_on' => null,
                'ends_on' => null,
            ])
            ->assertOk();

        $this->assertDatabaseHas('servers', [
            'id' => $this->server->id,
            'starts_on' => null,
            'ends_on' => null,
        ]);

        $this->actingAs($this->owner)
            ->patchJson(route('servers.update', $this->server), [
                'name' => $this->server->name,
                'starts_on' => '2026-08-05',
                'ends_on' => '2026-08-05',
            ])
            ->assertOk();

        $this->assertDatabaseHas('servers', [
            'id' => $this->server->id,
            'starts_on' => '2026-08-05',
            'ends_on' => '2026-08-05',
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

    public function test_message_creation_succeeds_when_realtime_broadcast_dispatch_fails(): void
    {
        Event::listen(MessageCreated::class, static function (): never {
            throw new RuntimeException('Realtime broadcast dispatch failed.');
        });

        $this->actingAs($this->member)
            ->postJson(route('servers.channels.messages.store', [$this->server, $this->channel]), [
                'body' => 'persist despite realtime failure',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('messages', [
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
            'body' => 'persist despite realtime failure',
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

    public function test_todo_creation_defaults_due_timezone_to_the_app_timezone(): void
    {
        config(['app.timezone' => 'Asia/Tokyo']);

        $this->actingAs($this->member)
            ->postJson(route('servers.channels.todos.store', [$this->server, $this->channel]), [
                'title' => 'Tokyo task',
                'due_at' => '2026-08-03T00:30:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('todo.due_timezone', 'Asia/Tokyo');

        $this->assertDatabaseHas('todos', [
            'channel_id' => $this->channel->id,
            'title' => 'Tokyo task',
            'due_timezone' => 'Asia/Tokyo',
        ]);
    }

    public function test_todo_due_timezone_must_be_valid_on_create_and_update(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.channels.todos.store', [$this->server, $this->channel]), [
                'title' => 'invalid timezone',
                'due_timezone' => 'Mars/Olympus',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_timezone');

        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'due_timezone' => 'Asia/Tokyo',
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'due_timezone' => 'Mars/Olympus',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_timezone');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'due_timezone' => 'Asia/Tokyo',
        ]);
    }

    public function test_todo_timezone_is_preserved_when_omitted_and_clears_marker_when_changed(): void
    {
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'due_timezone' => 'Asia/Tokyo',
            'reminded_at' => now(),
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'title' => 'title only',
            ])
            ->assertOk()
            ->assertJsonPath('todo.due_timezone', 'Asia/Tokyo');

        $this->assertNotNull($todo->fresh()->reminded_at);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'due_timezone' => 'America/Los_Angeles',
            ])
            ->assertOk()
            ->assertJsonPath('todo.due_timezone', 'America/Los_Angeles');

        $this->assertNull($todo->fresh()->reminded_at);
    }

    public function test_todo_assignee_must_belong_to_the_server(): void
    {
        $this->actingAs($this->member)
            ->postJson(route('servers.channels.todos.store', [$this->server, $this->channel]), [
                'title' => 'private assignment',
                'assignee_id' => $this->outsider->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');

        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'assignee_id' => $this->member->id,
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'assignee_id' => $this->outsider->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'assignee_id' => $this->member->id,
        ]);
    }

    public function test_todo_create_rechecks_assignee_membership_after_validation(): void
    {
        $membershipQueryObserved = false;
        $memberRemoved = false;
        $serverId = $this->server->id;
        $memberId = $this->member->id;

        DB::listen(function (QueryExecuted $query) use (&$membershipQueryObserved, &$memberRemoved, $serverId, $memberId): void {
            if ($memberRemoved || ! str_contains(strtolower($query->sql), 'server_user') || ! str_contains(strtolower($query->sql), 'count')) {
                return;
            }

            $membershipQueryObserved = true;
            $memberRemoved = true;

            DB::table('server_user')
                ->where('server_id', $serverId)
                ->where('user_id', $memberId)
                ->delete();
        });

        $response = $this->actingAs($this->member)
            ->postJson(route('servers.channels.todos.store', [$this->server, $this->channel]), [
                'title' => 'racing assignment',
                'assignee_id' => $this->member->id,
            ]);

        $this->assertTrue($membershipQueryObserved);
        $this->assertTrue($memberRemoved);
        $response->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
        $this->assertDatabaseMissing('todos', ['title' => 'racing assignment']);
    }

    public function test_todo_update_rechecks_submitted_assignee_membership_after_validation(): void
    {
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'assignee_id' => $this->owner->id,
        ]);
        $membershipQueryObserved = false;
        $memberRemoved = false;
        $serverId = $this->server->id;
        $memberId = $this->member->id;

        DB::listen(function (QueryExecuted $query) use (&$membershipQueryObserved, &$memberRemoved, $serverId, $memberId): void {
            if ($memberRemoved || ! str_contains(strtolower($query->sql), 'server_user') || ! str_contains(strtolower($query->sql), 'count')) {
                return;
            }

            $membershipQueryObserved = true;
            $memberRemoved = true;

            DB::table('server_user')
                ->where('server_id', $serverId)
                ->where('user_id', $memberId)
                ->delete();
        });

        $response = $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'assignee_id' => $this->member->id,
            ]);

        $this->assertTrue($membershipQueryObserved);
        $this->assertTrue($memberRemoved);
        $response->assertUnprocessable()->assertJsonValidationErrors('assignee_id');
        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'assignee_id' => $this->owner->id,
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

    public function test_todo_partial_update_cannot_create_an_invalid_schedule(): void
    {
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'starts_at' => '2026-08-03 12:00:00',
            'due_at' => '2026-08-03 13:00:00',
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'due_at' => '2026-08-03T11:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_at');

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'starts_at' => '2026-08-03T14:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_at');

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'starts_at' => '2026-08-03 12:00:00',
            'due_at' => '2026-08-03 13:00:00',
        ]);
    }

    public function test_todo_partial_start_update_persists_an_explicit_offset_as_the_same_instant(): void
    {
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'starts_at' => '2026-08-03 10:00:00',
            'due_at' => '2026-08-03 10:00:00',
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'starts_at' => '2026-08-03T19:00:00+09:00',
            ])
            ->assertOk();

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'starts_at' => '2026-08-03 10:00:00',
            'due_at' => '2026-08-03 10:00:00',
        ]);
    }

    public function test_todo_partial_due_update_preserves_marker_for_the_same_explicit_offset_instant(): void
    {
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'starts_at' => '2026-08-03 09:00:00',
            'due_at' => '2026-08-03 10:00:00',
            'reminded_at' => now(),
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'due_at' => '2026-08-03T19:00:00+09:00',
            ])
            ->assertOk();

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'due_at' => '2026-08-03 10:00:00',
        ]);
        $this->assertNotNull($todo->fresh()->reminded_at);
    }

    public function test_todo_partial_due_update_clears_marker_for_an_explicit_offset_instant_change(): void
    {
        $todo = Todo::factory()->create([
            'channel_id' => $this->channel->id,
            'starts_at' => '2026-08-03 09:00:00',
            'due_at' => '2026-08-03 10:00:00',
            'reminded_at' => now(),
        ]);

        $this->actingAs($this->member)
            ->patchJson(route('servers.channels.todos.update', [$this->server, $this->channel, $todo]), [
                'due_at' => '2026-08-03T20:00:00+09:00',
            ])
            ->assertOk();

        $this->assertDatabaseHas('todos', [
            'id' => $todo->id,
            'due_at' => '2026-08-03 11:00:00',
        ]);
        $this->assertNull($todo->fresh()->reminded_at);
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

    public function test_uploaded_file_response_includes_urls_for_composer_preview(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->member)
            ->post(route('servers.files.store', $this->server), [
                'files' => [UploadedFile::fake()->image('clipboard.png', 640, 480)],
            ])
            ->assertCreated();

        $file = StoredFile::query()->findOrFail($response->json('files.0.id'));

        $response
            ->assertJsonPath('files.0.stream_url', route('servers.files.stream', [$this->server, $file]))
            ->assertJsonPath('files.0.download_url', route('servers.files.download', [$this->server, $file]))
            ->assertJsonPath('files.0.thumbnail_url', null);
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
