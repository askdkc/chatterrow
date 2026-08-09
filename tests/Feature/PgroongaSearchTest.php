<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Message;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MarkdownSearchIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('pgroonga')]
class PgroongaSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Server $server;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PGroonga integration tests require PostgreSQL.');
        }

        $hasPgroonga = DB::selectOne(
            "SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pgroonga') AS available",
        );

        if (! in_array(strtolower((string) ($hasPgroonga?->available ?? false)), ['1', 't', 'true'], true)) {
            $this->markTestSkipped('PGroonga is not installed in the PostgreSQL test database.');
        }

        $owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->server = Server::factory()->create(['created_by' => $owner->id]);
        $this->server->members()->attach([$owner->id, $this->member->id]);
        $this->channel = Channel::factory()->create(['server_id' => $this->server->id]);
    }

    public function test_normalized_scripts_and_ascii_internal_matches_are_searchable(): void
    {
        $message = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => '日本語 한국어 中文 PGroonga integration',
        ]);
        $file = StoredFile::factory()->create([
            'server_id' => $this->server->id,
            'uploaded_by' => $this->member->id,
            'original_name' => 'pgroonga-notes.pdf',
            'markdown_status' => 'ready',
            'markdown_path' => 'pgroonga-notes.md',
            'attachable_type' => Message::class,
            'attachable_id' => $message->id,
        ]);

        app(MarkdownSearchIndex::class)->index(
            $file->id,
            '日本語 한국어 中文 PGroonga document integration',
        );

        foreach (['日本語', '한국어', '中文', 'roonga'] as $term) {
            $results = $this->actingAs($this->member)
                ->getJson(route('search', ['q' => $term]))
                ->assertOk()
                ->json('results');

            $this->assertEqualsCanonicalizing(
                [$message->id, $file->id],
                collect($results)->pluck('id')->all(),
                "Expected PGroonga to find both records for {$term}.",
            );
        }
    }

    public function test_multiple_terms_use_and_semantics_and_indexes_are_present(): void
    {
        $matching = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => 'alpha beta',
        ]);
        Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => 'alpha only',
        ]);

        $results = $this->actingAs($this->member)
            ->getJson(route('search', ['q' => 'alpha beta']))
            ->assertOk()
            ->json('results');

        $this->assertSame([$matching->id], collect($results)->pluck('id')->all());

        $indexes = DB::select(
            <<<'SQL'
            SELECT
                index_class.relname AS index_name,
                access_method.amname AS access_method,
                operator_class.opcname AS operator_class,
                pg_get_indexdef(index_class.oid) AS definition
            FROM pg_class AS index_class
            JOIN pg_index AS index_metadata ON index_metadata.indexrelid = index_class.oid
            JOIN pg_am AS access_method ON access_method.oid = index_class.relam
            JOIN pg_opclass AS operator_class ON operator_class.oid = ANY(index_metadata.indclass)
            WHERE index_class.relname IN (?, ?)
            SQL,
            [
                'messages_body_pgroonga_idx',
                'markdown_doc_contents_content_pgroonga_idx',
            ],
        );

        $this->assertCount(2, $indexes);
        foreach ($indexes as $index) {
            $this->assertSame('pgroonga', $index->access_method);
            $this->assertSame('pgroonga_text_full_text_search_ops_v2', $index->operator_class);
            $this->assertStringContainsString('tokenn', strtolower((string) $index->definition));
        }
    }

    public function test_special_characters_are_literal_keywords(): void
    {
        $message = Message::factory()->create([
            'server_id' => $this->server->id,
            'channel_id' => $this->channel->id,
            'body' => 'literal % marker _ slash \\ hyphen - colon :',
        ]);

        foreach (['%', '_', '\\', '-', ':'] as $term) {
            $results = $this->actingAs($this->member)
                ->getJson(route('search', ['q' => $term]))
                ->assertOk()
                ->json('results');

            $this->assertSame(
                [$message->id],
                collect($results)->pluck('id')->all(),
                "Expected {$term} to be treated as a literal PGroonga keyword.",
            );
        }
    }

    public function test_diagnostic_plan_can_use_the_message_pgroonga_index(): void
    {
        $plan = DB::transaction(function (): string {
            DB::statement('SET LOCAL enable_seqscan = off');

            $row = DB::selectOne(
                'EXPLAIN (FORMAT JSON) SELECT id FROM messages WHERE body &@ ?',
                ['roonga'],
            );

            return json_encode($row, JSON_THROW_ON_ERROR);
        });

        $this->assertStringContainsString('messages_body_pgroonga_idx', strtolower($plan));
    }
}
