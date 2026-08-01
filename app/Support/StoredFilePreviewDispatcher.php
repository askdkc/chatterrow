<?php

namespace App\Support;

use App\Jobs\GenerateStoredFilePreview;
use App\Models\StoredFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class StoredFilePreviewDispatcher
{
    public function dispatchAfterCommit(int $storedFileId, string $sourcePath): void
    {
        DB::afterCommit(static function () use ($sourcePath, $storedFileId): void {
            try {
                GenerateStoredFilePreview::dispatch($storedFileId, $sourcePath);
            } catch (Throwable $exception) {
                StoredFile::query()
                    ->whereKey($storedFileId)
                    ->where('path', $sourcePath)
                    ->whereIn('preview_status', ['pending', 'processing'])
                    ->update([
                        'preview_path' => null,
                        'preview_status' => 'failed',
                    ]);

                Log::error('Stored file preview could not be queued.', [
                    'stored_file_id' => $storedFileId,
                    'source_hash' => StoredFilePreviewGenerator::sourceHash($sourcePath),
                    'exception' => $exception,
                ]);
            }
        });
    }
}
