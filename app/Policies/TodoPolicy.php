<?php

namespace App\Policies;

use App\Models\Todo;
use App\Models\User;

class TodoPolicy
{
    public function view(User $user, Todo $todo): bool
    {
        return $todo->channel->server->members()->whereKey($user->id)->exists();
    }

    public function create(User $user, Todo $todo): bool
    {
        return $todo->channel->server->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, Todo $todo): bool
    {
        return $todo->channel->server->members()->whereKey($user->id)->exists();
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $todo->channel->server->members()->whereKey($user->id)->exists();
    }
}
