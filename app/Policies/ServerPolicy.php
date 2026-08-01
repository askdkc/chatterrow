<?php

namespace App\Policies;

use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Server $server): bool
    {
        return $server->members()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Server $server): bool
    {
        return $server->created_by === $user->id;
    }

    public function delete(User $user, Server $server): bool
    {
        return $server->created_by === $user->id;
    }

    public function manageMembers(User $user, Server $server): bool
    {
        return $server->created_by === $user->id;
    }
}
