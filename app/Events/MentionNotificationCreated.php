<?php

namespace App\Events;

use App\Models\MessageMention;
use App\Support\MentionNotificationPayload;
use Illuminate\Broadcasting\Channel as BroadcastingChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MentionNotificationCreated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MessageMention $mention) {}

    /** @return array<int, BroadcastingChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->mention->user_id}")];
    }

    public function broadcastAs(): string
    {
        return 'MentionNotificationCreated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['notification' => app(MentionNotificationPayload::class)->make($this->mention)];
    }
}
