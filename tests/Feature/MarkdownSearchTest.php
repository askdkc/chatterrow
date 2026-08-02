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
            'original_name' => 'メモ.txt',
            'markdown_status' => 'ready',
            'markdown_path' => '2-abc.md',
        ]);

        app(MarkdownSearchIndex::class)->index($file->id, '議事録の要点まとめ');

        $this->actingAs($this->member)
            ->getJson(route('servers.files.search', [$this->server, 'q' => '議事']))
            ->assertOk()
            ->assertJsonPath('results.0.id', $file->id);
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
}
