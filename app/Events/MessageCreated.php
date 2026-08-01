<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel as BroadcastingChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    /** @return array<int, BroadcastingChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("server.{$this->message->server_id}.channel.{$this->message->channel_id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['message' => $this->message->load(['user:id,name,email', 'attachments'])];
    }
}
