<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_user', function (Blueprint $table) {
            $table->string('role', 16)->default('member')->after('user_id');
            $table->index(['server_id', 'role']);
        });

        DB::table('server_user')
            ->whereExists(function ($query): void {
                $query
                    ->selectRaw('1')
                    ->from('servers')
                    ->whereColumn('servers.id', 'server_user.server_id')
                    ->whereColumn('servers.created_by', 'server_user.user_id');
            })
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('server_user', function (Blueprint $table) {
            $table->dropIndex(['server_id', 'role']);
            $table->dropColumn('role');
        });
    }
};
