<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use App\Support\OnlyOfficeDocumentVersion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class OnlyOfficeFileController extends Controller
{
    public function __construct(private OnlyOfficeDocumentVersion $documentVersion) {}

    public function __invoke(Request $request, StoredFile $storedFile): Response
    {
        abort_unless((bool) config('onlyoffice.enabled', false), 404);

        $requestedVersion = (string) $request->query('version', '');

        if ($requestedVersion === '' || ! hash_equals($this->documentVersion->key($storedFile), $requestedVersion)) {
            return response('The requested file version is no longer available.', 410);
        }

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
        $fallbackFilename = $this->fallbackFilename($filename);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition('inline', $filename, $fallbackFilename),
        );
        $response->headers->set('Content-Type', $this->mimeType($disk, $storedFile));
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        try {
            $size = $disk->size($storedFile->path);
        } catch (Throwable) {
            $size = $storedFile->size > 0 ? $storedFile->size : null;
        }

        if (is_int($size) && $size >= 0) {
            $response->headers->set('Content-Length', (string) $size);
        }

        return $response;
    }

    private function mimeType(FilesystemAdapter $disk, StoredFile $storedFile): string
    {
        if (is_string($storedFile->mime_type) && trim($storedFile->mime_type) !== '') {
            return $storedFile->mime_type;
        }

        $mimeType = $disk->mimeType($storedFile->path);

        return is_string($mimeType) && $mimeType !== '' ? $mimeType : 'application/octet-stream';
    }

    private function fallbackFilename(string $filename): string
    {
        $fallback = Str::ascii($filename);
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fallback) ?? '';
        $fallback = trim($fallback, '._-');

        return $fallback !== '' ? $fallback : 'document';
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^\p{L}\p{N}\p{M}._()\- ]+/u', '_', $filename) ?? '';
        $filename = trim($filename, " .\t\n\r\0\x0B");

        return $filename !== '' ? $filename : 'document';
    }
}
