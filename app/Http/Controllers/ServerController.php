<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function index(): Response
    {
        $servers = Server::query()
            ->visibleTo(auth()->user())
            ->withCount(['channels', 'members'])
            ->orderBy('name')
            ->get();

        return Inertia::render('servers/Index', [
            'servers' => $servers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Server::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $server = Server::create([
            ...$validated,
            'created_by' => $user->id,
        ]);

        $server->members()->attach($user->id);

        return response()->json(['server' => $server], 201);
    }

    public function show(Server $server): Response
    {
        Gate::authorize('view', $server);

        $server->load([
            'channels' => fn ($query) => $query->orderBy('name'),
            'members:id,name,email',
        ]);

        return Inertia::render('servers/Show', [
            'server' => $server,
            'members' => $server->members,
        ]);
    }

    public function update(Request $request, Server $server): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $server);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $server->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['server' => $server->fresh()]);
        }

        return back();
    }

    public function destroy(Server $server): RedirectResponse
    {
        Gate::authorize('delete', $server);

        $server->delete();

        return redirect()->route('servers.index');
    }

    public function storeMember(Request $request, Server $server): JsonResponse
    {
        Gate::authorize('manageMembers', $server);

        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
        ]);

        $user = User::where('email', $validated['email'])->firstOrFail();

        if ($server->members()->whereKey($user->id)->doesntExist()) {
            $server->members()->attach($user->id);
        }

        return response()->json(['user' => $user->only(['id', 'name', 'email'])], 201);
    }

    public function destroyMember(Server $server, User $user): JsonResponse
    {
        Gate::authorize('manageMembers', $server);

        if ($server->created_by !== $user->id) {
            $server->members()->detach($user->id);
        }

        return response()->json(['ok' => true]);
    }
}
