<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\Todo;
use App\Models\User;

class TodoPolicy
{
    public function view(User $user, Todo $todo): bool
    {
        return $todo->channel->server->members()->whereKey($user->id)->exists();
    }

    public function create(User $user, Channel $channel): bool
    {
        return $channel->server->archived_at === null
            && $channel->server->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, Todo $todo): bool
    {
        return $todo->channel->server->archived_at === null
            && $todo->channel->server->members()->whereKey($user->id)->exists();
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $todo->channel->server->archived_at === null
            && $todo->channel->server->members()->whereKey($user->id)->exists();
    }
}
