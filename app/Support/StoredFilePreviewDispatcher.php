<?php

namespace App\Support;

use App\Jobs\GenerateStoredFilePreview;
use Throwable;

final class StoredFilePreviewDispatcher extends AbstractStoredFileDispatcher
{
    protected function dispatchJob(int $storedFileId, string $sourcePath): void
    {
        GenerateStoredFilePreview::dispatch($storedFileId, $sourcePath);
    }

    protected function statusColumn(): string
    {
        return 'preview_status';
    }

    protected function pathColumn(): string
    {
        return 'preview_path';
    }

    protected function failureLogMessage(): string
    {
        return 'Stored file preview could not be queued.';
    }

    /** @return array<string, mixed> */
    protected function failureLogContext(int $storedFileId, string $sourcePath, Throwable $exception): array
    {
        return [
            'stored_file_id' => $storedFileId,
            'source_hash' => StoredFilePreviewGenerator::sourceHash($sourcePath),
            'exception' => $exception,
        ];
    }
}
