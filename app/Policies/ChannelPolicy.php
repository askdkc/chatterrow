<?php

namespace App\Policies;

use App\Models\Channel;
use App\Models\User;

class ChannelPolicy
{
    public function view(User $user, Channel $channel): bool
    {
        return $channel->server->members()->whereKey($user->id)->exists();
    }

    public function create(User $user, Channel $channel): bool
    {
        return $channel->server->members()->whereKey($user->id)->exists();
    }

    public function update(User $user, Channel $channel): bool
    {
        return $channel->server->members()->whereKey($user->id)->exists();
    }

    public function delete(User $user, Channel $channel): bool
    {
        return $channel->server->members()->whereKey($user->id)->exists();
    }
}
