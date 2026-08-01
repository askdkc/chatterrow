<?php

namespace App\Events;

use App\Models\Todo;
use Illuminate\Broadcasting\Channel as BroadcastingChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TodoUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Todo $todo) {}

    /** @return array<int, BroadcastingChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("server.{$this->todo->channel->server_id}.channel.{$this->todo->channel_id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['todo' => $this->todo->load(['assignee:id,name,email', 'creator:id,name,email'])];
    }
}
