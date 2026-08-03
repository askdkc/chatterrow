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
            ->orderBy('due_at')
            ->get();

        return Inertia::render('servers/Tasks', [
            'server' => $server,
            'channels' => $channels,
            'members' => $server->members()->get(['users.id', 'users.name', 'users.email']),
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
            ->orderBy('due_at')
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
            ->whereNotNull('due_at')
            ->orderBy('due_at')
            ->get();

        $tasks = $channels
            ->map(fn (Channel $channel) => $this->channelGanttTask($channel))
            ->concat(
                $todos->map(
                    fn (Todo $todo) => $this->todoGanttTask($todo, $todo->channel),
                ),
            )
            ->values();

        return Inertia::render('servers/Gantt', [
            'server' => $server,
            'channels' => $channels,
            'members' => $server->members()->get(['users.id', 'users.name', 'users.email']),
            'tasks' => $tasks,
        ]);
    }

    /**
     * Gantt page for one channel: the channel bar + its todos.
     */
    public function channelGantt(Server $server, Channel $channel): Response
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $todos = Todo::query()
            ->where('channel_id', $channel->id)
            ->whereNotNull('due_at')
            ->orderBy('due_at')
            ->get();

        $tasks = collect([$this->channelGanttTask($channel)])
            ->concat(
                $todos->map(
                    fn (Todo $todo) => $this->todoGanttTask($todo, $channel),
                ),
            )
            ->values();

        return Inertia::render('servers/Gantt', [
            'server' => $server,
            'channels' => $server->channels()->orderBy('name')->get(),
            'members' => $server->members()->get(['users.id', 'users.name', 'users.email']),
            'tasks' => $tasks,
        ]);
    }

    /**
     * @return array{id: string, type: string, title: string, start: string|null, end: string|null, channel_id: int, channel_name: string}
     */
    private function channelGanttTask(Channel $channel): array
    {
        return [
            'id' => "channel-{$channel->id}",
            'type' => 'channel',
            'title' => $channel->name,
            'start' => $channel->starts_on?->toDateString(),
            'end' => $channel->ends_on?->toDateString(),
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
        ];
    }

    /**
     * @return array{id: string, type: string, title: string, start: string|null, end: string|null, channel_id: int, channel_name: string, completed: bool}
     */
    private function todoGanttTask(Todo $todo, Channel $channel): array
    {
        return [
            'id' => "todo-{$todo->id}",
            'type' => 'todo',
            'title' => $todo->title,
            'start' => ($todo->starts_at ?? $todo->due_at)?->toISOString(),
            'end' => $todo->due_at?->toISOString(),
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'completed' => $todo->completed_at !== null,
        ];
    }
}
