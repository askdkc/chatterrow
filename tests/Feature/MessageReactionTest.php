<?php

namespace Tests\Feature;

use App\Events\MessageReactionUpdated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MessageReactionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $outsider;

    private Server $server;

    private Channel $channel;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Owner']);
        $this->member = User::factory()->create(['name' => 'Member']);
        $this->outsider = User::factory()->create();
        $this->server = Server::factory()->create(['created_by' => $this->owner->id]);
        $this->server->members()->attach([$this->owner->id, $this->member->id]);
        $this->channel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->owner->id,
        ]);
        $this->message = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_members_can_add_and_remove_grouped_reactions_idempotently(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $route = route('servers.channels.messages.reactions.store', [
            $this->server,
            $this->channel,
            $this->message,
        ]);

        $this->actingAs($this->owner)
            ->putJson($route, ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('message.reactions.0.emoji', '👍')
            ->assertJsonPath('message.reactions.0.count', 1)
            ->assertJsonPath('message.reactions.0.user_ids', [$this->owner->id])
            ->assertJsonPath('message.reactions.0.user_names', ['Owner']);

        $this->putJson($route, ['emoji' => '👍'])->assertOk();
        $this->assertDatabaseCount('message_reactions', 1);

        $this->actingAs($this->member)
            ->putJson($route, ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('message.reactions.0.count', 2)
            ->assertJsonPath('message.reactions.0.user_ids', [
                $this->owner->id,
                $this->member->id,
            ])
            ->assertJsonPath('message.reactions.0.user_names', [
                'Owner',
                'Member',
            ]);

        $deleteRoute = route('servers.channels.messages.reactions.destroy', [
            $this->server,
            $this->channel,
            $this->message,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson($deleteRoute, ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('message.reactions.0.count', 1)
            ->assertJsonPath('message.reactions.0.user_ids', [$this->member->id]);

        $this->deleteJson($deleteRoute, ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('message.reactions.0.count', 1);

        Event::assertDispatched(MessageReactionUpdated::class);
    }

    public function test_reactions_accept_one_emoji_grapheme_or_text_stamp_and_reject_other_text(): void
    {
        $route = route('servers.channels.messages.reactions.store', [
            $this->server,
            $this->channel,
            $this->message,
        ]);

        $this->actingAs($this->member)
            ->putJson($route, ['emoji' => '👍🏽'])
            ->assertOk()
            ->assertJsonPath('message.reactions.0.emoji', '👍🏽');

        $this->putJson($route, ['emoji' => 'stamp:それな'])
            ->assertOk()
            ->assertJsonFragment(['emoji' => 'stamp:それな']);

        $this->putJson($route, ['emoji' => 'stamp:たしかに'])
            ->assertOk()
            ->assertJsonFragment(['emoji' => 'stamp:たしかに']);

        $this->putJson($route, ['emoji' => 'stamp:v1:ff0000:00ff00:了解'])
            ->assertOk()
            ->assertJsonFragment(['emoji' => 'stamp:v1:ff0000:00ff00:了解']);

        $this->putJson($route, ['emoji' => 'stamp:v1:5865f2:none:確認'])
            ->assertOk()
            ->assertJsonFragment(['emoji' => 'stamp:v1:5865f2:none:確認']);

        foreach ([
            'a',
            'ab',
            '👍🎉',
            'stamp:',
            'stamp:12345',
            "stamp:\n",
            'stamp:v1:zz0000:ffffff:不可',
            'stamp:v1:ff0000:transparent:不可',
            'stamp:v1:ff0000:ffffff:12345',
            "stamp:v1:ff0000:ffffff:\n",
        ] as $invalid) {
            $this->putJson($route, ['emoji' => $invalid])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('emoji');
        }
    }

    public function test_outsiders_cross_channel_messages_and_archived_projects_are_rejected(): void
    {
        $route = route('servers.channels.messages.reactions.store', [
            $this->server,
            $this->channel,
            $this->message,
        ]);

        $this->actingAs($this->outsider)
            ->putJson($route, ['emoji' => '🎉'])
            ->assertForbidden();

        $otherChannel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->putJson(route('servers.channels.messages.reactions.store', [
                $this->server,
                $otherChannel,
                $this->message,
            ]), ['emoji' => '🎉'])
            ->assertNotFound();

        $this->server->forceFill(['archived_at' => now()])->save();

        $this->putJson($route, ['emoji' => '🎉'])->assertForbidden();
        $this->assertDatabaseCount('message_reactions', 0);
    }

    public function test_message_payload_groups_reactions_on_index(): void
    {
        $this->message->reactions()->createMany([
            ['user_id' => $this->owner->id, 'emoji' => '❤️'],
            ['user_id' => $this->member->id, 'emoji' => '❤️'],
            ['user_id' => $this->member->id, 'emoji' => '🎉'],
        ]);

        $this->actingAs($this->member)
            ->getJson(route('servers.channels.messages.index', [
                $this->server,
                $this->channel,
            ]))
            ->assertOk()
            ->assertJsonPath('messages.0.reactions.0.emoji', '❤️')
            ->assertJsonPath('messages.0.reactions.0.count', 2)
            ->assertJsonPath('messages.0.reactions.1.emoji', '🎉')
            ->assertJsonPath('messages.0.reactions.1.count', 1);
    }
}
