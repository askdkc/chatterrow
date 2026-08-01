<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
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
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
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
            $table->timestamps();

            $table->index(['server_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stored_files');
        Schema::dropIfExists('todos');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('channels');
        Schema::dropIfExists('server_user');
        Schema::dropIfExists('servers');
    }
};
