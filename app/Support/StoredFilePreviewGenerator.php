<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoredFilePreviewGenerator
{
    /** @var list<string> */
    public const SUPPORTED_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'xlsm',
        'ods',
        'odt',
        'ppt',
        'pptx',
        'odp',
    ];

    public static function supports(?string $name): bool
    {
        return in_array(strtolower(pathinfo((string) $name, PATHINFO_EXTENSION)), self::SUPPORTED_EXTENSIONS, true);
    }

    public static function sourceHash(string $sourcePath): string
    {
        return substr(hash('sha256', $sourcePath), 0, 16);
    }

    public static function webpPath(int $storedFileId, string $sourcePath): string
    {
        return "previews/{$storedFileId}-".self::sourceHash($sourcePath).'.webp';
    }

    public static function cachedPdfPath(int $storedFileId, string $sourcePath): string
    {
        return "previews/{$storedFileId}-".self::sourceHash($sourcePath).'.pdf';
    }

    public static function legacyWebpPath(int $storedFileId): string
    {
        return "previews/{$storedFileId}.webp";
    }

    public static function legacyPdfPath(int $storedFileId): string
    {
        return "previews/{$storedFileId}.pdf";
    }

    /** @return list<string> */
    public static function legacyPaths(int $storedFileId): array
    {
        return [
            self::legacyPdfPath($storedFileId),
            self::legacyWebpPath($storedFileId),
        ];
    }

    /**
     * Remove only the derivatives belonging to one source version.
     *
     * Legacy ID-only paths are included only by callers explicitly cleaning up
     * a replacement or deletion; they are never read as a cache.
     */
    public static function cleanup(
        FilesystemAdapter $disk,
        int $storedFileId,
        string $sourcePath,
        ?string $storedPreviewPath = null,
        bool $includeLegacy = false,
    ): void {
        $paths = [
            self::cachedPdfPath($storedFileId, $sourcePath),
            self::webpPath($storedFileId, $sourcePath),
        ];

        if ($storedPreviewPath !== null) {
            $paths[] = $storedPreviewPath;
        }

        if ($includeLegacy) {
            $paths = [...$paths, ...self::legacyPaths($storedFileId)];
        }

        foreach (array_unique($paths) as $path) {
            try {
                if (! $disk->delete($path)) {
                    Log::warning('Stored file preview cleanup failed.', [
                        'stored_file_id' => $storedFileId,
                        'path' => $path,
                    ]);
                }
            } catch (Throwable $exception) {
                Log::warning('Stored file preview cleanup failed.', [
                    'stored_file_id' => $storedFileId,
                    'path' => $path,
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    public function generate(StoredFile $storedFile): string
    {
        $disk = Storage::disk($storedFile->disk);
        $extension = strtolower(pathinfo($storedFile->original_name, PATHINFO_EXTENSION));
        $pdfPath = $extension === 'pdf'
            ? $disk->path($storedFile->path)
            : $this->ensureOfficePdf($disk, $storedFile);

        if ($pdfPath === null || ! is_file($pdfPath)) {
            throw new RuntimeException('PDF input is unavailable.');
        }

        $workDir = sys_get_temp_dir().'/stored-file-preview-'.bin2hex(random_bytes(8));
        File::makeDirectory($workDir, 0700, true);

        try {
            $pngPrefix = $workDir.'/page-1';
            $pngResult = Process::timeout((int) config('services.poppler.timeout', 45))->run([
                config('services.poppler.path', 'pdftoppm'),
                '-f',
                '1',
                '-l',
                '1',
                '-png',
                '-singlefile',
                $pdfPath,
                $pngPrefix,
            ]);

            $pngPath = $pngPrefix.'.png';

            if (! $pngResult->successful() || ! is_file($pngPath)) {
                throw new RuntimeException('PDF page rendering failed.');
            }

            $webpPath = $workDir.'/preview.webp';
            $webpResult = Process::timeout((int) config('services.imagemagick.timeout', 45))->run([
                config('services.imagemagick.path', 'convert'),
                $pngPath,
                '-resize',
                '1600x1600>',
                '-strip',
                '-quality',
                '82',
                $webpPath,
            ]);

            if (! $webpResult->successful() || ! is_file($webpPath)) {
                throw new RuntimeException('WebP conversion failed.');
            }

            $previewPath = self::webpPath($storedFile->id, $storedFile->path);
            $contents = file_get_contents($webpPath);

            if ($contents === false || ! $disk->put($previewPath, $contents)) {
                throw new RuntimeException('WebP storage failed.');
            }

            return $previewPath;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * Return the full-preview PDF, creating the shared Office cache when needed.
     */
    public function officePreviewPdf(StoredFile $storedFile): ?string
    {
        $disk = Storage::disk($storedFile->disk);

        try {
            $pdfPath = $this->ensureOfficePdf($disk, $storedFile);

            return $pdfPath === null ? null : $disk->get(self::cachedPdfPath($storedFile->id, $storedFile->path));
        } catch (Throwable $exception) {
            Log::warning('Office preview PDF read failed.', [
                'stored_file_id' => $storedFile->id,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function ensureOfficePdf(FilesystemAdapter $disk, StoredFile $storedFile): ?string
    {
        $sourcePath = $storedFile->path;
        $cachePath = self::cachedPdfPath($storedFile->id, $sourcePath);

        if ($disk->exists($cachePath)) {
            if (! $this->sourceIsCurrent($storedFile, $sourcePath)) {
                $disk->delete($cachePath);

                return null;
            }

            return $disk->path($cachePath);
        }

        $workDir = sys_get_temp_dir().'/office-preview-'.bin2hex(random_bytes(8));
        File::makeDirectory($workDir, 0700, true);

        try {
            $result = Process::timeout((int) config('services.libreoffice.timeout', 120))->run([
                config('services.libreoffice.path', 'soffice'),
                '--headless',
                '-env:UserInstallation=file://'.$workDir.'/profile',
                '--convert-to',
                'pdf',
                '--outdir',
                $workDir,
                $disk->path($sourcePath),
            ]);

            $converted = $workDir.'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.pdf';

            if (! $result->successful() || ! is_file($converted)) {
                Log::warning('Office preview conversion failed.', [
                    'stored_file_id' => $storedFile->id,
                    'exit_code' => $result->exitCode(),
                ]);

                return null;
            }

            $contents = file_get_contents($converted);

            if ($contents === false) {
                Log::warning('Office preview cache write failed.', [
                    'stored_file_id' => $storedFile->id,
                ]);

                return null;
            }

            $published = DB::transaction(function () use ($disk, $storedFile, $sourcePath, $cachePath, $contents): ?bool {
                $current = StoredFile::query()
                    ->whereKey($storedFile->id)
                    ->lockForUpdate()
                    ->first();

                if ($current === null
                    || $current->disk !== $storedFile->disk
                    || $current->path !== $sourcePath
                    || $current->preview_status === 'deleting') {
                    return null;
                }

                return (bool) $disk->put($cachePath, $contents);
            });

            if ($published === null) {
                $disk->delete($cachePath);

                return null;
            }

            if (! $published) {
                Log::warning('Office preview cache write failed.', [
                    'stored_file_id' => $storedFile->id,
                ]);

                return null;
            }

            return $disk->path($cachePath);
        } catch (Throwable $exception) {
            Log::warning('Office preview conversion failed.', [
                'stored_file_id' => $storedFile->id,
                'exception' => $exception::class,
            ]);

            return null;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function sourceIsCurrent(StoredFile $storedFile, string $sourcePath): bool
    {
        return StoredFile::query()
            ->whereKey($storedFile->id)
            ->where('disk', $storedFile->disk)
            ->where('path', $sourcePath)
            ->where(function ($query): void {
                $query
                    ->whereNull('preview_status')
                    ->orWhere('preview_status', '!=', 'deleting');
            })
            ->exists();
    }
}
