<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChatPageController extends Controller
{
    public function __invoke(Server $server, Channel $channel): Response
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $server->load([
            'channels' => fn ($query) => $query->orderBy('name'),
            'members:id,name,email',
        ]);

        $messages = Message::query()
            ->with(['user:id,name,email', 'attachments'])
            ->where('channel_id', $channel->id)
            ->whereNull('parent_id')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return Inertia::render('chat/Chat', [
            'server' => $server,
            'channel' => $channel->load('creator:id,name,email'),
            'initialMessages' => $messages,
            'members' => $server->members,
        ]);
    }
}
