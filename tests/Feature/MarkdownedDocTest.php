<?php

namespace Tests\Feature;

use App\Jobs\GenerateMarkdownedDoc;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownedDocGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MarkdownedDocTest extends TestCase
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
        config(['services.markitdown.path' => PHP_BINARY]);
    }

    public function test_uploading_a_supported_file_queues_markdown_conversion(): void
    {
        Queue::fake();

        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'report.docx',
            'path' => 'uploads/1/report.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);

        $this->assertSame('pending', $file->markdown_status);
        Queue::assertPushed(GenerateMarkdownedDoc::class, fn (GenerateMarkdownedDoc $job) => $job->storedFileId === $file->id);
    }

    public function test_legacy_office_files_are_not_marked_for_markdown_conversion(): void
    {
        Queue::fake();
        $legacy = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'report.doc',
            'path' => 'uploads/1/report.doc',
        ]);

        $this->assertNull($legacy->markdown_status);
        Queue::assertNotPushed(GenerateMarkdownedDoc::class);
    }

    public function test_unsupported_files_are_not_marked_for_conversion(): void
    {
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'image.png',
        ]);

        $this->assertNull($file->markdown_status);
    }

    public function test_markdown_command_reports_only_files_actually_queued(): void
    {
        Queue::fake();

        $supported = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'report.docx',
            'path' => 'uploads/1/report.docx',
        ]);
        StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'image.png',
            'path' => 'uploads/1/image.png',
        ]);
        $supported->update(['markdown_status' => 'failed']);

        Queue::fake();

        $this->artisan('files:markdown')
            ->expectsOutput('Queued 1 files for markdown conversion.')
            ->assertSuccessful();

        Queue::assertPushed(GenerateMarkdownedDoc::class, 1);
    }

    public function test_markdown_command_skips_legacy_office_files_when_onlyoffice_is_not_configured(): void
    {
        Queue::fake();
        config(['onlyoffice.enabled' => false]);
        StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'report.doc',
            'path' => 'uploads/1/report.doc',
            'markdown_path' => null,
            'markdown_status' => null,
        ]));

        $this->artisan('files:markdown')
            ->expectsOutput('Queued 0 files for markdown conversion.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_generator_converts_a_pdf_and_indexes_the_content(): void
    {
        Storage::fake('local');
        Storage::fake('markdowned');

        Process::fake(['*' => Process::result(output: "# Converted\n\nこれは全文検索の対象になる日本語本文です", exitCode: 0)]);

        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'document.pdf',
            'path' => 'uploads/1/document.pdf',
            'mime_type' => 'application/pdf',
        ]);

        Storage::disk('local')->put($file->path, '%PDF-1.4 fake content');

        $path = app(MarkdownedDocGenerator::class)->generate($file);

        Storage::disk('markdowned')->assertExists($path);
        $this->assertStringContainsString('日本語本文', Storage::disk('markdowned')->get($path));

        $row = DB::table('markdown_docs')->where('rowid', $file->id)->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('全文検索', $row->content);
    }

    public function test_generator_throws_when_markitdown_fails(): void
    {
        Storage::fake('local');

        Process::fake(['*' => Process::result(output: '', exitCode: 1)]);

        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'broken.pdf',
            'path' => 'uploads/1/broken.pdf',
            'mime_type' => 'application/pdf',
        ]);

        Storage::disk('local')->put($file->path, 'not a pdf');

        $this->expectException(RuntimeException::class);
        app(MarkdownedDocGenerator::class)->generate($file);
    }

    public function test_markdown_job_publishes_ready_only_after_storage_and_indexing(): void
    {
        Storage::fake('local');
        Storage::fake('markdowned');
        Process::fake(['*' => Process::result(output: '# Ready document')]);

        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'ready.pdf',
            'path' => 'uploads/1/ready.pdf',
            'markdown_status' => 'pending',
        ]));
        Storage::disk('local')->put($file->path, '%PDF-1.4 fixture');

        (new GenerateMarkdownedDoc($file->id, $file->path))->handle(app(MarkdownedDocGenerator::class));

        $file->refresh();
        $this->assertSame('ready', $file->markdown_status);
        $this->assertNotNull($file->markdown_path);
        $this->assertDatabaseHas('markdown_doc_contents', ['stored_file_id' => $file->id]);
    }

    public function test_markdown_command_recovers_stale_processing_files(): void
    {
        Queue::fake();
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'stale.pdf',
            'path' => 'uploads/1/stale.pdf',
            'markdown_status' => 'processing',
        ]));
        StoredFile::query()->whereKey($file->id)->update([
            'updated_at' => now()->subHour(),
        ]);

        $this->artisan('files:markdown', ['--stale-after' => 900])
            ->expectsOutput('Queued 1 files for markdown conversion.')
            ->assertSuccessful();

        Queue::assertPushed(GenerateMarkdownedDoc::class, fn (GenerateMarkdownedDoc $job): bool => $job->storedFileId === $file->id);
        $this->assertDatabaseHas('stored_files', [
            'id' => $file->id,
            'markdown_status' => 'pending',
            'markdown_path' => null,
        ]);
    }

    public function test_generator_logs_a_limited_stderr_when_markitdown_fails(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result(
            output: '',
            errorOutput: str_repeat('e', 10000),
            exitCode: 17,
        )]);
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'MarkItDown conversion failed.'
                    && $context['exit_code'] === 17
                    && strlen($context['stderr']) <= 4099;
            });

        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'stderr.pdf',
            'path' => 'uploads/1/stderr.pdf',
        ]));
        Storage::disk('local')->put($file->path, 'fixture');

        $this->expectException(RuntimeException::class);
        app(MarkdownedDocGenerator::class)->generate($file);
    }

    public function test_generator_rejects_a_missing_markitdown_cli(): void
    {
        Storage::fake('local');
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'missing-cli.pdf',
            'path' => 'uploads/1/missing-cli.pdf',
        ]));
        Storage::disk('local')->put($file->path, 'fixture');
        config(['services.markitdown.path' => sys_get_temp_dir().'/missing-markitdown-cli']);

        $this->expectExceptionMessage('MarkItDown CLI was not found');
        app(MarkdownedDocGenerator::class)->generate($file);
    }

    public function test_generator_rejects_a_non_executable_markitdown_cli(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('POSIX executable permissions are not available on Windows.');
        }

        Storage::fake('local');
        $cliPath = tempnam(sys_get_temp_dir(), 'markitdown-cli-');
        $this->assertNotFalse($cliPath);
        file_put_contents($cliPath, "#!/bin/sh\nprintf '# output'\n");
        chmod($cliPath, 0644);
        config(['services.markitdown.path' => $cliPath]);

        try {
            $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
                'server_id' => $this->server->id,
                'uploaded_by' => $this->owner->id,
                'original_name' => 'permissions.pdf',
                'path' => 'uploads/1/permissions.pdf',
            ]));
            Storage::disk('local')->put($file->path, 'fixture');

            $this->expectExceptionMessage('MarkItDown CLI is not executable');
            app(MarkdownedDocGenerator::class)->generate($file);
        } finally {
            @unlink($cliPath);
        }
    }

    public function test_generator_handles_a_markitdown_timeout(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('The timeout fixture uses a POSIX shell.');
        }

        Storage::fake('local');
        $cliPath = tempnam(sys_get_temp_dir(), 'markitdown-timeout-');
        $this->assertNotFalse($cliPath);
        file_put_contents($cliPath, "#!/bin/sh\nsleep 2\nprintf '# output'\n");
        chmod($cliPath, 0755);
        config([
            'services.markitdown.path' => $cliPath,
            'services.markitdown.timeout' => 1,
        ]);

        try {
            $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
                'server_id' => $this->server->id,
                'uploaded_by' => $this->owner->id,
                'original_name' => 'timeout.pdf',
                'path' => 'uploads/1/timeout.pdf',
            ]));
            Storage::disk('local')->put($file->path, 'fixture');

            $this->expectExceptionMessage('MarkItDown conversion timed out');
            app(MarkdownedDocGenerator::class)->generate($file);
        } finally {
            @unlink($cliPath);
        }
    }

    public function test_generator_rejects_unsupported_extensions(): void
    {
        Storage::fake('local');

        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'archive.zip',
            'path' => 'uploads/1/archive.zip',
        ]);

        Storage::disk('local')->put($file->path, 'zip');

        $this->expectException(RuntimeException::class);
        app(MarkdownedDocGenerator::class)->generate($file);
    }

    public function test_generator_rejects_legacy_office_extensions(): void
    {
        Storage::fake('local');

        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'archive.doc',
            'path' => 'uploads/1/archive.doc',
        ]));

        Storage::disk('local')->put($file->path, 'legacy office document');

        $this->expectException(RuntimeException::class);
        app(MarkdownedDocGenerator::class)->generate($file);
    }
}
