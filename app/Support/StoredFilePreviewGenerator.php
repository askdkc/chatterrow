<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Filesystem\FilesystemAdapter;
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

    public static function requiresOnlyOffice(?string $name): bool
    {
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return $extension !== 'pdf' && in_array($extension, self::SUPPORTED_EXTENSIONS, true);
    }

    public static function sourceHash(string $sourcePath): string
    {
        return substr(hash('sha256', $sourcePath), 0, 16);
    }

    public static function webpPath(int $storedFileId, string $sourcePath): string
    {
        return "previews/{$storedFileId}-".self::sourceHash($sourcePath).'.webp';
    }

    public static function officeThumbnailPath(int $storedFileId, string $sourcePath): string
    {
        return "previews/{$storedFileId}-".self::sourceHash($sourcePath).'.png';
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
    ): bool {
        $paths = [
            self::cachedPdfPath($storedFileId, $sourcePath),
            self::webpPath($storedFileId, $sourcePath),
            self::officeThumbnailPath($storedFileId, $sourcePath),
        ];

        if ($storedPreviewPath !== null) {
            $paths[] = $storedPreviewPath;
        }

        if ($includeLegacy) {
            $paths = [...$paths, ...self::legacyPaths($storedFileId)];
        }

        $cleaned = true;

        foreach (array_unique($paths) as $path) {
            try {
                if (! $disk->delete($path)) {
                    $cleaned = false;
                    Log::warning('Stored file preview cleanup failed.', [
                        'stored_file_id' => $storedFileId,
                        'path' => $path,
                    ]);
                }
            } catch (Throwable $exception) {
                $cleaned = false;
                Log::warning('Stored file preview cleanup failed.', [
                    'stored_file_id' => $storedFileId,
                    'path' => $path,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $cleaned;
    }

    public function __construct(private OnlyOfficeConversionService $onlyOfficeConversion) {}

    public function generate(StoredFile $storedFile): string
    {
        $disk = Storage::disk($storedFile->disk);
        $extension = strtolower(pathinfo($storedFile->original_name, PATHINFO_EXTENSION));

        if (! $disk->exists($storedFile->path)) {
            throw new RuntimeException('Source file is unavailable.');
        }

        if ($extension !== 'pdf') {
            $previewPath = self::officeThumbnailPath($storedFile->id, $storedFile->path);
            $contents = $this->onlyOfficeConversion->thumbnail($storedFile);

            if (! $disk->put($previewPath, $contents)) {
                throw new RuntimeException('Office thumbnail storage failed.');
            }

            return $previewPath;
        }

        $pdfPath = $disk->path($storedFile->path);

        if (! is_file($pdfPath)) {
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
}
