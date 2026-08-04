<?php

use App\Models\Channel;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('server.{serverId}.channel.{channelId}', function ($user, int $serverId, int $channelId) {
    $channel = Channel::query()->with('server')->find($channelId);

    return $channel !== null
        && $channel->server_id === $serverId
        && $channel->server->members()->whereKey($user->id)->exists();
});

Broadcast::channel('users.{userId}', function ($user, int $userId): bool {
    return (int) $user->getAuthIdentifier() === $userId;
});
