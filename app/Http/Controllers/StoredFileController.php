<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\StoredFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoredFileController extends Controller
{
    public function store(Request $request, Server $server): JsonResponse
    {
        Gate::authorize('view', $server);

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

        return $disk->response($storedFile->preview_path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function destroy(Server $server, StoredFile $storedFile): JsonResponse
    {
        abort_unless($storedFile->server_id === $server->id, 404);
        Gate::authorize('view', $server);

        Storage::disk($storedFile->disk)->delete($storedFile->path);
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
