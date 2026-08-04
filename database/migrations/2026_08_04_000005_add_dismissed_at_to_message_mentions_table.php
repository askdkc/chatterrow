<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_mentions', function (Blueprint $table): void {
            $table->timestamp('dismissed_at')->nullable()->after('read_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('message_mentions', function (Blueprint $table): void {
            $table->dropColumn('dismissed_at');
        });
    }
};
