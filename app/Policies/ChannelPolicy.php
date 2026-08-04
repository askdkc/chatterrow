<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\Server;
use App\Models\User;

class ChannelPolicy
{
    public function view(User $user, Channel $channel): bool
    {
        return $channel->server->members()->whereKey($user->id)->exists();
    }

    public function create(User $user, Server $server): bool
    {
        return $server->archived_at === null
            && $server->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, Channel $channel): bool
    {
        return $channel->server->archived_at === null
            && $channel->server->members()->whereKey($user->id)->exists();
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $channel->server->archived_at === null
            && $channel->server->members()->whereKey($user->id)->exists();
    }
}
