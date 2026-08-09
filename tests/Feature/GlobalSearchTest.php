<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownSearchIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Server $server;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->server = Server::factory()->create(['created_by' => $owner->id]);
        $this->server->members()->attach([$owner->id, $this->member->id]);
        $this->channel = Channel::factory()->create(['server_id' => $this->server->id]);
    }

    public function test_search_spans_memberships_and_returns_structured_results(): void
    {
        $message = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'user_id' => $this->member->id,
            'body' => '日本語 한국어 中文 PGroonga',
        ]);
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->member->id,
            'original_name' => 'search-notes.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => 'search-notes.md',
            'attachable_type' => Message::class,
            'attachable_id' => $message->id,
        ]);

        app(MarkdownSearchIndex::class)->index($file->id, '日本語 한국어 中文 PGroonga notes');

        $otherServer = Server::factory()->create(['created_by' => $this->member->id]);
        $otherServer->members()->attach($this->member->id);
        $otherChannel = Channel::factory()->create(['server_id' => $otherServer->id]);
        $otherMessage = Message::factory()->create([
            'server_id' => $otherServer->id,
            'channel_id' => $otherChannel->id,
            'user_id' => $this->member->id,
            'body' => 'PGroonga from another project',
        ]);
        $otherFile = StoredFile::factory()->create([
            'server_id' => $otherServer->id,
            'uploaded_by' => $this->member->id,
            'original_name' => 'other-search-notes.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => 'other-search-notes.md',
            'attachable_type' => Message::class,
            'attachable_id' => $otherMessage->id,
        ]);

        app(MarkdownSearchIndex::class)->index($otherFile->id, 'PGroonga from another project');

        $response = $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'PGroonga']));

        $response->assertOk()->assertJsonPath('meta.terms_count', 1);

        $results = collect($response->json('results'));

        $this->assertEqualsCanonicalizing(
            [$message->id, $file->id, $otherMessage->id, $otherFile->id],
            $results->pluck('id')->all(),
        );
        $messageResult = $results->first(
            fn (array $result): bool => $result['type'] === 'message' && $result['id'] === $message->id,
        );
        $fileResult = $results->first(
            fn (array $result): bool => $result['type'] === 'file' && $result['id'] === $file->id,
        );

        $this->assertNotNull($messageResult);
        $this->assertNotNull($fileResult);
        $this->assertSame(
            ['type' => 'hit', 'text' => 'PGroonga'],
            collect($messageResult['snippet'])->firstWhere('type', 'hit'),
        );
        $this->assertSame($this->channel->id, $fileResult['channel']['id']);
        $this->assertStringContainsString('?message='.$message->id, $messageResult['url']);
    }

    public function test_non_membership_results_are_not_returned_and_archived_memberships_remain_visible(): void
    {
        $archived = Server::factory()->create(['archived_at' => now()]);
        $archived->members()->attach($this->member->id);
        $archivedChannel = Channel::factory()->create(['server_id' => $archived->id]);
        $archivedMessage = Message::factory()->create([
            'server_id' => $archived->id,
            'channel_id' => $archivedChannel->id,
            'body' => 'archived membership result',
        ]);

        $other = Server::factory()->create();
        $otherChannel = Channel::factory()->create(['server_id' => $other->id]);
        $outsiderMessage = Message::factory()->create([
            'server_id' => $other->id,
            'channel_id' => $otherChannel->id,
            'body' => 'archived membership result',
        ]);
        $outsiderFile = StoredFile::factory()->create([
            'server_id' => $other->id,
            'original_name' => 'outsider.md',
            'markdown_status' => 'ready',
            'markdown_path' => 'outsider.md',
        ]);
        app(MarkdownSearchIndex::class)->index($outsiderFile->id, 'archived membership result');

        $results = $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'archived membership']))
            ->assertOk()
            ->json('results');

        $this->assertTrue(collect($results)->contains(
            fn (array $result): bool => $result['type'] === 'message' && $result['id'] === $archivedMessage->id,
        ));
        $this->assertFalse(collect($results)->contains(
            fn (array $result): bool => $result['type'] === 'message' && $result['id'] === $outsiderMessage->id,
        ));
        $this->assertFalse(collect($results)->contains(
            fn (array $result): bool => $result['type'] === 'file' && $result['id'] === $outsiderFile->id,
        ));
    }

    public function test_terms_are_anded_and_wildcards_are_literal(): void
    {
        $both = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => 'alpha beta',
        ]);
        Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => 'alpha only',
        ]);
        $percent = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => 'literal % marker',
        ]);

        $andResults = $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'alpha beta']))
            ->assertOk()
            ->json('results');
        $percentResults = $this->actingAs($this->member)
            ->getJson(route('search', ['q' => '%']))
            ->assertOk()
            ->json('results');

        $this->assertSame([$both->id], collect($andResults)->pluck('id')->all());
        $this->assertSame([$percent->id], collect($percentResults)->pluck('id')->all());
    }

    public function test_empty_queries_do_not_search_everything_and_query_limits_are_enforced(): void
    {
        Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => 'something searchable',
        ]);

        $this->actingAs($this->member)
            ->getJson(route('search', ['q' => '   ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');

        $this->actingAs($this->member)
            ->getJson(route('search', ['q' => implode(' ', array_fill(0, 13, 'word'))]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_message_updates_and_deletes_are_reflected_by_sqlite_fts(): void
    {
        $message = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => 'before update',
        ]);

        $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'before']))
            ->assertJsonFragment(['message_id' => $message->id]);

        $message->update(['body' => 'after update']);

        $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'before']))
            ->assertJsonMissing(['message_id' => $message->id]);
        $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'after']))
            ->assertJsonFragment(['message_id' => $message->id]);

        $message->delete();

        $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'after']))
            ->assertJsonMissing(['message_id' => $message->id]);
    }

    public function test_only_ready_markdown_documents_are_visible_to_global_search(): void
    {
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->member->id,
            'original_name' => 'processing.pdf',
            'markdown_status' => 'processing',
            'markdown_path' => null,
        ]));

        app(MarkdownSearchIndex::class)->index($file->id, 'conversion is not ready');

        $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'conversion']))
            ->assertOk()
            ->assertJsonCount(0, 'results');

        $file->update(['markdown_status' => 'ready', 'markdown_path' => 'processing.md']);

        $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'conversion']))
            ->assertJsonFragment(['stored_file_id' => $file->id]);
    }

    public function test_snippets_are_data_not_untrusted_html(): void
    {
        Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => '<script>alert(1)</script> safe search',
        ]);

        $snippet = $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'search']))
            ->assertOk()
            ->json('results.0.snippet');

        $this->assertIsArray($snippet);
        $this->assertStringNotContainsString('<script>', json_encode($snippet, JSON_THROW_ON_ERROR));
    }
}
