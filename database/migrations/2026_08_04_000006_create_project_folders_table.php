<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'position']);
        });

        Schema::table('server_user', function (Blueprint $table): void {
            $table->foreignId('project_folder_id')
                ->nullable()
                ->after('role')
                ->constrained('project_folders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('server_user', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_folder_id');
        });

        Schema::dropIfExists('project_folders');
    }
};
