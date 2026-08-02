<?php

namespace Tests\Feature;

use App\Jobs\GenerateMarkdownedDoc;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownedDocGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_unsupported_files_are_not_marked_for_conversion(): void
    {
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'image.png',
        ]);

        $this->assertNull($file->markdown_status);
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
