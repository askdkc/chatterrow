<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Support\StoredFilePreviewGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateStoredFilePreview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 25;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(public int $storedFileId, public string $sourcePath)
    {
        $this->afterCommit = true;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping(
                "{$this->storedFileId}-".StoredFilePreviewGenerator::sourceHash($this->sourcePath),
                releaseAfter: 15,
                expiresAfter: $this->timeout + 60,
            ),
        ];
    }

    public function handle(StoredFilePreviewGenerator $generator): void
    {
        $storedFile = StoredFile::query()->find($this->storedFileId);
        $disk = Storage::disk($storedFile->disk ?? config('filesystems.default', 'local'));

        $claimed = StoredFile::query()
            ->whereKey($this->storedFileId)
            ->where('path', $this->sourcePath)
            ->whereIn('preview_status', ['pending', 'processing'])
            ->update([
                'preview_status' => 'processing',
                'preview_path' => null,
            ]);

        if ($claimed !== 1) {
            $this->cleanupIfSourceIsStale($disk);

            return;
        }

        $storedFile = StoredFile::query()
            ->whereKey($this->storedFileId)
            ->where('path', $this->sourcePath)
            ->where('preview_status', 'processing')
            ->first();

        if ($storedFile === null) {
            $this->cleanupIfSourceIsStale($disk);

            return;
        }

        try {
            $previewPath = $generator->generate($storedFile);

            $published = StoredFile::query()
                ->whereKey($this->storedFileId)
                ->where('path', $this->sourcePath)
                ->where('preview_status', 'processing')
                ->update([
                    'preview_path' => $previewPath,
                    'preview_status' => 'ready',
                ]);

            if ($published !== 1) {
                $this->cleanupIfSourceIsStale($disk);

                return;
            }
        } catch (Throwable $exception) {
            Log::warning('Stored file preview generation failed.', [
                'stored_file_id' => $this->storedFileId,
                'source_path' => $this->sourcePath,
                'exception' => $exception::class,
            ]);

            $failed = StoredFile::query()
                ->whereKey($this->storedFileId)
                ->where('path', $this->sourcePath)
                ->where('preview_status', 'processing')
                ->update([
                    'preview_path' => null,
                    'preview_status' => 'failed',
                ]);

            if ($failed === 1) {
                StoredFilePreviewGenerator::cleanup($disk, $this->storedFileId, $this->sourcePath);
            } else {
                $this->cleanupIfSourceIsStale($disk);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $storedFile = StoredFile::query()->find($this->storedFileId);
        $disk = Storage::disk($storedFile->disk ?? config('filesystems.default', 'local'));
        $failed = StoredFile::query()
            ->whereKey($this->storedFileId)
            ->where('path', $this->sourcePath)
            ->where('preview_status', 'processing')
            ->update([
                'preview_path' => null,
                'preview_status' => 'failed',
            ]);

        if ($failed === 1) {
            StoredFilePreviewGenerator::cleanup($disk, $this->storedFileId, $this->sourcePath);
        } else {
            $this->cleanupIfSourceIsStale($disk);
        }
    }

    private function cleanupIfSourceIsStale(FilesystemAdapter $disk): void
    {
        $storedFile = StoredFile::query()
            ->select(['id', 'path', 'preview_status'])
            ->whereKey($this->storedFileId)
            ->first();

        if ($storedFile !== null
            && $storedFile->path === $this->sourcePath
            && $storedFile->preview_status !== 'deleting') {
            return;
        }

        StoredFilePreviewGenerator::cleanup($disk, $this->storedFileId, $this->sourcePath);
    }
}
