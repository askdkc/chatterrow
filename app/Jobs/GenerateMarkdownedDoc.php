<?php

namespace App\Jobs;

use App\Models\StoredFile;
use App\Support\MarkdownedDocGenerator;
use App\Support\MarkdownSearchIndex;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateMarkdownedDoc implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

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
                'markdown-'.$this->storedFileId.'-'.substr(hash('sha256', $this->sourcePath), 0, 16),
                releaseAfter: 15,
                expiresAfter: $this->timeout + 60,
            ),
        ];
    }

    public function handle(MarkdownedDocGenerator $generator): void
    {
        $claimed = StoredFile::query()
            ->whereKey($this->storedFileId)
            ->where('path', $this->sourcePath)
            ->whereIn('markdown_status', ['pending', 'processing'])
            ->update([
                'markdown_status' => 'processing',
                'markdown_path' => null,
            ]);

        if ($claimed !== 1) {
            return;
        }

        $storedFile = StoredFile::query()
            ->whereKey($this->storedFileId)
            ->where('path', $this->sourcePath)
            ->where('markdown_status', 'processing')
            ->first();

        if ($storedFile === null) {
            return;
        }

        try {
            $markdownPath = $generator->generate($storedFile);

            $published = StoredFile::query()
                ->whereKey($this->storedFileId)
                ->where('path', $this->sourcePath)
                ->where('markdown_status', 'processing')
                ->update([
                    'markdown_path' => $markdownPath,
                    'markdown_status' => 'ready',
                ]);

            if ($published !== 1) {
                Storage::disk('markdowned')->delete($markdownPath);
                app(MarkdownSearchIndex::class)->remove($this->storedFileId);
            }
        } catch (Throwable $exception) {
            Log::warning('Stored file markdown conversion failed.', [
                'stored_file_id' => $this->storedFileId,
                'source_path' => $this->sourcePath,
                'exception' => $exception::class,
            ]);

            $failed = StoredFile::query()
                ->whereKey($this->storedFileId)
                ->where('path', $this->sourcePath)
                ->where('markdown_status', 'processing')
                ->update([
                    'markdown_path' => null,
                    'markdown_status' => 'failed',
                ]);

            if ($failed === 1) {
                Storage::disk('markdowned')->delete(
                    MarkdownedDocGenerator::markdownPath($this->storedFileId, $this->sourcePath),
                );
                app(MarkdownSearchIndex::class)->remove($this->storedFileId);
            }
        }
    }
}
