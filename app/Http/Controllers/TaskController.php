<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Server;
use App\Models\Todo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * Server-wide task list: every channel (as a task) + every todo across channels.
     */
    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        $channels = Channel::query()
            ->where('server_id', $server->id)
            ->withCount(['todos as open_todos_count' => fn ($query) => $query->whereNull('completed_at')])
            ->withCount('todos')
            ->orderBy('name')
            ->get();

        $todos = Todo::query()
            ->with(['assignee:id,name,email', 'channel:id,name'])
            ->whereIn('channel_id', $channels->pluck('id'))
            ->orderBy('completed_at')
            ->orderBy('due_on')
            ->get();

        return Inertia::render('servers/Tasks', [
            'server' => $server,
            'channels' => $channels,
            'todos' => $todos,
        ]);
    }

    /**
     * Channel task list: the channel itself (as a task) + its todos.
     */
    public function channelTasks(Server $server, Channel $channel): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $todos = Todo::query()
            ->with(['assignee:id,name,email', 'creator:id,name,email'])
            ->where('channel_id', $channel->id)
            ->orderBy('completed_at')
            ->orderBy('due_on')
            ->get();

        return response()->json([
            'channel' => $channel,
            'todos' => $todos,
        ]);
    }

    /**
     * Gantt page for a server: all channels as bars + todos as bars grouped by channel.
     */
    public function gantt(Server $server): Response
    {
        Gate::authorize('view', $server);

        $channels = Channel::query()
            ->where('server_id', $server->id)
            ->orderBy('starts_on')
            ->orderBy('name')
            ->get();

        $todos = Todo::query()
            ->with(['channel:id,name'])
            ->whereIn('channel_id', $channels->pluck('id'))
            ->whereNotNull('due_on')
            ->orderBy('due_on')
            ->get();

        $tasks = $channels->map(fn (Channel $channel) => [
            'id' => "channel-{$channel->id}",
            'type' => 'channel',
            'title' => $channel->name,
            'start' => $channel->starts_on?->toDateString(),
            'end' => $channel->ends_on?->toDateString(),
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
        ])->concat($todos->map(fn (Todo $todo) => [
            'id' => "todo-{$todo->id}",
            'type' => 'todo',
            'title' => $todo->title,
            'start' => $todo->due_on?->toDateString(),
            'end' => $todo->due_on?->toDateString(),
            'channel_id' => $todo->channel_id,
            'channel_name' => $todo->channel?->name,
            'completed' => $todo->completed_at !== null,
        ]))->values();

        return Inertia::render('servers/Gantt', [
            'server' => $server,
            'tasks' => $tasks,
        ]);
    }

    /**
     * Gantt data for one channel: the channel bar + its todos.
     */
    public function channelGantt(Server $server, Channel $channel): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $todos = Todo::query()
            ->where('channel_id', $channel->id)
            ->whereNotNull('due_on')
            ->orderBy('due_on')
            ->get();

        $tasks = collect([
            [
                'id' => "channel-{$channel->id}",
                'type' => 'channel',
                'title' => $channel->name,
                'start' => $channel->starts_on?->toDateString(),
                'end' => $channel->ends_on?->toDateString(),
                'channel_id' => $channel->id,
                'channel_name' => $channel->name,
            ],
        ])->concat($todos->map(fn (Todo $todo) => [
            'id' => "todo-{$todo->id}",
            'type' => 'todo',
            'title' => $todo->title,
            'start' => $todo->due_on?->toDateString(),
            'end' => $todo->due_on?->toDateString(),
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'completed' => $todo->completed_at !== null,
        ]))->values();

        return response()->json([
            'channel' => $channel,
            'tasks' => $tasks,
        ]);
    }
}
