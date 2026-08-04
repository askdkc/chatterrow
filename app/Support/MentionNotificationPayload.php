<?php

namespace App\Support;

use App\Models\MessageMention;

final class MentionNotificationPayload
{
    public function __construct(private MessagePayload $messages) {}

    /** @return array<string, mixed> */
    public function make(MessageMention $mention): array
    {
        $mention->loadMissing([
            'message.user:id,name,email',
            'message.channel:id,name,server_id',
            'message.server:id,name',
        ]);

        $message = $mention->message;

        return [
            'id' => $mention->id,
            'kind' => $mention->kind,
            'message_id' => $message->id,
            'parent_message_id' => $message->parent_id,
            'parent_id' => $message->parent_id,
            'server_id' => $message->server_id,
            'server_name' => $message->server->name,
            'channel_id' => $message->channel_id,
            'channel_name' => $message->channel->name,
            'author' => $message->user?->only(['id', 'name', 'email']),
            'excerpt' => $this->messages->excerpt($message),
            'created_at' => $mention->created_at,
            'read_at' => $mention->read_at,
        ];
    }
}
