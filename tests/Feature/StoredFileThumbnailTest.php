<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoredFileThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->server = Server::factory()->create(['created_by' => $this->owner->id]);
        $this->server->members()->attach([$this->owner->id, $this->member->id]);
    }

    public function test_png_thumbnail_is_served_with_png_content_type_and_nosniff(): void
    {
        Storage::fake('local');
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'path' => 'uploads/report.docx',
            'original_name' => 'report.docx',
            'preview_path' => 'previews/office.png',
            'preview_status' => 'ready',
        ]));
        Storage::disk('local')->put($file->preview_path, "\x89PNG\r\n\x1a\n".'binary');

        $this->actingAs($this->member)
            ->get(route('servers.files.thumbnail', [$this->server, $file]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'max-age=3600, private');
    }

    public function test_webp_thumbnail_keeps_the_default_webp_content_type(): void
    {
        Storage::fake('local');
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'path' => 'uploads/report.pdf',
            'original_name' => 'report.pdf',
            'preview_path' => 'previews/report.webp',
            'preview_status' => 'ready',
        ]));
        Storage::disk('local')->put($file->preview_path, 'RIFF....WEBPVP8');

        $this->actingAs($this->member)
            ->get(route('servers.files.thumbnail', [$this->server, $file]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_thumbnail_requires_membership(): void
    {
        Storage::fake('local');
        $outsider = User::factory()->create();
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'path' => 'uploads/report.docx',
            'original_name' => 'report.docx',
            'preview_path' => 'previews/office.png',
            'preview_status' => 'ready',
        ]));
        Storage::disk('local')->put($file->preview_path, "\x89PNG\r\n\x1a\n".'binary');

        $this->actingAs($outsider)
            ->get(route('servers.files.thumbnail', [$this->server, $file]))
            ->assertForbidden();
    }
}
