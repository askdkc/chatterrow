<?php

namespace App\Http\Controllers;

use App\Events\TodoUpdated;
use App\Models\Channel;
use App\Models\Server;
use App\Models\Todo;
use App\Support\BestEffortBroadcaster;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

class TodoController extends Controller
{
    public function __construct(private BestEffortBroadcaster $broadcaster) {}

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
            'starts_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'due_timezone' => ['sometimes', 'string', 'timezone'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assignee_id' => ['nullable', 'integer', $this->assigneeExistsRule($server)],
        ]);
        $validated = $this->normalizeScheduleValues($validated);
        $validated['due_timezone'] ??= config('app.timezone');

        $todo = DB::transaction(function () use ($request, $server, $channel, $validated): Todo {
            if (($validated['assignee_id'] ?? null) !== null) {
                $this->lockAssigneeMembership($server, (int) $validated['assignee_id']);
            }

            return $channel->todos()->create([
                ...$validated,
                'created_by' => $request->user()?->id,
                'position' => Todo::query()->where('channel_id', $channel->id)->count(),
            ]);
        });

        $todo->load(['assignee:id,name,email', 'creator:id,name,email']);

        $this->broadcaster->broadcastToOthers(new TodoUpdated($todo));

        return response()->json(['todo' => $todo], 201);
    }

    public function update(Request $request, Server $server, Channel $channel, Todo $todo): JsonResponse
    {
        abort_unless($todo->channel_id === $channel->id && $channel->server_id === $server->id, 404);
        Gate::authorize('update', $todo);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'details' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'due_timezone' => ['sometimes', 'string', 'timezone'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assignee_id' => ['sometimes', 'nullable', 'integer', $this->assigneeExistsRule($server)],
            'completed_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $validated = $this->normalizeScheduleValues($validated);
        DB::transaction(function () use ($server, $todo, $validated): void {
            if (array_key_exists('assignee_id', $validated) && $validated['assignee_id'] !== null) {
                $this->lockAssigneeMembership($server, (int) $validated['assignee_id']);
            }

            $this->validateScheduleUpdate($todo, $validated);
            $todo->fill($validated);

            if ($todo->isDirty(['due_at', 'due_timezone'])) {
                $todo->reminded_at = null;
            }

            $todo->save();
        });

        $todo->load(['assignee:id,name,email', 'creator:id,name,email']);

        $this->broadcaster->broadcastToOthers(new TodoUpdated($todo));

        return response()->json(['todo' => $todo]);
    }

    public function toggle(Request $request, Server $server, Channel $channel, Todo $todo): JsonResponse
    {
        abort_unless($todo->channel_id === $channel->id && $channel->server_id === $server->id, 404);
        Gate::authorize('update', $todo);

        $todo->completed_at = $todo->completed_at === null ? Carbon::now() : null;
        $todo->completed_by = $todo->completed_at !== null ? $request->user()?->id : null;
        $todo->save();

        $todo->load(['assignee:id,name,email', 'creator:id,name,email']);

        $this->broadcaster->broadcastToOthers(new TodoUpdated($todo));

        return response()->json(['todo' => $todo]);
    }

    public function destroy(Server $server, Channel $channel, Todo $todo): RedirectResponse
    {
        abort_unless($todo->channel_id === $channel->id && $channel->server_id === $server->id, 404);
        Gate::authorize('delete', $todo);

        $todo->delete();

        return back();
    }

    /** @param array<string, mixed> $validated */
    private function validateScheduleUpdate(Todo $todo, array $validated): void
    {
        $startsAt = $todo->starts_at;
        $dueAt = $todo->due_at;

        if (array_key_exists('starts_at', $validated)) {
            $startsAt = $validated['starts_at'] === null
                ? null
                : Carbon::parse((string) $validated['starts_at']);
        }

        if (array_key_exists('due_at', $validated)) {
            $dueAt = $validated['due_at'] === null
                ? null
                : Carbon::parse((string) $validated['due_at']);
        }

        if ($startsAt !== null && $dueAt !== null && $dueAt->lessThan($startsAt)) {
            throw ValidationException::withMessages([
                'due_at' => [__('The end date and time must be on or after the start date and time.')],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeScheduleValues(array $validated): array
    {
        foreach (['starts_at', 'due_at'] as $attribute) {
            if (array_key_exists($attribute, $validated) && $validated[$attribute] !== null) {
                $validated[$attribute] = Carbon::parse((string) $validated[$attribute])
                    ->utc()
                    ->format('Y-m-d H:i:s');
            }
        }

        return $validated;
    }

    private function assigneeExistsRule(Server $server): Exists
    {
        return Rule::exists('server_user', 'user_id')
            ->where(fn (Builder $query) => $query->where('server_id', $server->id));
    }

    private function lockAssigneeMembership(Server $server, int $assigneeId): void
    {
        $membership = DB::table('server_user')
            ->where('server_id', $server->id)
            ->where('user_id', $assigneeId)
            ->lockForUpdate()
            ->first(['id']);

        if ($membership === null) {
            throw ValidationException::withMessages([
                'assignee_id' => [__('The selected assignee is not a member of this server.')],
            ]);
        }
    }
}
