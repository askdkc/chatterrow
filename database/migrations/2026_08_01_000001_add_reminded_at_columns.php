<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->timestamp('reminded_at')->nullable()->after('completed_at');
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->timestamp('reminded_at')->nullable()->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->dropColumn('reminded_at');
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropColumn('reminded_at');
        });
    }
};
