<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
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
    ];

    public static function supports(?string $name): bool
    {
        $extension = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

        return in_array($extension, self::NATIVE_EXTENSIONS, true);
    }

    public static function markdownPath(int $storedFileId, string $sourcePath): string
    {
        $hash = substr(hash('sha256', $sourcePath), 0, 16);

        return substr($hash, 0, 2)."/{$storedFileId}-{$hash}.md";
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

        if (! self::supports($storedFile->original_name)) {
            throw new RuntimeException('File extension is not supported.');
        }

        $cliPath = (string) config('services.markitdown.path', '');
        $this->assertCliIsExecutable($cliPath);

        try {
            $result = Process::timeout((int) config('services.markitdown.timeout', 180))->run([
                $cliPath,
                $sourcePath,
            ]);
        } catch (ProcessTimedOutException $exception) {
            $this->logConversionFailure(
                $storedFile,
                null,
                $exception->result->errorOutput(),
                'timed out',
            );

            throw new RuntimeException('MarkItDown conversion timed out.', 0, $exception);
        }

        if (! $result->successful()) {
            $this->logConversionFailure(
                $storedFile,
                $result->exitCode(),
                $result->errorOutput(),
                'failed',
            );

            throw new RuntimeException(sprintf(
                'MarkItDown conversion failed with exit code %s.',
                (string) $result->exitCode(),
            ));
        }

        $content = $result->output();

        if ($content === '') {
            throw new RuntimeException('MarkItDown produced no output.');
        }

        $targetPath = self::markdownPath($storedFile->id, $storedFile->path);

        if (! Storage::disk('markdowned')->put($targetPath, $content)) {
            throw new RuntimeException('Markdown storage failed.');
        }

        app(MarkdownSearchIndex::class)->index($storedFile->id, $content);

        return $targetPath;
    }

    private function assertCliIsExecutable(string $cliPath): void
    {
        if ($cliPath === '' || ! is_file($cliPath)) {
            throw new RuntimeException("MarkItDown CLI was not found at {$cliPath}.");
        }

        if (PHP_OS_FAMILY !== 'Windows' && ! is_executable($cliPath)) {
            throw new RuntimeException("MarkItDown CLI is not executable: {$cliPath}.");
        }
    }

    private function logConversionFailure(
        StoredFile $storedFile,
        ?int $exitCode,
        string $stderr,
        string $reason,
    ): void {
        Log::warning('MarkItDown conversion '.$reason.'.', [
            'stored_file_id' => $storedFile->id,
            'source_path' => $storedFile->path,
            'exit_code' => $exitCode,
            'stderr' => $this->limitedOutput($stderr),
        ]);
    }

    private function limitedOutput(string $output): string
    {
        $limit = 4096;

        return strlen($output) > $limit ? substr($output, 0, $limit).'...' : $output;
    }
}
