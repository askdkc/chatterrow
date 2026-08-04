<?php

namespace App\Policies;

use App\Models\MessageMention;
use App\Models\User;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class MessageMentionPolicy
{
    public function read(User $user, MessageMention $mention): bool
    {
        return DB::table('message_mentions')
            ->join('messages', 'messages.id', '=', 'message_mentions.message_id')
            ->join('channels', function (JoinClause $join): void {
                $join->on('channels.id', '=', 'messages.channel_id')
                    ->on('channels.server_id', '=', 'messages.server_id');
            })
            ->join('server_user', function (JoinClause $join) use ($user): void {
                $join->on('server_user.server_id', '=', 'messages.server_id')
                    ->where('server_user.user_id', '=', $user->id);
            })
            ->where('message_mentions.id', $mention->id)
            ->where('message_mentions.user_id', $user->id)
            ->exists();
    }

    public function delete(User $user, MessageMention $mention): bool
    {
        return $this->read($user, $mention);
    }
}
