<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\StoredFile;
use App\Support\MarkdownSearchIndex;
use App\Support\SearchBackendUnavailable;
use App\Support\SearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileController extends Controller
{
    public function store(Request $request, Server $server): JsonResponse
    {
        Gate::authorize('mutateContent', $server);

        $request->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['required', 'file', 'max:51200'],
        ]);

        $uploadedFiles = $request->file('files');
        abort_unless(is_array($uploadedFiles), 422);

        $stored = collect($uploadedFiles)->map(function (UploadedFile $file) use ($server, $request) {
            $originalName = $file->getClientOriginalName();
            $path = $file->store("uploads/{$server->id}/".date('Y/m/d'), ['disk' => 'local']);

            return StoredFile::create([
                'server_id' => $server->id,
                'uploaded_by' => $request->user()?->id,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        });

        return response()->json([
            'files' => $stored->map(fn (StoredFile $file) => [
                'id' => $file->id,
                'path' => $file->path,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size' => $file->size,
                'preview_status' => $file->preview_status,
                'markdown_status' => $file->markdown_status,
                'stream_url' => $file->stream_url,
                'download_url' => $file->download_url,
                'thumbnail_url' => $file->thumbnail_url,
            ]),
        ], 201);
    }

    public function show(Server $server, StoredFile $storedFile): JsonResponse
    {
        abort_unless($storedFile->server_id === $server->id, 404);
        Gate::authorize('view', $server);

        return response()->json(['file' => $storedFile->load('uploader:id,name,email')]);
    }

    public function download(Server $server, StoredFile $storedFile): Response
    {
        abort_unless($storedFile->server_id === $server->id, 404);
        Gate::authorize('view', $server);

        $disk = Storage::disk($storedFile->disk);
        abort_unless($disk->exists($storedFile->path), 404);

        return $disk->download($storedFile->path, $this->safeFilename($storedFile->original_name));
    }

    public function stream(Server $server, StoredFile $storedFile): StreamedResponse
    {
        abort_unless($storedFile->server_id === $server->id, 404);
        Gate::authorize('view', $server);

        $disk = Storage::disk($storedFile->disk);
        abort_unless($disk->exists($storedFile->path), 404);

        $stream = $disk->readStream($storedFile->path);
        abort_unless(is_resource($stream), 404);

        $response = new StreamedResponse(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        });
        $filename = $this->safeFilename($storedFile->original_name);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition('inline', $filename, Str::ascii($filename)),
        );
        $response->headers->set('Content-Type', $storedFile->mime_type ?? 'application/octet-stream');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function search(Request $request, Server $server): JsonResponse
    {
        Gate::authorize('view', $server);

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'channel_id' => ['nullable', 'integer'],
        ]);
        $channelId = isset($validated['channel_id']) ? (int) $validated['channel_id'] : null;

        if ($channelId !== null) {
            $channel = $server->channels()->whereKey($channelId)->firstOrFail();
            Gate::authorize('view', $channel);
        }

        try {
            SearchQuery::from($validated['q']);

            $results = app(MarkdownSearchIndex::class)
                ->search($server->id, $validated['q'], channelId: $channelId)
                ->map(fn (stdClass $row) => [
                    'id' => $row->id,
                    'original_name' => $row->original_name,
                    'mime_type' => $row->mime_type,
                    'size' => $row->size,
                    'preview_status' => $row->preview_status,
                    'created_at' => $row->created_at,
                    'snippet' => $row->snippet,
                    'snippet_segments' => $row->snippet_segments ?? [],
                    'stream_url' => route('servers.files.stream', [$server->id, $row->id]),
                    'download_url' => route('servers.files.download', [$server->id, $row->id]),
                    'thumbnail_url' => $row->preview_status === 'ready'
                        ? route('servers.files.thumbnail', [$server->id, $row->id])
                        : null,
                ])
                ->values();
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['q' => $exception->getMessage()]);
        } catch (SearchBackendUnavailable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Search is temporarily unavailable.',
                'results' => [],
            ], 503);
        }

        return response()->json(['results' => $results]);
    }

    public function preview(Server $server, StoredFile $storedFile): JsonResponse
    {
        abort_unless($storedFile->server_id === $server->id, 404);
        Gate::authorize('view', $server);

        $previewPath = $storedFile->preview_status === 'ready' ? $storedFile->preview_path : null;

        return response()->json([
            'preview_url' => $previewPath !== null
                ? route('servers.files.thumbnail', [$server, $storedFile])
                : null,
            'stream_url' => route('servers.files.stream', [$server, $storedFile]),
            'download_url' => route('servers.files.download', [$server, $storedFile]),
        ]);
    }

    public function thumbnail(Server $server, StoredFile $storedFile): Response
    {
        abort_unless($storedFile->server_id === $server->id, 404);
        Gate::authorize('view', $server);

        abort_unless($storedFile->preview_status === 'ready' && $storedFile->preview_path !== null, 404);

        $disk = Storage::disk($storedFile->disk);
        abort_unless($disk->exists($storedFile->preview_path), 404);

        $contentType = match (strtolower(pathinfo($storedFile->preview_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/webp',
        };

        return $disk->response($storedFile->preview_path, null, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Server $server, StoredFile $storedFile): JsonResponse
    {
        abort_unless($storedFile->server_id === $server->id, 404);
        Gate::authorize('mutateContent', $server);

        $markedForDeletion = StoredFile::query()
            ->whereKey($storedFile->id)
            ->update([
                'preview_status' => 'deleting',
                'markdown_status' => 'deleting',
            ]);

        if ($markedForDeletion !== 1) {
            return response()->json(['ok' => true]);
        }

        $storedFile = $storedFile->fresh();

        if ($storedFile === null) {
            return response()->json(['ok' => true]);
        }

        $disk = Storage::disk($storedFile->disk);

        if ($disk->exists($storedFile->path) && ! $disk->delete($storedFile->path)) {
            throw new RuntimeException('Stored file deletion failed.');
        }

        $storedFile->delete();

        return response()->json(['ok' => true]);
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^\p{L}\p{N}\p{M}._()\- ]+/u', '_', $filename) ?? '';
        $filename = trim($filename, " .\t\n\r\0\x0B");

        return $filename !== '' ? $filename : 'document';
    }
}
