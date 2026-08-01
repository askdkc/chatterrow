<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function create(User $user, Message $message): bool
    {
        return $message->server->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, Message $message): bool
    {
        return $message->user_id === $user->id;
    }

    public function delete(User $user, Message $message): bool
    {
        return $message->user_id === $user->id
            || $message->server->created_by === $user->id;
    }
}
