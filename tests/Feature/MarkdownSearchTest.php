<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownSearchIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkdownSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private User $outsider;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->server = Server::factory()->create(['created_by' => $this->owner->id]);
        $this->server->members()->attach([$this->owner->id, $this->member->id]);
    }

    public function test_full_text_search_returns_matching_files_with_snippet(): void
    {
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => '仕様書.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => '1-abc.md',
        ]);

        app(MarkdownSearchIndex::class)->index($file->id, "これは日本語の全文検索テスト文書です\nmarkdown conversion sample");

        $this->actingAs($this->member)
            ->getJson(route('servers.files.search', [$this->server, 'q' => '全文検索']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $file->id)
            ->assertJsonPath('results.0.original_name', '仕様書.pdf')
            ->assertJsonPath('results.0.snippet', fn (string $snippet) => str_contains($snippet, '<mark>'));

        $this->actingAs($this->member)
            ->getJson(route('servers.files.search', [$this->server, 'q' => 'markdown conversion']))
            ->assertOk()
            ->assertJsonCount(1, 'results');
    }

    public function test_short_queries_fall_back_to_like(): void
    {
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'メモ.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => '2-abc.md',
        ]);

        app(MarkdownSearchIndex::class)->index($file->id, '議事録の要点まとめ');

        $this->actingAs($this->member)
            ->getJson(route('servers.files.search', [$this->server, 'q' => '議事']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $file->id);
    }

    public function test_indexed_content_is_hidden_until_markdown_is_ready(): void
    {
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'processing.pdf',
            'markdown_status' => 'processing',
            'markdown_path' => null,
        ]));

        app(MarkdownSearchIndex::class)->index($file->id, '本文はまだ公開されていません');

        $this->assertCount(0, app(MarkdownSearchIndex::class)->search($this->server->id, '公開'));

        $file->update(['markdown_status' => 'ready', 'markdown_path' => 'processing.md']);

        $this->assertCount(1, app(MarkdownSearchIndex::class)->search($this->server->id, '公開'));
    }

    public function test_non_sqlite_fallback_searches_long_queries_without_fts(): void
    {
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'portable.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => 'portable.md',
        ]);
        $index = new class extends MarkdownSearchIndex
        {
            protected function usesSqliteFts(): bool
            {
                return false;
            }
        };

        $index->index($file->id, 'Portable database search works without SQLite FTS.');

        $results = $index->search($this->server->id, 'database search');

        $this->assertCount(1, $results);
        $this->assertSame($file->id, $results->first()->id);
        $this->assertStringContainsString('<mark>database search</mark>', $results->first()->snippet);

        $index->remove($file->id);

        $this->assertCount(0, $index->search($this->server->id, 'database search'));
    }

    public function test_like_fallback_escapes_wildcards_and_limits_snippets(): void
    {
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => 'large.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => 'large.md',
        ]);
        $content = str_repeat('a', 50_000).' 50% complete '.str_repeat('b', 50_000);
        $index = app(MarkdownSearchIndex::class);
        $index->index($file->id, $content);

        $this->assertCount(0, $index->search($this->server->id, '_'));

        $results = $index->search($this->server->id, '%');

        $this->assertCount(1, $results);
        $this->assertStringContainsString('<mark>%</mark>', $results->first()->snippet);
        $this->assertLessThan(500, strlen($results->first()->snippet));
    }

    public function test_search_never_leaks_files_from_other_servers(): void
    {
        $other = Server::factory()->create(['created_by' => $this->owner->id]);
        $other->members()->attach($this->owner->id);

        $file = StoredFile::factory()->create([
            'server_id' => $other->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => '他サーバー.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => '3-abc.md',
        ]);

        app(MarkdownSearchIndex::class)->index($file->id, '秘密の内容');

        $this->actingAs($this->member)
            ->getJson(route('servers.files.search', [$this->server, 'q' => '秘密']))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_deleting_a_file_removes_it_from_the_index(): void
    {
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->owner->id,
            'original_name' => '削除対象.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => '4-abc.md',
        ]);

        app(MarkdownSearchIndex::class)->index($file->id, '削除される内容');
        $file->delete();

        $this->actingAs($this->member)
            ->getJson(route('servers.files.search', [$this->server, 'q' => '削除される内容']))
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_outsiders_cannot_search(): void
    {
        $this->actingAs($this->outsider)
            ->getJson(route('servers.files.search', [$this->server, 'q' => 'anything']))
            ->assertForbidden();
    }

    public function test_file_search_enforces_the_shared_term_limit(): void
    {
        $query = implode(' ', array_fill(0, 13, 'term'));

        $this->actingAs($this->member)
            ->getJson(route('servers.files.search', [$this->server, 'q' => $query]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }
}
