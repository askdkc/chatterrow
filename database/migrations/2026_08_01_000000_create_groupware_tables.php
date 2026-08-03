<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Prototype consolidation: the groupware schema (servers, channels,
     * messages, todos, stored_files + markdown FTS search) lives in one
     * migration so the full schema is visible in a single file.
     */
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('server_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['server_id', 'user_id']);
        });

        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'name']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('messages')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_reminder')->default(false);
            $table->string('reminder_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['channel_id', 'parent_id', 'id']);
        });

        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('details')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('due_timezone', 64)->default('UTC');
            $table->string('priority')->default('normal');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('stored_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('attachable');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('preview_path')->nullable();
            $table->string('preview_status')->nullable();
            $table->string('markdown_path')->nullable();
            $table->string('markdown_status')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'created_at']);
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            // FTS5 trigram index: matches substrings of 3+ characters (Japanese friendly).
            DB::statement('CREATE VIRTUAL TABLE markdown_docs USING fts5(content, tokenize = "trigram")');
        }

        // Portable content copy for short queries, non-SQLite databases, and future AI ingestion.
        Schema::create('markdown_doc_contents', function (Blueprint $table): void {
            $table->unsignedBigInteger('stored_file_id')->primary();
            $table->longText('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('markdown_doc_contents');
        DB::statement('DROP TABLE IF EXISTS markdown_docs');
        Schema::dropIfExists('stored_files');
        Schema::dropIfExists('todos');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('channels');
        Schema::dropIfExists('server_user');
        Schema::dropIfExists('servers');
    }
};
