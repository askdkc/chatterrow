<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function create(User $user, Channel $channel): bool
    {
        return $channel->server->archived_at === null
            && $channel->server->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, Message $message): bool
    {
        return $message->server->archived_at === null
            && $message->user_id === $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->server->archived_at === null
            && ($message->user_id === $user->id
                || $message->server->isAdministrator($user));
    }

    public function react(User $user, Message $message): bool
    {
        return $message->server->archived_at === null
            && $message->server->members()->whereKey($user->id)->exists();
    }
}
