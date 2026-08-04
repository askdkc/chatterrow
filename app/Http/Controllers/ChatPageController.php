<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Support\MessagePayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChatPageController extends Controller
{
    public function __construct(private MessagePayload $payload) {}

    public function __invoke(Request $request, Server $server, Channel $channel): Response
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $focusMessageId = null;
        $openThreadParentId = null;
        $targetRootId = null;

        if ($request->query('message') !== null) {
            $messageId = (string) $request->query('message');

            abort_unless(preg_match('/\A[1-9][0-9]*\z/', $messageId) === 1, 404);

            $target = Message::query()
                ->whereKey((int) $messageId)
                ->where('server_id', $server->id)
                ->where('channel_id', $channel->id)
                ->first();

            abort_unless($target !== null, 404);

            if ($target->parent_id !== null) {
                $parent = Message::query()
                    ->whereKey($target->parent_id)
                    ->where('server_id', $server->id)
                    ->where('channel_id', $channel->id)
                    ->whereNull('parent_id')
                    ->exists();

                abort_unless($parent, 404);
            }

            $focusMessageId = $target->id;
            $openThreadParentId = $target->parent_id;
            $targetRootId = $target->parent_id ?? $target->id;
        }

        $server->load([
            'channels' => fn ($query) => $query->orderBy('name'),
            'members:id,name,email',
        ]);

        $rootIds = Message::query()
            ->where('channel_id', $channel->id)
            ->whereNull('parent_id')
            ->latest('id')
            ->limit(100)
            ->pluck('id')
            ->all();

        if ($targetRootId !== null && ! in_array($targetRootId, $rootIds, true)) {
            $rootIds[] = $targetRootId;
        }

        $messages = Message::query()
            ->with(['user:id,name,email', 'attachments', 'mentions.user:id,name', 'reactions.user:id,name'])
            ->withCount(['replies as reply_count'])
            ->whereIn('id', $rootIds)
            ->where('server_id', $server->id)
            ->where('channel_id', $channel->id)
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message): array => $this->payload->make($message))
            ->values();

        return Inertia::render('chat/Chat', [
            'server' => $server,
            'channel' => $channel->load('creator:id,name,email'),
            'initialMessages' => $messages,
            'members' => $server->members,
            'focus_message_id' => $focusMessageId,
            'open_thread_parent_id' => $openThreadParentId,
        ]);
    }
}
