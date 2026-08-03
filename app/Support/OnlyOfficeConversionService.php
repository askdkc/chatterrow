<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class OnlyOfficeConversionService
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(
        private OnlyOfficeConfigService $configService,
        private OnlyOfficeDocumentTypeResolver $documentTypeResolver,
        private OnlyOfficeDocumentVersion $documentVersion,
        private OnlyOfficeTokenService $tokenService,
    ) {}

    public function thumbnail(StoredFile $storedFile): string
    {
        $width = min(max((int) config('onlyoffice.thumbnail_width', 640), 100), 2000);
        $height = min(max((int) config('onlyoffice.thumbnail_height', 360), 100), 2000);

        return $this->convert($storedFile, 'png', [
            'thumbnail' => [
                'aspect' => 1,
                'first' => true,
                'height' => $height,
                'width' => $width,
            ],
        ]);
    }

    public function document(StoredFile $storedFile, string $outputType): string
    {
        if (! in_array($outputType, ['docx', 'xlsx', 'pptx'], true)) {
            throw new RuntimeException('The requested ONLYOFFICE document output type is not supported.');
        }

        return $this->convert($storedFile, $outputType);
    }

    /** @param array<string, mixed> $options */
    private function convert(StoredFile $storedFile, string $outputType, array $options = []): string
    {
        if (! $this->configService->isEnabledAndConfigured()) {
            throw new RuntimeException('ONLYOFFICE conversion is not configured.');
        }

        $resolved = $this->documentTypeResolver->resolve($storedFile);

        if ($resolved === null) {
            throw new RuntimeException('The file type is not supported by ONLYOFFICE.');
        }

        $key = $this->documentVersion->key($storedFile);
        $payload = [
            'async' => false,
            'filetype' => $resolved['fileType'],
            'key' => $key,
            'outputtype' => $outputType,
            'title' => $this->safeTitle($storedFile->original_name),
            'url' => $this->configService->sourceUrl($storedFile),
            ...$options,
        ];
        $endpoint = rtrim((string) config('onlyoffice.document_server_url'), '/')
            .'/converter?shardkey='.rawurlencode($key);

        $response = Http::acceptJson()
            ->asJson()
            ->withoutRedirecting()
            ->connectTimeout(5)
            ->timeout($this->requestTimeout())
            ->post($endpoint, ['token' => $this->tokenService->encode($payload)]);

        if (! $response->successful()) {
            throw new RuntimeException("ONLYOFFICE conversion request failed: {$response->status()}.");
        }

        $result = $response->json();

        if (! is_array($result)) {
            throw new RuntimeException('ONLYOFFICE returned an invalid conversion response.');
        }

        $error = $result['error'] ?? null;

        if ($error !== null && (int) $error !== 0) {
            throw new RuntimeException("ONLYOFFICE conversion failed with error {$error}.");
        }

        $fileUrl = $result['fileUrl'] ?? null;

        if (! $this->isTrue($result['endConvert'] ?? false) || ! is_string($fileUrl) || trim($fileUrl) === '') {
            throw new RuntimeException('ONLYOFFICE did not finish the conversion.');
        }

        $maxBytes = $this->maxBytes();
        $converted = Http::connectTimeout(5)
            ->withoutRedirecting()
            ->timeout($this->downloadTimeout())
            ->withOptions([
                'on_headers' => function (ResponseInterface $response) use ($maxBytes): void {
                    $length = $response->getHeaderLine('Content-Length');

                    if ($length !== '' && ctype_digit($length) && (float) $length > $maxBytes) {
                        throw new RuntimeException('The ONLYOFFICE conversion result exceeds the size limit.');
                    }
                },
                'progress' => function (
                    int $downloadTotal,
                    int $downloadedBytes,
                    int $uploadTotal,
                    int $uploadedBytes,
                ) use ($maxBytes): void {
                    if ($downloadTotal > $maxBytes || $downloadedBytes > $maxBytes) {
                        throw new RuntimeException('The ONLYOFFICE conversion result exceeds the size limit.');
                    }
                },
            ])
            ->get($this->resultUrl($fileUrl));

        if (! $converted->successful()) {
            throw new RuntimeException("The ONLYOFFICE conversion result could not be downloaded: {$converted->status()}.");
        }

        return $this->validatedBody($outputType, $converted, $maxBytes);
    }

    private function requestTimeout(): int
    {
        return max((int) config('onlyoffice.conversion_timeout', 120), 1);
    }

    private function downloadTimeout(): int
    {
        return max((int) config('onlyoffice.conversion_download_timeout', 60), 1);
    }

    private function maxBytes(): int
    {
        return max((int) config('onlyoffice.conversion_max_bytes', 104857600), 1);
    }

    private function validatedBody(string $outputType, Response $response, int $maxBytes): string
    {
        if ($response->header('Content-Length') !== '') {
            $declaredLength = (int) $response->header('Content-Length');

            if ($declaredLength > $maxBytes) {
                throw new RuntimeException('The ONLYOFFICE conversion result exceeds the size limit.');
            }
        }

        $body = $response->body();

        if ($body === '') {
            throw new RuntimeException('The ONLYOFFICE conversion result is empty.');
        }

        if (strlen($body) > $maxBytes) {
            throw new RuntimeException('The ONLYOFFICE conversion result exceeds the size limit.');
        }

        if ($outputType === 'png' && ! str_starts_with($body, self::PNG_SIGNATURE)) {
            throw new RuntimeException('ONLYOFFICE returned an unexpected PNG result.');
        }

        if (in_array($outputType, ['docx', 'xlsx', 'pptx'], true) && ! str_starts_with($body, "PK\x03\x04")) {
            throw new RuntimeException('ONLYOFFICE returned an unexpected Office document result.');
        }

        return $body;
    }

    private function resultUrl(string $fileUrl): string
    {
        $baseUrl = rtrim((string) config('onlyoffice.document_server_url'), '/');
        $base = parse_url($baseUrl);
        $result = parse_url($fileUrl);

        if (! is_array($base) || ! is_array($result)) {
            throw new RuntimeException('ONLYOFFICE returned an invalid result URL.');
        }

        if (! isset($result['scheme'], $result['host'])) {
            return $baseUrl.'/'.ltrim($fileUrl, '/');
        }

        $basePort = $base['port'] ?? null;
        $resultPort = $result['port'] ?? null;

        if (
            Str::lower((string) ($base['scheme'] ?? '')) !== Str::lower($result['scheme'])
            || Str::lower((string) ($base['host'] ?? '')) !== Str::lower($result['host'])
            || $basePort !== $resultPort
        ) {
            throw new RuntimeException('ONLYOFFICE returned a result URL for an unexpected host.');
        }

        return $fileUrl;
    }

    private function safeTitle(string $filename): string
    {
        $title = basename($filename);
        $title = preg_replace('/[^\p{L}\p{N}\p{M}._()\- ]+/u', '_', $title) ?? '';
        $title = trim($title, " .\t\n\r\0\x0B");

        return Str::limit($title !== '' ? $title : 'document', 128, '');
    }

    private function isTrue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return Str::lower(trim($value)) === 'true';
        }

        return false;
    }
}
