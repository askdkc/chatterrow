<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\Todo;
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
            ])
            ->assertCreated();

        $this->assertDatabaseHas('todos', [
            'channel_id' => $this->channel->id,
            'created_by' => $this->member->id,
            'title' => 'ship it',
        ]);
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
}
