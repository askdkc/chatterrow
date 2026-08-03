<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMarkdownedDoc;
use App\Models\StoredFile;
use App\Support\MarkdownedDocGenerator;
use App\Support\OnlyOfficeConfigService;
use Illuminate\Console\Command;

class MarkdownFiles extends Command
{
    protected $signature = 'files:markdown {--server= : Limit to a server id}';

    protected $description = 'Queue markdown conversion for stored Office/PDF files that lack one';

    public function handle(): int
    {
        $onlyOfficeReady = app(OnlyOfficeConfigService::class)->isEnabledAndConfigured();
        $query = StoredFile::query()
            ->whereNull('markdown_path')
            ->where(function ($query): void {
                $query
                    ->whereNull('markdown_status')
                    ->orWhere('markdown_status', 'failed');
            });

        if ($serverId = $this->option('server')) {
            $query->where('server_id', (int) $serverId);
        }

        $files = $query->get(['id', 'path', 'original_name']);
        $queued = 0;

        foreach ($files as $file) {
            if (! MarkdownedDocGenerator::supports($file->original_name)
                || (MarkdownedDocGenerator::requiresOnlyOffice($file->original_name) && ! $onlyOfficeReady)) {
                continue;
            }

            StoredFile::query()
                ->whereKey($file->id)
                ->update(['markdown_status' => 'pending', 'markdown_path' => null]);

            GenerateMarkdownedDoc::dispatch($file->id, $file->path);
            $queued++;
            $this->line("Queued #{$file->id} {$file->original_name}");
        }

        $this->info("Queued {$queued} files for markdown conversion.");

        return self::SUCCESS;
    }
}
