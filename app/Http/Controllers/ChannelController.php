<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChannelController extends Controller
{
    public function store(Request $request, Server $server): RedirectResponse
    {
        Gate::authorize('create', [Channel::class, $server]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('channels', 'name')->where('server_id', $server->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $channel = $server->channels()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('servers.channels.show', [$server, $channel]);
    }

    public function update(Request $request, Server $server, Channel $channel): RedirectResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('update', $channel);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('channels', 'name')->where('server_id', $server->id)->ignore($channel->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        $channel->update($validated);

        return back();
    }

    public function destroy(Server $server, Channel $channel): RedirectResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('delete', $channel);

        $channel->delete();

        return redirect()->route('servers.show', $server);
    }
}
