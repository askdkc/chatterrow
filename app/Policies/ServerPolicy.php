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
        return $server->isAdministrator($user)
            && $server->archived_at === null;
    }

    public function delete(User $user, Server $server): bool
    {
        return $server->isAdministrator($user);
    }

    public function manageMembers(User $user, Server $server): bool
    {
        return $server->isAdministrator($user)
            && $server->archived_at === null;
    }

    public function viewInvitations(User $user, Server $server): bool
    {
        return $server->isAdministrator($user);
    }

    public function mutateContent(User $user, Server $server): bool
    {
        return $server->archived_at === null
            && $server->members()->whereKey($user->id)->exists();
    }

    public function archive(User $user, Server $server): bool
    {
        return $server->isAdministrator($user)
            && $server->archived_at === null;
    }

    public function restore(User $user, Server $server): bool
    {
        return $server->isAdministrator($user)
            && $server->archived_at !== null;
    }
}
