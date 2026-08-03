<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMarkdownedDoc;
use App\Models\StoredFile;
use App\Support\MarkdownedDocGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarkdownFiles extends Command
{
    protected $signature = 'files:markdown
        {--server= : Limit to a server id}
        {--stale-after= : Requeue pending or processing files older than this many seconds}';

    protected $description = 'Queue Markdown conversion for supported PDF and Office files';

    public function handle(): int
    {
        $staleAfter = max(
            (int) ($this->option('stale-after') ?? config('services.markitdown.stale_after', 900)),
            0,
        );
        $staleBefore = now()->subSeconds($staleAfter);
        $query = StoredFile::query()
            ->whereNull('markdown_path')
            ->where(function ($query) use ($staleBefore): void {
                $query
                    ->whereNull('markdown_status')
                    ->orWhere('markdown_status', 'failed')
                    ->orWhere(function ($query) use ($staleBefore): void {
                        $query
                            ->whereIn('markdown_status', ['pending', 'processing'])
                            ->where('updated_at', '<', $staleBefore);
                    });
            });

        if ($serverId = $this->option('server')) {
            $query->where('server_id', (int) $serverId);
        }

        $files = $query->get(['id', 'path', 'original_name']);
        $queued = 0;

        foreach ($files as $file) {
            if (! MarkdownedDocGenerator::supports($file->original_name)) {
                continue;
            }

            $claimed = StoredFile::query()
                ->whereKey($file->id)
                ->whereNull('markdown_path')
                ->where(function ($query) use ($staleBefore): void {
                    $query
                        ->whereNull('markdown_status')
                        ->orWhere('markdown_status', 'failed')
                        ->orWhere(function ($query) use ($staleBefore): void {
                            $query
                                ->whereIn('markdown_status', ['pending', 'processing'])
                                ->where('updated_at', '<', $staleBefore);
                        });
                })
                ->update([
                    'markdown_status' => 'pending',
                    'markdown_path' => null,
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                GenerateMarkdownedDoc::dispatch($file->id, $file->path);
                $queued++;
                $this->line("Queued #{$file->id} {$file->original_name}");
            } catch (Throwable $exception) {
                StoredFile::query()
                    ->whereKey($file->id)
                    ->where('path', $file->path)
                    ->where('markdown_status', 'pending')
                    ->update(['markdown_status' => 'failed', 'markdown_path' => null]);

                Log::error('Stored file markdown recovery dispatch failed.', [
                    'stored_file_id' => $file->id,
                    'source_path' => $file->path,
                    'exception' => $exception,
                ]);
            }
        }

        $this->info("Queued {$queued} files for markdown conversion.");

        return self::SUCCESS;
    }
}
