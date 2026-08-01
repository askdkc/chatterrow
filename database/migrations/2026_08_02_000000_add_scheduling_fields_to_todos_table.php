<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->dateTime('starts_at')->nullable()->after('details');
            $table->dateTime('due_at')->nullable()->after('starts_at');
            $table->string('priority')->default('normal')->after('due_at');
        });

        DB::table('todos')
            ->select(['id', 'due_on'])
            ->whereNotNull('due_on')
            ->orderBy('id')
            ->eachById(function (object $todo): void {
                DB::table('todos')
                    ->where('id', $todo->id)
                    ->update([
                        'due_at' => Carbon::parse($todo->due_on)
                            ->endOfDay()
                            ->format('Y-m-d H:i:s'),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'due_at', 'priority']);
        });
    }
};
