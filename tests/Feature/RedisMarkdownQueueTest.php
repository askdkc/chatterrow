<?php

namespace Tests\Feature;

use App\Jobs\GenerateMarkdownedDoc;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RedisMarkdownQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_redis_queue_worker_processes_markdown_job_to_ready(): void
    {
        if (! filter_var(env('RUN_REDIS_INTEGRATION_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REDIS_INTEGRATION_TESTS=1 to run Redis integration tests.');
        }

        $this->assertTrue(extension_loaded('redis'), 'The PHP Redis extension is required.');
        $this->assertTrue((bool) Redis::connection('default')->ping(), 'Redis did not respond to PING.');

        $cliPath = (string) config('services.markitdown.path', '');
        $this->assertNotSame('', $cliPath, 'The MarkItDown CLI path is not configured.');
        $this->assertFileExists($cliPath, 'The MarkItDown CLI is not installed.');
        $this->assertTrue(
            PHP_OS_FAMILY === 'Windows' || is_executable($cliPath),
            'The MarkItDown CLI is not executable.',
        );

        $queue = 'markdown-integration-'.Str::lower(Str::random(16));
        config([
            'queue.default' => 'redis',
            'queue.connections.redis.queue' => $queue,
        ]);
        Storage::fake('local');
        Storage::fake('markdowned');

        $owner = User::factory()->create();
        $server = Server::factory()->create(['created_by' => $owner->id]);
        $server->members()->attach($owner->id);
        $path = 'uploads/queue-document.pdf';
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $server->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'queue-document.pdf',
            'path' => $path,
            'markdown_status' => 'pending',
        ]));
        Storage::disk('local')->put($path, file_get_contents(base_path('tests/Fixtures/markitdown/document.pdf')));

        // RefreshDatabase keeps the test transaction open; enqueue directly so
        // the worker can consume the job while the application transaction is active.
        GenerateMarkdownedDoc::dispatch($file->id, $path)
            ->onQueue($queue)
            ->beforeCommit();
        Artisan::call('queue:work', [
            'connection' => 'redis',
            '--queue' => $queue,
            '--once' => true,
            '--tries' => 1,
            '--timeout' => 300,
        ]);

        $file->refresh();
        $this->assertSame('ready', $file->markdown_status);
        $this->assertNotNull($file->markdown_path);
        $this->assertDatabaseHas('markdown_doc_contents', ['stored_file_id' => $file->id]);
    }
}
