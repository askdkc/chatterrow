<?php

namespace App\Http\Controllers;

use App\Models\MessageMention;
use App\Models\User;
use App\Support\MentionNotificationPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class NotificationController extends Controller
{
    public function __construct(private MentionNotificationPayload $payload) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = $this->visibleQuery($user);
        $perPage = min(max($request->integer('per_page', $request->integer('limit', 25)), 1), 100);

        $total = (clone $query)->count('message_mentions.id');
        $unread = (clone $query)
            ->whereNull('message_mentions.read_at')
            ->count('message_mentions.id');
        $serverCounts = $this->unreadCounts(clone $query, 'server_id');
        $channelCounts = $this->unreadCounts(clone $query, 'channel_id');
        $paginator = $query
            ->with([
                'message.user:id,name,email',
                'message.channel:id,name,server_id',
                'message.server:id,name',
                'message.mentions.user:id,name',
            ])
            ->orderByDesc('message_mentions.created_at')
            ->orderByDesc('message_mentions.id')
            ->cursorPaginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (MessageMention $mention): array => $this->payload->make($mention))
            ->values();

        return response()->json([
            'items' => $items,
            'notifications' => $items,
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'previous_cursor' => $paginator->previousCursor()?->encode(),
            'total' => $total,
            'unread' => $unread,
            'server_counts' => $serverCounts,
            'channel_counts' => $channelCounts,
            'counts' => [
                'total' => $total,
                'unread' => $unread,
                'servers' => $serverCounts,
                'channels' => $channelCounts,
            ],
        ]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ids = $this->visibleQuery($user)->pluck('message_mentions.id');

        $deleted = $ids->isEmpty()
            ? 0
            : MessageMention::query()
                ->whereIn('id', $ids)
                ->whereNull('dismissed_at')
                ->update(['dismissed_at' => now()]);

        return response()->json(['deleted' => $deleted]);
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $ids = $this->visibleQuery($user)
            ->whereNull('message_mentions.read_at')
            ->pluck('message_mentions.id');

        $updated = $ids->isEmpty()
            ? 0
            : MessageMention::query()
                ->whereIn('id', $ids)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

        return response()->json(['updated' => $updated]);
    }

    public function read(Request $request, MessageMention $messageMention): JsonResponse
    {
        Gate::authorize('read', $messageMention);

        if ($messageMention->read_at === null) {
            $messageMention->update(['read_at' => now()]);
        }

        $messageMention->refresh();

        return response()->json(['notification' => $this->payload->make($messageMention)]);
    }

    public function destroy(Request $request, MessageMention $messageMention): JsonResponse
    {
        Gate::authorize('delete', $messageMention);
        $messageMention->update(['dismissed_at' => now()]);

        return response()->json(['deleted' => true]);
    }

    /** @return Builder<MessageMention> */
    private function visibleQuery(User $user): Builder
    {
        return MessageMention::query()
            ->select('message_mentions.*')
            ->join('messages', 'messages.id', '=', 'message_mentions.message_id')
            ->join('channels', function (JoinClause $join): void {
                $join->on('channels.id', '=', 'messages.channel_id')
                    ->on('channels.server_id', '=', 'messages.server_id');
            })
            ->join('servers', 'servers.id', '=', 'messages.server_id')
            ->join('server_user', function (JoinClause $join) use ($user): void {
                $join->on('server_user.server_id', '=', 'servers.id')
                    ->where('server_user.user_id', '=', $user->id);
            })
            ->where('message_mentions.user_id', $user->id)
            ->whereNull('message_mentions.dismissed_at');
    }

    /**
     * @param  Builder<MessageMention>  $query
     * @return array<string, int>
     */
    private function unreadCounts(Builder $query, string $column): array
    {
        $rows = $query
            ->whereNull('message_mentions.read_at')
            ->select("messages.{$column} as count_key", DB::raw('count(message_mentions.id) as unread_count'))
            ->groupBy("messages.{$column}")
            ->get()
            ->map(fn (MessageMention $row): array => [
                'count_key' => $row->getAttribute('count_key'),
                'unread_count' => $row->getAttribute('unread_count'),
            ]);

        return $rows
            ->mapWithKeys(fn (array $row): array => [
                (string) $row['count_key'] => (int) $row['unread_count'],
            ])
            ->all();
    }
}
