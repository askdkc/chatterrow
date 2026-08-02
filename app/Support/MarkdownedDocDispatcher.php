<?php

namespace App\Support;

use App\Jobs\GenerateMarkdownedDoc;
use App\Models\StoredFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MarkdownedDocDispatcher
{
    public function dispatchAfterCommit(int $storedFileId, string $sourcePath): void
    {
        DB::afterCommit(static function () use ($sourcePath, $storedFileId): void {
            try {
                GenerateMarkdownedDoc::dispatch($storedFileId, $sourcePath);
            } catch (Throwable $exception) {
                StoredFile::query()
                    ->whereKey($storedFileId)
                    ->where('path', $sourcePath)
                    ->whereIn('markdown_status', ['pending', 'processing'])
                    ->update([
                        'markdown_path' => null,
                        'markdown_status' => 'failed',
                    ]);

                Log::error('Stored file markdown could not be queued.', [
                    'stored_file_id' => $storedFileId,
                    'source_path' => $sourcePath,
                    'exception' => $exception,
                ]);
            }
        });
    }
}
