<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_files', function (Blueprint $table): void {
            $table->index(
                ['server_id', 'markdown_status', 'created_at'],
                'stored_files_markdown_search_index',
            );
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(
            'CREATE INDEX markdown_doc_contents_content_trgm_index '
            .'ON markdown_doc_contents USING gin (content gin_trgm_ops)',
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS markdown_doc_contents_content_trgm_index');
        }

        Schema::table('stored_files', function (Blueprint $table): void {
            $table->dropIndex('stored_files_markdown_search_index');
        });
    }
};
