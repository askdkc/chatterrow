<?php

namespace App\Http\Controllers;

use App\Actions\MessageMutation;
use App\Events\MentionNotificationCreated;
use App\Events\MessageCreated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageMention;
use App\Models\Server;
use App\Models\User;
use App\Support\BestEffortBroadcaster;
use App\Support\MessagePayload;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function __construct(
        private BestEffortBroadcaster $broadcaster,
        private MessageMutation $mutation,
        private MessagePayload $payload,
    ) {}

    public function index(Server $server, Channel $channel, Request $request): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $parentId = $request->integer('parent_id') ?: null;

        $query = Message::query()
            ->with(['user:id,name,email', 'attachments', 'mentions.user:id,name', 'reactions.user:id,name'])
            ->withCount(['replies as reply_count'])
            ->where('server_id', $server->id)
            ->where('channel_id', $channel->id)
            ->where('parent_id', $parentId)
            ->latest('id')
            ->limit(min(max($request->integer('limit', 100), 1), 200))
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $message): array => $this->payload->make($message));

        return response()->json(['messages' => $query]);
    }

    public function store(Request $request, Server $server, Channel $channel): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('create', [Message::class, $channel]);

        $validated = $request->validate([
            'body' => ['required_without:attachments', 'string', 'max:10000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')
                    ->where('channel_id', $channel->id)
                    ->whereNull('parent_id'),
            ],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.path' => ['required', 'string'],
            'attachments.*.original_name' => ['required', 'string', 'max:255'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
            'attachments.*.size' => ['nullable', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $result = $this->mutation->create(
            $server,
            $channel,
            $user,
            $validated['body'] ?? '',
            $validated['parent_id'] ?? null,
            $validated['attachments'] ?? [],
        );

        $this->broadcastMutation($result->message, $result->newMentions, true);

        return response()->json(['message' => $this->payload->make($result->message)], 201);
    }

    public function update(Request $request, Server $server, Channel $channel, Message $message): JsonResponse
    {
        $this->ensureMessageBelongsToChannel($server, $channel, $message);
        Gate::authorize('update', $message);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $result = $this->mutation->update($server, $channel, $message, $user, $validated['body']);
        $this->broadcastMutation($result->message, $result->newMentions);

        return response()->json(['message' => $this->payload->make($result->message)]);
    }

    public function destroy(Server $server, Channel $channel, Message $message): JsonResponse
    {
        $this->ensureMessageBelongsToChannel($server, $channel, $message);
        Gate::authorize('delete', $message);

        $message->delete();

        return response()->json(status: 204);
    }

    private function ensureMessageBelongsToChannel(Server $server, Channel $channel, Message $message): void
    {
        abort_unless(
            $channel->server_id === $server->id
                && $message->server_id === $server->id
                && $message->channel_id === $channel->id,
            404,
        );
    }

    /**
     * @param  Collection<int, MessageMention>  $newMentions
     */
    private function broadcastMutation(Message $message, Collection $newMentions, bool $created = false): void
    {
        if ($created) {
            $this->broadcaster->broadcastToOthers(new MessageCreated($message));
        }

        foreach ($newMentions as $mention) {
            $this->broadcaster->broadcast(new MentionNotificationCreated($mention));
        }
    }
}
