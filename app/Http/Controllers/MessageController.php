<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function index(Server $server, Channel $channel, Request $request): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $parentId = $request->integer('parent_id') ?: null;

        $query = Message::query()
            ->with(['user:id,name,email', 'attachments'])
            ->where('channel_id', $channel->id)
            ->where('parent_id', $parentId)
            ->latest('id')
            ->limit(min(max($request->integer('limit', 100), 1), 200))
            ->get()
            ->reverse()
            ->values();

        return response()->json(['messages' => $query]);
    }

    public function store(Request $request, Server $server, Channel $channel): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('create', [Message::class, $channel]);

        $validated = $request->validate([
            'body' => ['required_without:attachments', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('messages', 'id')->where('channel_id', $channel->id)],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.path' => ['required', 'string'],
            'attachments.*.original_name' => ['required', 'string', 'max:255'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
            'attachments.*.size' => ['nullable', 'integer'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $message = Message::create([
            'server_id' => $server->id,
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'] ?? '',
        ]);

        foreach ($validated['attachments'] ?? [] as $attachment) {
            $storedFile = StoredFile::query()
                ->where('server_id', $server->id)
                ->where('path', $attachment['path'])
                ->first();

            if ($storedFile !== null && $storedFile->attachable_id === null) {
                $storedFile->update([
                    'attachable_type' => Message::class,
                    'attachable_id' => $message->id,
                    'original_name' => $attachment['original_name'],
                    'mime_type' => $attachment['mime_type'] ?? $storedFile->mime_type,
                    'size' => $attachment['size'] ?? $storedFile->size,
                ]);
            }
        }

        $message->load(['user:id,name,email', 'attachments']);

        broadcast(new MessageCreated($message))->toOthers();

        return response()->json(['message' => $message], 201);
    }

    public function destroy(Server $server, Channel $channel, Message $message): RedirectResponse
    {
        abort_unless($message->server_id === $server->id && $message->channel_id === $channel->id, 404);
        Gate::authorize('delete', $message);

        $message->delete();

        return back();
    }
}
