<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownedDocGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarkItDownSmokeTest extends TestCase
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

        $cliPath = (string) config('services.markitdown.path', '');
        $fixtureDirectory = base_path('tests/Fixtures/markitdown');
        $fixtures = ['document.pdf', 'document.docx', 'document.xlsx', 'document.pptx'];

        if ($cliPath === '' || ! is_file($cliPath) || (PHP_OS_FAMILY !== 'Windows' && ! is_executable($cliPath))) {
            $this->markTestSkipped('The MarkItDown CLI is not installed.');
        }

        foreach ($fixtures as $fixture) {
            if (! is_file($fixtureDirectory.'/'.$fixture)) {
                $this->markTestSkipped('MarkItDown fixtures have not been generated.');
            }
        }

        Storage::fake('local');
        Storage::fake('markdowned');
    }

    public function test_markitdown_converts_all_supported_fixture_formats(): void
    {
        $fixtureDirectory = base_path('tests/Fixtures/markitdown');

        foreach (['pdf', 'docx', 'xlsx', 'pptx'] as $extension) {
            $name = "document.{$extension}";
            $path = "uploads/{$name}";
            $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
                'server_id' => $this->server->id,
                'uploaded_by' => $this->owner->id,
                'original_name' => $name,
                'path' => $path,
            ]));

            Storage::disk('local')->put($path, file_get_contents($fixtureDirectory.'/'.$name));

            $markdownPath = app(MarkdownedDocGenerator::class)->generate($file);
            $markdown = Storage::disk('markdowned')->get($markdownPath);

            $this->assertStringContainsString('conversion fixture', strtolower($markdown));
        }
    }
}
