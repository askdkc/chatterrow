<?php

namespace Tests\Feature;

use App\Http\Controllers\StoredFileController;
use App\Jobs\GenerateMarkdownedDoc;
use App\Jobs\GenerateStoredFilePreview;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownedDocDispatcher;
use App\Support\MarkdownedDocGenerator;
use App\Support\StoredFilePreviewDispatcher;
use App\Support\StoredFilePreviewGenerator;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Filesystem\FilesystemAdapter;
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
            StoredFilePreviewGenerator::officeThumbnailPath($file->id, $sourcePath),
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

    public function test_a_deleting_file_can_resume_deletion_after_an_interrupted_attempt(): void
    {
        Storage::fake('local');
        $file = $this->storedFile([
            'preview_path' => null,
            'preview_status' => 'deleting',
        ]);
        Storage::disk('local')->put($file->path, 'source');

        $this->actingAs($this->owner)
            ->delete(route('servers.files.destroy', [$this->server, $file]))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('stored_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_a_failed_source_deletion_keeps_the_database_record_for_retry(): void
    {
        $file = $this->storedFile([
            'preview_path' => 'previews/existing.webp',
            'preview_status' => 'ready',
        ]);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->once()->with($file->path)->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with($file->path)->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        try {
            $this->withoutExceptionHandling()
                ->actingAs($this->owner)
                ->delete(route('servers.files.destroy', [$this->server, $file]));
            $this->fail('Expected the failed storage deletion to throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Stored file deletion failed.', $exception->getMessage());
        }

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'preview_status' => 'deleting',
        ]);
    }

    public function test_deletion_refreshes_markdown_state_before_cleanup(): void
    {
        Storage::fake('local');
        Storage::fake('markdowned');
        $file = $this->storedFile([
            'preview_path' => null,
            'preview_status' => 'ready',
            'markdown_path' => null,
            'markdown_status' => 'processing',
        ]);
        $markdownPath = 'stored-file.md';
        StoredFile::query()->whereKey($file->id)->update([
            'markdown_path' => $markdownPath,
            'markdown_status' => 'ready',
        ]);
        Storage::disk('local')->put($file->path, 'source');
        Storage::disk('markdowned')->put($markdownPath, 'markdown');

        $this->actingAs($this->owner);
        app(StoredFileController::class)->destroy($this->server, $file);

        $this->assertDatabaseMissing('stored_files', ['id' => $file->id]);
        Storage::disk('markdowned')->assertMissing($markdownPath);
    }

    public function test_failed_preview_cleanup_keeps_the_database_record_for_retry(): void
    {
        $file = $this->storedFile([
            'preview_path' => null,
            'preview_status' => 'deleting',
        ]);
        $failedPath = StoredFilePreviewGenerator::cachedPdfPath($file->id, $file->path);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('delete')
            ->andReturnUsing(fn (string $path): bool => $path !== $failedPath);
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        try {
            $file->delete();
            $this->fail('Expected failed derivative cleanup to throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Stored file preview cleanup failed.', $exception->getMessage());
        }

        $this->assertDatabaseHas('stored_files', ['id' => $file->id]);
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

    public function test_preview_command_backfills_an_office_file_after_onlyoffice_is_configured(): void
    {
        Queue::fake();
        config(['onlyoffice.enabled' => false]);
        $file = $this->storedFile([
            'original_name' => 'legacy.doc',
            'preview_path' => null,
            'preview_status' => null,
        ]);

        $this->artisan('files:previews')
            ->expectsOutput('Queued 0 files for preview generation.')
            ->assertSuccessful();

        config([
            'onlyoffice.enabled' => true,
            'onlyoffice.document_server_url' => 'http://onlyoffice.test',
            'onlyoffice.public_url' => 'http://onlyoffice.test',
            'onlyoffice.internal_url' => 'http://chatterrow.test',
            'onlyoffice.jwt_secret' => str_repeat('secret', 8),
        ]);

        $this->artisan('files:previews')
            ->expectsOutput("Queued #{$file->id} {$file->original_name}")
            ->expectsOutput('Queued 1 files for preview generation.')
            ->assertSuccessful();

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'preview_status' => 'pending',
        ]);
        Queue::assertPushed(GenerateStoredFilePreview::class, 1);
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

    public function test_preview_job_rethrows_generation_errors_for_queue_retry(): void
    {
        Storage::fake('local');
        $file = $this->storedFile([
            'preview_path' => null,
            'preview_status' => 'pending',
        ]);
        $generator = Mockery::mock(StoredFilePreviewGenerator::class);
        $generator->shouldReceive('generate')
            ->once()
            ->andThrow(new RuntimeException('ONLYOFFICE unavailable'));
        $job = new GenerateStoredFilePreview($file->id, $file->path);

        try {
            $job->handle($generator);
            $this->fail('Expected preview generation to be retried.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ONLYOFFICE unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'preview_path' => null,
            'preview_status' => 'processing',
        ]);
        $this->assertSame([30, 60, 120, 300, 600], $job->backoff());
    }

    public function test_preview_job_marks_the_file_failed_after_retries_are_exhausted(): void
    {
        Storage::fake('local');
        $file = $this->storedFile([
            'preview_path' => null,
            'preview_status' => 'processing',
        ]);
        $previewPath = StoredFilePreviewGenerator::officeThumbnailPath($file->id, $file->path);
        Storage::disk('local')->put($previewPath, 'partial preview');

        (new GenerateStoredFilePreview($file->id, $file->path))
            ->failed(new RuntimeException('ONLYOFFICE unavailable'));

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'preview_path' => null,
            'preview_status' => 'failed',
        ]);
        Storage::disk('local')->assertMissing($previewPath);
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

    public function test_markdown_job_rethrows_generation_errors_for_queue_retry(): void
    {
        Storage::fake('markdowned');
        $file = $this->storedFile([
            'markdown_path' => null,
            'markdown_status' => 'pending',
        ]);
        $generator = Mockery::mock(MarkdownedDocGenerator::class);
        $generator->shouldReceive('generate')
            ->once()
            ->andThrow(new RuntimeException('ONLYOFFICE unavailable'));
        $job = new GenerateMarkdownedDoc($file->id, $file->path);

        try {
            $job->handle($generator);
            $this->fail('Expected markdown generation to be retried.');
        } catch (RuntimeException $exception) {
            $this->assertSame('ONLYOFFICE unavailable', $exception->getMessage());
        }

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'markdown_path' => null,
            'markdown_status' => 'processing',
        ]);
        $this->assertSame([30, 60, 120, 300], $job->backoff());
    }

    public function test_markdown_job_marks_the_file_failed_after_retries_are_exhausted(): void
    {
        Storage::fake('markdowned');
        $file = $this->storedFile([
            'markdown_path' => null,
            'markdown_status' => 'processing',
        ]);
        $markdownPath = MarkdownedDocGenerator::markdownPath($file->id, $file->path);
        Storage::disk('markdowned')->put($markdownPath, 'partial markdown');

        (new GenerateMarkdownedDoc($file->id, $file->path))
            ->failed(new RuntimeException('ONLYOFFICE unavailable'));

        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'markdown_path' => null,
            'markdown_status' => 'failed',
        ]);
        Storage::disk('markdowned')->assertMissing($markdownPath);
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
