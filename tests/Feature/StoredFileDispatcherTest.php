<?php

namespace Tests\Feature;

use App\Jobs\GenerateMarkdownedDoc;
use App\Jobs\GenerateStoredFilePreview;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownedDocDispatcher;
use App\Support\StoredFilePreviewDispatcher;
use App\Support\StoredFilePreviewGenerator;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StoredFileDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->server = Server::factory()->create(['created_by' => $this->owner->id]);
        $this->server->members()->attach($this->owner->id);
    }

    public function test_deleting_a_stored_file_removes_all_preview_derivatives(): void
    {
        Storage::fake('local');
        $sourcePath = 'uploads/1/source.docx';
        $storedPreviewPath = 'previews/stored-preview.webp';

        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'path' => $sourcePath,
            'original_name' => 'source.docx',
            'preview_path' => $storedPreviewPath,
            'preview_status' => 'ready',
        ]));
        $disk = Storage::disk('local');
        $paths = [
            StoredFilePreviewGenerator::webpPath($file->id, $sourcePath),
            StoredFilePreviewGenerator::cachedPdfPath($file->id, $sourcePath),
            $storedPreviewPath,
            StoredFilePreviewGenerator::legacyWebpPath($file->id),
            StoredFilePreviewGenerator::legacyPdfPath($file->id),
        ];

        foreach ($paths as $path) {
            $disk->put($path, 'derivative');
        }

        $file->delete();

        foreach ($paths as $path) {
            $disk->assertMissing($path);
        }
    }

    public function test_stored_file_preview_dispatcher_dispatches_preview_job(): void
    {
        Queue::fake();
        $file = $this->storedFile([
            'preview_path' => null,
            'preview_status' => 'pending',
        ]);

        app(StoredFilePreviewDispatcher::class)->dispatchAfterCommit($file->id, $file->path);

        Queue::assertPushed(GenerateStoredFilePreview::class, fn (GenerateStoredFilePreview $job): bool => $job->storedFileId === $file->id && $job->sourcePath === $file->path
        );
    }

    public function test_markdowned_doc_dispatcher_dispatches_markdown_job(): void
    {
        Queue::fake();
        $file = $this->storedFile([
            'markdown_path' => null,
            'markdown_status' => 'pending',
        ]);

        app(MarkdownedDocDispatcher::class)->dispatchAfterCommit($file->id, $file->path);

        Queue::assertPushed(GenerateMarkdownedDoc::class, fn (GenerateMarkdownedDoc $job): bool => $job->storedFileId === $file->id && $job->sourcePath === $file->path
        );
    }

    public function test_stored_file_preview_dispatcher_marks_preview_failed_when_dispatch_throws(): void
    {
        $file = $this->storedFile([
            'preview_path' => 'previews/existing.webp',
            'preview_status' => 'processing',
        ]);
        $this->app->instance(Dispatcher::class, Mockery::mock(Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatch')
                ->once()
                ->with(Mockery::type(GenerateStoredFilePreview::class))
                ->andThrow(new RuntimeException('queue unavailable'));
        }));

        app(StoredFilePreviewDispatcher::class)->dispatchAfterCommit($file->id, $file->path);

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'path' => $file->path,
            'preview_path' => null,
            'preview_status' => 'failed',
        ]);
    }

    public function test_markdowned_doc_dispatcher_marks_markdown_failed_when_dispatch_throws(): void
    {
        $file = $this->storedFile([
            'markdown_path' => 'markdown/existing.md',
            'markdown_status' => 'processing',
        ]);
        $this->app->instance(Dispatcher::class, Mockery::mock(Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatch')
                ->once()
                ->with(Mockery::type(GenerateMarkdownedDoc::class))
                ->andThrow(new RuntimeException('queue unavailable'));
        }));

        app(MarkdownedDocDispatcher::class)->dispatchAfterCommit($file->id, $file->path);

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'path' => $file->path,
            'markdown_path' => null,
            'markdown_status' => 'failed',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function storedFile(array $attributes): StoredFile
    {
        return StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'path' => 'uploads/source.docx',
            ...$attributes,
        ]));
    }
}
