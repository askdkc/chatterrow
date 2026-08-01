<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class OnlyOfficeConfigService
{
    public function __construct(
        private OnlyOfficeDocumentTypeResolver $documentTypeResolver,
        private OnlyOfficeDocumentVersion $documentVersion,
        private OnlyOfficeTokenService $tokenService,
    ) {}

    public function isConfigured(): bool
    {
        return $this->isHttpUrl(config('onlyoffice.document_server_url'))
            && $this->isHttpUrl(config('onlyoffice.public_url'))
            && $this->isHttpUrl(config('onlyoffice.internal_url'))
            && Str::length(trim((string) config('onlyoffice.jwt_secret', ''))) >= 32;
    }

    public function isDocumentServerAvailable(): bool
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(3)
                ->get(rtrim((string) config('onlyoffice.document_server_url'), '/').'/healthcheck');

            return $response->successful()
                && in_array(Str::lower(trim($response->body())), ['true', 'ok'], true);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    public function make(StoredFile $storedFile): array
    {
        $resolved = $this->documentTypeResolver->resolve($storedFile);
        $version = $this->documentVersion->key($storedFile);
        $fileType = $resolved['fileType'];

        $config = [
            'type' => 'embedded',
            'documentType' => $resolved['documentType'],
            'width' => '100%',
            'height' => '100%',
            'document' => [
                'fileType' => $fileType,
                'key' => $version,
                'title' => $this->safeTitle($storedFile->original_name, $fileType),
                'url' => $this->downloadUrl($storedFile, $version),
                'permissions' => [
                    'edit' => false,
                    'comment' => false,
                    'review' => false,
                    'fillForms' => false,
                    'chat' => false,
                    'protect' => false,
                    'modifyContentControl' => false,
                    'download' => (bool) config('onlyoffice.allow_download', false),
                    'print' => (bool) config('onlyoffice.allow_print', false),
                    'copy' => (bool) config('onlyoffice.allow_copy', true),
                ],
            ],
            'editorConfig' => [
                'mode' => 'view',
                'lang' => 'ja',
                'region' => 'ja-JP',
                'user' => [
                    'id' => hash('sha256', "chatter-onlyoffice-user-v1\0".(string) (auth()->id() ?? 'anonymous')),
                    'name' => Str::limit((string) (auth()->user()->name ?? 'Viewer'), 128, ''),
                ],
                'customization' => [
                    'integrationMode' => 'embed',
                    'macros' => false,
                    'macrosMode' => 'disable',
                    'plugins' => false,
                    'feedback' => false,
                    'help' => false,
                    'comments' => false,
                    'forcesave' => false,
                ],
            ],
        ];

        return [...$config, 'token' => $this->tokenService->encode($config)];
    }

    private function downloadUrl(StoredFile $storedFile, string $version): string
    {
        $relativeUrl = URL::temporarySignedRoute(
            'onlyoffice.files.download',
            $this->downloadTtl(),
            ['stored_file' => $storedFile->getKey(), 'version' => $version],
            absolute: false,
        );

        return rtrim((string) config('onlyoffice.internal_url'), '/').'/'.ltrim($relativeUrl, '/');
    }

    private function downloadTtl(): int
    {
        return min(max((int) config('onlyoffice.download_ttl', 300), 1), 3600);
    }

    private function safeTitle(?string $originalName, string $fileType): string
    {
        $title = strip_tags(basename((string) $originalName));
        $title = preg_replace('/[^\p{L}\p{N}\p{M}._()\- ]+/u', '_', $title) ?? '';
        $title = trim($title, " .\t\n\r\0\x0B");

        return Str::limit($title !== '' ? $title : "document.{$fileType}", 128, '');
    }

    private function isHttpUrl(mixed $url): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(Str::lower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && trim((string) ($parts['host'] ?? '')) !== '';
    }
}
