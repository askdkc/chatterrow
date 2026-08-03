<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ChannelController extends Controller
{
    public function store(Request $request, Server $server): JsonResponse
    {
        Gate::authorize('create', [Channel::class, $server]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('channels', 'name')->where('server_id', $server->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);

        $channel = $server->channels()->create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['channel' => $channel], 201);
    }

    public function update(Request $request, Server $server, Channel $channel): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('update', $channel);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('channels', 'name')->where('server_id', $server->id)->ignore($channel->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);

        Validator::make([
            'starts_on' => array_key_exists('starts_on', $request->all())
                ? $request->input('starts_on')
                : $channel->starts_on?->toDateString(),
            'ends_on' => array_key_exists('ends_on', $request->all())
                ? $request->input('ends_on')
                : $channel->ends_on?->toDateString(),
        ], [
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ])->validate();

        $channel->fill($validated);

        if ($channel->isDirty('ends_on')) {
            $channel->reminded_at = null;
        }

        $channel->save();

        return response()->json(['channel' => $channel->fresh()]);
    }

    public function destroy(Server $server, Channel $channel): JsonResponse
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('delete', $channel);

        $channel->delete();

        return response()->json(['ok' => true]);
    }
}
