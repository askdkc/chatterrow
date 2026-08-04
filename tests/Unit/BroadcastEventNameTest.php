<?php

namespace Tests\Unit;

use App\Events\MentionNotificationCreated;
use App\Events\MessageCreated;
use App\Events\MessageReactionUpdated;
use App\Events\ReminderCreated;
use App\Events\TodoUpdated;
use App\Models\Message;
use App\Models\MessageMention;
use App\Models\Todo;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BroadcastEventNameTest extends TestCase
{
    #[DataProvider('events')]
    public function test_event_name_matches_the_short_name_used_by_echo(object $event, string $name): void
    {
        $this->assertSame($name, $event->broadcastAs());
    }

    public function test_message_creation_broadcasts_without_waiting_for_a_queue_worker(): void
    {
        $this->assertInstanceOf(ShouldBroadcastNow::class, new MessageCreated(new Message));
    }

    public function test_mention_notifications_broadcast_without_waiting_for_a_queue_worker(): void
    {
        $this->assertInstanceOf(ShouldBroadcastNow::class, new MentionNotificationCreated(new MessageMention));
    }

    public function test_message_reactions_broadcast_without_waiting_for_a_queue_worker(): void
    {
        $this->assertInstanceOf(ShouldBroadcastNow::class, new MessageReactionUpdated(new Message));
    }

    /** @return iterable<string, array{object, string}> */
    public static function events(): iterable
    {
        yield 'message created' => [new MessageCreated(new Message), 'MessageCreated'];
        yield 'message reaction updated' => [new MessageReactionUpdated(new Message), 'MessageReactionUpdated'];
        yield 'reminder created' => [new ReminderCreated(new Message), 'ReminderCreated'];
        yield 'todo updated' => [new TodoUpdated(new Todo), 'TodoUpdated'];
        yield 'mention notification created' => [
            new MentionNotificationCreated(new MessageMention),
            'MentionNotificationCreated',
        ];
    }
}
