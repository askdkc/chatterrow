<?php

namespace Tests\Feature;

use App\Events\MentionNotificationCreated;
use App\Events\MessageCreated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageMention;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MessageMentionTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $member;

    private User $secondMember;

    private User $outsider;

    private Server $server;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create(['name' => 'Author']);
        $this->member = User::factory()->create(['name' => 'Member']);
        $this->secondMember = User::factory()->create(['name' => 'Second Member']);
        $this->outsider = User::factory()->create(['name' => 'Outsider']);

        $this->server = Server::factory()->create(['created_by' => $this->author->id]);
        $this->server->members()->attach([
            $this->author->id,
            $this->member->id,
            $this->secondMember->id,
        ]);
        $this->channel = Channel::factory()->create([
            'server_id' => $this->server->id,
            'created_by' => $this->author->id,
        ]);

        Event::fake([MessageCreated::class, MentionNotificationCreated::class]);
    }

    public function test_direct_mentions_are_deduplicated_and_return_resolved_payloads(): void
    {
        $response = $this->actingAs($this->author)->postJson($this->messageRoute(), [
            'body' => "hello <@{$this->member->id}> again <@{$this->member->id}>",
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.mentions.0.id', $this->member->id)
            ->assertJsonPath('message.mentions.0.name', 'Member')
            ->assertJsonPath('message.mentions.0.kind', 'direct');

        $this->assertDatabaseCount('message_mentions', 1);
        $this->assertDatabaseHas('message_mentions', [
            'user_id' => $this->member->id,
            'kind' => 'direct',
            'read_at' => null,
        ]);
        Event::assertDispatched(MentionNotificationCreated::class, 1);
    }

    public function test_everyone_expands_current_members_excludes_author_and_deduplicates_direct_overlap(): void
    {
        $this->actingAs($this->author)->postJson($this->messageRoute(), [
            'body' => "<!everyone> <@{$this->member->id}>",
        ])->assertCreated();

        $this->assertDatabaseCount('message_mentions', 2);
        $this->assertDatabaseHas('message_mentions', [
            'user_id' => $this->member->id,
            'kind' => 'direct',
        ]);
        $this->assertDatabaseHas('message_mentions', [
            'user_id' => $this->secondMember->id,
            'kind' => 'everyone',
        ]);
        $this->assertDatabaseMissing('message_mentions', ['user_id' => $this->author->id]);
        Event::assertDispatched(MentionNotificationCreated::class, 2);
    }

    public function test_self_mentions_are_rendered_without_creating_a_notification(): void
    {
        $response = $this->actingAs($this->author)->postJson($this->messageRoute(), [
            'body' => "hello <@{$this->author->id}>",
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.body', "hello <@{$this->author->id}>")
            ->assertJsonPath('message.mentions.0.id', $this->author->id)
            ->assertJsonPath('message.mentions.0.name', 'Author');

        $this->assertDatabaseCount('message_mentions', 0);
        Event::assertNotDispatched(MentionNotificationCreated::class);
    }

    public function test_non_member_direct_mentions_are_rejected_without_persisting_the_message(): void
    {
        $this->actingAs($this->author)
            ->postJson($this->messageRoute(), [
                'body' => "hello <@{$this->outsider->id}>",
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('message_mentions', 0);
    }

    public function test_code_tokens_are_not_extracted(): void
    {
        $body = "inline `<@{$this->outsider->id}>`\n```\n<@{$this->member->id}> <!everyone>\n```";

        $this->actingAs($this->author)->postJson($this->messageRoute(), [
            'body' => $body,
        ])->assertCreated();

        $this->assertDatabaseCount('message_mentions', 0);
    }

    public function test_reminder_messages_do_not_create_mentions_when_edited(): void
    {
        $message = Message::query()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->author->id,
            'body' => 'reminder',
            'is_reminder' => true,
        ]);

        $this->actingAs($this->author)
            ->patchJson($this->updateRoute($message), ['body' => '<!everyone>'])
            ->assertOk();

        $this->assertDatabaseCount('message_mentions', 0);
    }

    public function test_edit_preserves_read_state_deletes_removed_rows_and_unread_readded_rows(): void
    {
        $message = $this->createMentionedMessage("<@{$this->member->id}>");
        $memberMention = MessageMention::query()->where('message_id', $message->id)->firstOrFail();
        $readAt = Carbon::now()->subMinute();
        $memberMention->update(['read_at' => $readAt]);

        $this->actingAs($this->author)
            ->patchJson($this->updateRoute($message), [
                'body' => "<@{$this->member->id}> <@{$this->secondMember->id}>",
            ])
            ->assertOk();

        $this->assertSame($memberMention->id, $memberMention->fresh()->id);
        $this->assertEquals($readAt->toDateTimeString(), $memberMention->fresh()->read_at?->toDateTimeString());
        $secondMention = MessageMention::query()
            ->where('message_id', $message->id)
            ->where('user_id', $this->secondMember->id)
            ->firstOrFail();
        $this->assertNull($secondMention->read_at);

        $this->actingAs($this->author)
            ->patchJson($this->updateRoute($message), [
                'body' => "<@{$this->secondMember->id}>",
            ])
            ->assertOk();

        $this->assertDatabaseMissing('message_mentions', ['id' => $memberMention->id]);
        $this->assertDatabaseHas('message_mentions', ['id' => $secondMention->id]);

        $this->actingAs($this->author)
            ->patchJson($this->updateRoute($message), [
                'body' => "<@{$this->member->id}>",
            ])
            ->assertOk();

        $readded = MessageMention::query()
            ->where('message_id', $message->id)
            ->where('user_id', $this->member->id)
            ->firstOrFail();
        $this->assertNotSame($memberMention->id, $readded->id);
        $this->assertNull($readded->read_at);
    }

    public function test_notification_list_counts_only_accessible_rows_and_read_requires_ownership(): void
    {
        $message = $this->createMentionedMessage("<@{$this->member->id}>");
        $mention = MessageMention::query()->where('message_id', $message->id)->firstOrFail();

        $this->actingAs($this->member)
            ->getJson('/notifications')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('unread', 1)
            ->assertJsonPath("server_counts.{$this->server->id}", 1)
            ->assertJsonPath("channel_counts.{$this->channel->id}", 1)
            ->assertJsonPath('items.0.message_id', $message->id);

        $this->actingAs($this->outsider)
            ->patchJson(route('notifications.read', $mention))
            ->assertForbidden();

        $this->actingAs($this->member)
            ->patchJson(route('notifications.read', $mention))
            ->assertOk();

        $this->assertNotNull($mention->fresh()->read_at);
    }

    public function test_removed_members_cannot_list_or_read_old_notifications(): void
    {
        $message = $this->createMentionedMessage("<@{$this->member->id}>");
        $mention = MessageMention::query()->where('message_id', $message->id)->firstOrFail();
        $this->server->members()->detach($this->member->id);

        $this->actingAs($this->member)
            ->getJson('/notifications')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'items');

        $this->actingAs($this->member)
            ->patchJson(route('notifications.read', $mention))
            ->assertForbidden();
    }

    public function test_read_all_marks_only_the_current_members_visible_notifications(): void
    {
        $this->createMentionedMessage("first <@{$this->member->id}>");
        $this->createMentionedMessage("second <@{$this->member->id}>");

        $this->actingAs($this->member)
            ->patchJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->actingAs($this->member)
            ->getJson('/notifications')
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('server_counts', []);
    }

    public function test_member_can_delete_a_notification_but_other_users_cannot(): void
    {
        $message = $this->createMentionedMessage("hello <@{$this->member->id}>");
        $mention = MessageMention::query()->where('message_id', $message->id)->firstOrFail();

        $this->actingAs($this->outsider)
            ->deleteJson(route('notifications.destroy', $mention))
            ->assertForbidden();

        $this->actingAs($this->member)
            ->deleteJson(route('notifications.destroy', $mention))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseHas('message_mentions', [
            'id' => $mention->id,
        ]);
        $this->assertNotNull($mention->fresh()->dismissed_at);

        $this->actingAs($this->member)
            ->getJson('/notifications')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonCount(0, 'items');
    }

    public function test_delete_all_removes_only_the_current_members_visible_notifications(): void
    {
        $this->createMentionedMessage("first <@{$this->member->id}>");
        $this->createMentionedMessage("second <@{$this->member->id}>");
        $otherMessage = $this->createMentionedMessage("other <@{$this->secondMember->id}>");

        $this->actingAs($this->member)
            ->deleteJson(route('notifications.destroy-all'))
            ->assertOk()
            ->assertJsonPath('deleted', 2);

        $this->assertDatabaseCount('message_mentions', 3);
        $this->assertDatabaseHas('message_mentions', [
            'message_id' => $otherMessage->id,
            'user_id' => $this->secondMember->id,
            'dismissed_at' => null,
        ]);
        $this->assertDatabaseMissing('message_mentions', [
            'user_id' => $this->member->id,
            'dismissed_at' => null,
        ]);
    }

    public function test_notification_list_uses_a_cursor_for_subsequent_pages(): void
    {
        $this->createMentionedMessage("first <@{$this->member->id}>");
        $this->createMentionedMessage("second <@{$this->member->id}>");
        $this->createMentionedMessage("third <@{$this->member->id}>");

        $firstPage = $this->actingAs($this->member)
            ->getJson('/notifications?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'items');
        $cursor = $firstPage->json('next_cursor');

        $this->assertNotEmpty($cursor);
        $this->actingAs($this->member)
            ->getJson('/notifications?per_page=2&cursor='.urlencode((string) $cursor))
            ->assertOk()
            ->assertJsonCount(1, 'items');
    }

    public function test_message_payload_redacts_unresolved_direct_tokens_and_broadcasts_mentions(): void
    {
        $message = $this->createMentionedMessage("hello <@{$this->member->id}>");
        $eventPayload = (new MessageCreated($message->fresh()))->broadcastWith();

        $this->assertSame($this->member->id, $eventPayload['message']['mentions'][0]['id']);
        MessageMention::query()->where('message_id', $message->id)->delete();

        $this->actingAs($this->member)
            ->getJson(route('servers.channels.messages.index', [$this->server, $this->channel]))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'hello [deleted user]')
            ->assertJsonCount(0, 'messages.0.mentions');
    }

    public function test_focus_query_loads_an_old_thread_parent_and_exposes_focus_props(): void
    {
        $oldRoot = $this->createMessage('old root');
        $reply = Message::query()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
            'parent_id' => $oldRoot->id,
            'body' => 'old reply',
        ]);

        for ($index = 0; $index < 100; $index++) {
            $this->createMessage("new root {$index}");
        }

        $this->actingAs($this->member)
            ->get(route('servers.channels.show', [$this->server, $this->channel]).'?message='.$reply->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('chat/Chat')
                ->where('focus_message_id', $reply->id)
                ->where('open_thread_parent_id', $oldRoot->id)
                ->where('initialMessages.0.id', $oldRoot->id));
    }

    private function createMessage(string $body): Message
    {
        return Message::query()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->author->id,
            'body' => $body,
        ]);
    }

    private function createMentionedMessage(string $body): Message
    {
        $response = $this->actingAs($this->author)->postJson($this->messageRoute(), [
            'body' => $body,
        ]);

        $response->assertCreated();

        return Message::query()->findOrFail($response->json('message.id'));
    }

    private function messageRoute(): string
    {
        return route('servers.channels.messages.store', [$this->server, $this->channel]);
    }

    private function updateRoute(Message $message): string
    {
        return route('servers.channels.messages.update', [$this->server, $this->channel, $message]);
    }
}
