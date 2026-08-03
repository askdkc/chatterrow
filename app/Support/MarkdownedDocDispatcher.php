<?php

namespace App\Support;

use App\Jobs\GenerateMarkdownedDoc;

final class MarkdownedDocDispatcher extends AbstractStoredFileDispatcher
{
    protected function dispatchJob(int $storedFileId, string $sourcePath): void
    {
        GenerateMarkdownedDoc::dispatch($storedFileId, $sourcePath);
    }

    protected function statusColumn(): string
    {
        return 'markdown_status';
    }

    protected function pathColumn(): string
    {
        return 'markdown_path';
    }

    protected function failureLogMessage(): string
    {
        return 'Stored file markdown could not be queued.';
    }
}
