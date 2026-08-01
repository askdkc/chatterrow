<?php

namespace App\Http\Controllers;

use App\Events\TodoUpdated;
use App\Models\Channel;
use App\Models\Server;
use App\Models\Todo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TodoController extends Controller
{
    public function index(Server $server, Channel $channel, Request $request): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $todos = Todo::query()
            ->with(['assignee:id,name,email', 'creator:id,name,email'])
            ->where('channel_id', $channel->id)
            ->orderBy('completed_at')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json(['todos' => $todos]);
    }

    public function store(Request $request, Server $server, Channel $channel): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('create', [Todo::class, $channel]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
            'due_on' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $todo = $channel->todos()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
            'position' => Todo::query()->where('channel_id', $channel->id)->count(),
        ]);

        $todo->load(['assignee:id,name,email', 'creator:id,name,email']);

        broadcast(new TodoUpdated($todo))->toOthers();

        return response()->json(['todo' => $todo], 201);
    }

    public function update(Request $request, Server $server, Channel $channel, Todo $todo): JsonResponse
    {
        abort_unless($todo->channel_id === $channel->id && $channel->server_id === $server->id, 404);
        Gate::authorize('update', $todo);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'details' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'due_on' => ['sometimes', 'nullable', 'date'],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $todo->update($validated);

        $todo->load(['assignee:id,name,email', 'creator:id,name,email']);

        broadcast(new TodoUpdated($todo))->toOthers();

        return response()->json(['todo' => $todo]);
    }

    public function toggle(Request $request, Server $server, Channel $channel, Todo $todo): JsonResponse
    {
        abort_unless($todo->channel_id === $channel->id && $channel->server_id === $server->id, 404);
        Gate::authorize('update', $todo);

        $todo->completed_at = $todo->completed_at === null ? now() : null;
        $todo->completed_by = $todo->completed_at !== null ? $request->user()?->id : null;
        $todo->save();

        $todo->load(['assignee:id,name,email', 'creator:id,name,email']);

        broadcast(new TodoUpdated($todo))->toOthers();

        return response()->json(['todo' => $todo]);
    }

    public function destroy(Server $server, Channel $channel, Todo $todo): RedirectResponse
    {
        abort_unless($todo->channel_id === $channel->id && $channel->server_id === $server->id, 404);
        Gate::authorize('delete', $todo);

        $todo->delete();

        return back();
    }
}
