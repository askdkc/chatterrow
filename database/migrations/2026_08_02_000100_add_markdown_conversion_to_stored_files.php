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
            $table->string('markdown_path')->nullable()->after('preview_status');
            $table->string('markdown_status')->nullable()->after('markdown_path');
        });

        // FTS5 trigram index: matches substrings of 3+ characters (Japanese friendly).
        DB::statement('CREATE VIRTUAL TABLE markdown_docs USING fts5(content, tokenize = "trigram")');

        // Plain content copy for short (1-2 char) LIKE fallback and future AI ingestion.
        Schema::create('markdown_doc_contents', function (Blueprint $table): void {
            $table->unsignedBigInteger('stored_file_id')->primary();
            $table->longText('content');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markdown_doc_contents');
        DB::statement('DROP TABLE IF EXISTS markdown_docs');

        Schema::table('stored_files', function (Blueprint $table): void {
            $table->dropColumn(['markdown_path', 'markdown_status']);
        });
    }
};
