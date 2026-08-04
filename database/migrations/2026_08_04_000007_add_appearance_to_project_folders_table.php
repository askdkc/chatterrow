<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_folders', function (Blueprint $table): void {
            $table->string('color', 7)->default('#5865F2')->after('name');
            $table->string('icon_path')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('project_folders', function (Blueprint $table): void {
            $table->dropColumn(['color', 'icon_path']);
        });
    }
};
