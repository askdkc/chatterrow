<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->createSqliteMessageIndex();

            return;
        }

        if ($driver !== 'pgsql') {
            return;
        }

        $extension = DB::selectOne(
            "SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pgroonga') AS available",
        );

        if (! in_array(
            strtolower((string) ($extension->available ?? false)),
            ['1', 't', 'true', 'y', 'yes', 'on'],
            true,
        )) {
            throw new RuntimeException(
                'PGroonga is required before running the global-search migration. Install it and run CREATE EXTENSION pgroonga as a privileged database operation.',
            );
        }

        DB::statement(
            'CREATE INDEX IF NOT EXISTS messages_body_pgroonga_idx '
            .'ON messages USING pgroonga '
            .'(body pgroonga_text_full_text_search_ops_v2) '
            .'WITH (tokenizer = \'TokenNgram("unify_alphabet", false, "unify_symbol", false, "unify_digit", false)\')',
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS markdown_doc_contents_content_pgroonga_idx '
            .'ON markdown_doc_contents USING pgroonga '
            .'(content pgroonga_text_full_text_search_ops_v2) '
            .'WITH (tokenizer = \'TokenNgram("unify_alphabet", false, "unify_symbol", false, "unify_digit", false)\')',
        );

        // The forward migration above is the cutover point from the old
        // substring index. Keep pg_trgm installed because other applications
        // may share the extension.
        DB::statement('DROP INDEX IF EXISTS markdown_doc_contents_content_trgm_index');
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS messages_fts_ai');
            DB::statement('DROP TRIGGER IF EXISTS messages_fts_au');
            DB::statement('DROP TRIGGER IF EXISTS messages_fts_ad');
            DB::statement('DROP TABLE IF EXISTS messages_fts');

            return;
        }

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS messages_body_pgroonga_idx');
        DB::statement('DROP INDEX IF EXISTS markdown_doc_contents_content_pgroonga_idx');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(
            'CREATE INDEX IF NOT EXISTS markdown_doc_contents_content_trgm_index '
            .'ON markdown_doc_contents USING gin (content gin_trgm_ops)',
        );
    }

    private function createSqliteMessageIndex(): void
    {
        DB::statement(
            "CREATE VIRTUAL TABLE messages_fts USING fts5(body, content='messages', content_rowid='id', tokenize='trigram')",
        );
        DB::statement('INSERT INTO messages_fts(rowid, body) SELECT id, body FROM messages');

        DB::statement(
            'CREATE TRIGGER messages_fts_ai AFTER INSERT ON messages BEGIN '
            .'INSERT INTO messages_fts(rowid, body) VALUES (new.id, new.body); END',
        );
        DB::statement(
            'CREATE TRIGGER messages_fts_au AFTER UPDATE OF body ON messages BEGIN '
            ."INSERT INTO messages_fts(messages_fts, rowid, body) VALUES ('delete', old.id, old.body); "
            .'INSERT INTO messages_fts(rowid, body) VALUES (new.id, new.body); END',
        );
        DB::statement(
            'CREATE TRIGGER messages_fts_ad AFTER DELETE ON messages BEGIN '
            ."INSERT INTO messages_fts(messages_fts, rowid, body) VALUES ('delete', old.id, old.body); END",
        );
    }
};
