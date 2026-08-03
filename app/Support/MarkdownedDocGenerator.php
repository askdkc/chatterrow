<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MarkdownedDocGenerator
{
    /** @var list<string> */
    public const NATIVE_EXTENSIONS = [
        'pdf',
        'docx',
        'xlsx',
        'pptx',
        'csv',
        'html',
        'htm',
        'txt',
        'md',
        'markdown',
    ];

    /** @var list<string> */
    public const ONLYOFFICE_EXTENSIONS = [
        'doc',
        'xls',
        'xlsm',
        'ppt',
        'odt',
        'ods',
        'odp',
    ];

    /** @var array<string, string> */
    public const ONLYOFFICE_TARGETS = [
        'doc' => 'docx',
        'xls' => 'xlsx',
        'xlsm' => 'xlsx',
        'ppt' => 'pptx',
        'odt' => 'docx',
        'ods' => 'xlsx',
        'odp' => 'pptx',
    ];

    public static function supports(?string $name): bool
    {
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return in_array($extension, [...self::NATIVE_EXTENSIONS, ...self::ONLYOFFICE_EXTENSIONS], true);
    }

    public static function requiresOnlyOffice(?string $name): bool
    {
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return isset(self::ONLYOFFICE_TARGETS[$extension]);
    }

    public function __construct(private OnlyOfficeConversionService $onlyOfficeConversion) {}

    public static function markdownPath(int $storedFileId, string $sourcePath): string
    {
        return "{$storedFileId}-".substr(hash('sha256', $sourcePath), 0, 16).'.md';
    }

    /**
     * Convert the stored file to Markdown, write it to the markdowned disk,
     * and index its content for full-text search.
     *
     * @return string relative path on the "markdowned" disk
     */
    public function generate(StoredFile $storedFile): string
    {
        $sourceDisk = Storage::disk($storedFile->disk);
        $sourcePath = $sourceDisk->path($storedFile->path);

        if (! is_file($sourcePath)) {
            throw new RuntimeException('Source file is unavailable.');
        }

        $extension = strtolower(pathinfo($storedFile->original_name, PATHINFO_EXTENSION));

        if (! self::supports($storedFile->original_name)) {
            throw new RuntimeException('File extension is not supported.');
        }

        $workDir = sys_get_temp_dir().'/markdowned-doc-'.bin2hex(random_bytes(8));
        File::makeDirectory($workDir, 0700, true);

        try {
            $inputPath = $sourcePath;

            if (isset(self::ONLYOFFICE_TARGETS[$extension])) {
                $targetExtension = self::ONLYOFFICE_TARGETS[$extension];
                $convertedPath = $workDir.'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.'.$targetExtension;
                $converted = $this->onlyOfficeConversion->document($storedFile, $targetExtension);

                if (File::put($convertedPath, $converted) === false) {
                    throw new RuntimeException('ONLYOFFICE conversion storage failed for markdown input.');
                }

                $inputPath = $convertedPath;
            }

            $result = Process::timeout((int) config('services.markitdown.timeout', 180))->run([
                config('services.markitdown.path'),
                $inputPath,
            ]);

            if (! $result->successful()) {
                throw new RuntimeException('markitdown conversion failed.');
            }

            $content = $result->output();

            if ($content === '') {
                throw new RuntimeException('markitdown produced no output.');
            }

            $targetPath = self::markdownPath($storedFile->id, $storedFile->path);

            if (! Storage::disk('markdowned')->put($targetPath, $content)) {
                throw new RuntimeException('Markdown storage failed.');
            }

            app(MarkdownSearchIndex::class)->index($storedFile->id, $content);

            return $targetPath;
        } finally {
            File::deleteDirectory($workDir);
        }
    }
}
