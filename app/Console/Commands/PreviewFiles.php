<?php

namespace App\Console\Commands;

use App\Jobs\GenerateStoredFilePreview;
use App\Models\StoredFile;
use App\Support\OnlyOfficeConfigService;
use App\Support\StoredFilePreviewGenerator;
use Illuminate\Console\Command;

class PreviewFiles extends Command
{
    protected $signature = 'files:previews {--server= : Limit to a server id}';

    protected $description = 'Queue preview generation for stored Office/PDF files that lack one';

    public function handle(): int
    {
        $onlyOfficeReady = app(OnlyOfficeConfigService::class)->isEnabledAndConfigured();
        $query = StoredFile::query()
            ->whereNull('preview_path')
            ->where(function ($query): void {
                $query
                    ->whereNull('preview_status')
                    ->orWhere('preview_status', 'failed');
            });

        if ($serverId = $this->option('server')) {
            $query->where('server_id', (int) $serverId);
        }

        $files = $query->get(['id', 'path', 'original_name']);
        $queued = 0;

        foreach ($files as $file) {
            if (! StoredFilePreviewGenerator::supports($file->original_name)
                || (StoredFilePreviewGenerator::requiresOnlyOffice($file->original_name) && ! $onlyOfficeReady)) {
                continue;
            }

            $claimed = StoredFile::query()
                ->whereKey($file->id)
                ->whereNull('preview_path')
                ->where(function ($query): void {
                    $query
                        ->whereNull('preview_status')
                        ->orWhere('preview_status', 'failed');
                })
                ->update(['preview_status' => 'pending']);

            if ($claimed !== 1) {
                continue;
            }

            GenerateStoredFilePreview::dispatch($file->id, $file->path);
            $queued++;
            $this->line("Queued #{$file->id} {$file->original_name}");
        }

        $this->info("Queued {$queued} files for preview generation.");

        return self::SUCCESS;
    }
}
