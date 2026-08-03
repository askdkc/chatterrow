<?php

namespace Tests\Feature;

use App\Jobs\GenerateMarkdownedDoc;
use App\Jobs\GenerateStoredFilePreview;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownedDocGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

    public function test_onlyoffice_dependent_jobs_are_skipped_when_it_is_not_configured(): void
    {
        Queue::fake();
        config([
            'onlyoffice.enabled' => true,
            'onlyoffice.jwt_secret' => '',
        ]);

        $modern = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'report.docx',
            'path' => 'uploads/1/report.docx',
        ]);
        $legacy = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'report.doc',
            'path' => 'uploads/1/report.doc',
        ]);

        $this->assertNull($modern->preview_status);
        $this->assertSame('pending', $modern->markdown_status);
        $this->assertNull($legacy->preview_status);
        $this->assertNull($legacy->markdown_status);
        Queue::assertNotPushed(GenerateStoredFilePreview::class);
        Queue::assertPushed(GenerateMarkdownedDoc::class, 1);
    }

    public function test_onlyoffice_dependent_jobs_are_queued_when_it_is_configured(): void
    {
        Queue::fake();
        config([
            'onlyoffice.enabled' => true,
            'onlyoffice.document_server_url' => 'http://onlyoffice.test',
            'onlyoffice.public_url' => 'http://onlyoffice.test',
            'onlyoffice.internal_url' => 'http://chatterrow.test',
            'onlyoffice.jwt_secret' => str_repeat('secret', 8),
        ]);

        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'report.doc',
            'path' => 'uploads/1/report.doc',
        ]);

        $this->assertSame('pending', $file->preview_status);
        $this->assertSame('pending', $file->markdown_status);
        Queue::assertPushed(GenerateStoredFilePreview::class, 1);
        Queue::assertPushed(GenerateMarkdownedDoc::class, 1);
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

    public function test_generator_converts_legacy_office_input_with_onlyoffice(): void
    {
        Storage::fake('local');
        Storage::fake('markdowned');

        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'report.doc',
            'path' => 'uploads/1/abc123.doc',
            'mime_type' => 'application/msword',
        ]));

        Storage::disk('local')->put($file->path, 'legacy office document');
        config([
            'onlyoffice.enabled' => true,
            'onlyoffice.document_server_url' => 'http://onlyoffice.test',
            'onlyoffice.public_url' => 'http://onlyoffice.test',
            'onlyoffice.internal_url' => 'http://chatterrow.test',
            'onlyoffice.jwt_secret' => str_repeat('secret', 8),
        ]);
        Http::fake([
            'http://onlyoffice.test/converter*' => Http::response([
                'endConvert' => true,
                'fileType' => 'docx',
                'fileUrl' => 'http://onlyoffice.test/cache/result.docx',
                'percent' => 100,
            ]),
            'http://onlyoffice.test/cache/result.docx' => Http::response("PK\x03\x04converted document"),
        ]);
        Process::fake(function ($process) {
            $command = $process->command;
            $inputPath = is_array($command) ? $command[1] : null;

            $this->assertIsString($inputPath);
            $this->assertSame('abc123.docx', basename($inputPath));
            $this->assertStringStartsWith("PK\x03\x04", (string) file_get_contents($inputPath));

            return Process::result(output: '# Converted legacy document');
        });

        $path = app(MarkdownedDocGenerator::class)->generate($file);

        $expectedContent = "# Converted legacy document\n";

        Storage::disk('markdowned')->assertExists($path);
        $this->assertSame($expectedContent, Storage::disk('markdowned')->get($path));

        $row = DB::table('markdown_docs')->where('rowid', $file->id)->first();
        $this->assertNotNull($row);
        $this->assertSame($expectedContent, $row->content);
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
}
