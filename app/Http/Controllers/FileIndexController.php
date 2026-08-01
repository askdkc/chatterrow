<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Server;
use App\Models\StoredFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FileIndexController extends Controller
{
    /** Server-wide file viewer. */
    public function index(Server $server): Response
    {
        Gate::authorize('view', $server);

        $files = StoredFile::query()
            ->with(['uploader:id,name,email'])
            ->where('server_id', $server->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StoredFile $file) => $this->present($file));

        return Inertia::render('servers/Files', [
            'server' => $server,
            'files' => $files,
        ]);
    }

    /** Channel-scoped file viewer. */
    public function channelIndex(Server $server, Channel $channel): Response
    {
        abort_unless($channel->server_id === $server->id, 404);
        Gate::authorize('view', $channel);

        $messageIds = $channel->messages()->pluck('id');

        $files = StoredFile::query()
            ->with(['uploader:id,name,email'])
            ->where('server_id', $server->id)
            ->where(function ($query) use ($messageIds, $channel): void {
                $query
                    ->where(fn ($q) => $q->where('attachable_type', 'App\Models\Message')->whereIn('attachable_id', $messageIds))
                    ->orWhere(fn ($q) => $q->where('attachable_type', 'App\Models\Todo')->whereIn('attachable_id', $channel->todos()->pluck('id')));
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StoredFile $file) => $this->present($file));

        return Inertia::render('servers/Files', [
            'server' => $server,
            'channel' => $channel,
            'files' => $files,
        ]);
    }

    /** @return array<string, mixed> */
    private function present(StoredFile $file): array
    {
        return [
            'id' => $file->id,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'preview_status' => $file->preview_status,
            'created_at' => $file->created_at?->toISOString(),
            'uploader' => $file->uploader,
            'stream_url' => route('servers.files.stream', [$file->server_id, $file]),
            'download_url' => route('servers.files.download', [$file->server_id, $file]),
            'thumbnail_url' => $file->preview_status === 'ready' && $file->preview_path !== null
                ? route('servers.files.thumbnail', [$file->server_id, $file])
                : null,
        ];
    }
}
