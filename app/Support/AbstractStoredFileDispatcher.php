<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AbstractStoredFileDispatcher
{
    public function dispatchAfterCommit(int $storedFileId, string $sourcePath): void
    {
        DB::afterCommit(function () use ($sourcePath, $storedFileId): void {
            try {
                $this->dispatchJob($storedFileId, $sourcePath);
            } catch (Throwable $exception) {
                StoredFile::query()
                    ->whereKey($storedFileId)
                    ->where('path', $sourcePath)
                    ->whereIn($this->statusColumn(), ['pending', 'processing'])
                    ->update([
                        $this->pathColumn() => null,
                        $this->statusColumn() => 'failed',
                    ]);

                Log::error($this->failureLogMessage(), $this->failureLogContext(
                    $storedFileId,
                    $sourcePath,
                    $exception,
                ));
            }
        });
    }

    abstract protected function dispatchJob(int $storedFileId, string $sourcePath): void;

    abstract protected function statusColumn(): string;

    abstract protected function pathColumn(): string;

    abstract protected function failureLogMessage(): string;

    /** @return array<string, mixed> */
    protected function failureLogContext(int $storedFileId, string $sourcePath, Throwable $exception): array
    {
        return [
            'stored_file_id' => $storedFileId,
            'source_path' => $sourcePath,
            'exception' => $exception,
        ];
    }
}
