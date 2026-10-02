<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('veterancy_awards')) {
            return;
        }

        if (! Schema::hasColumn('veterancy_awards', 'earned_at')) {
            Schema::table('veterancy_awards', function (Blueprint $table): void {
                $table->dateTime('earned_at')->nullable()->after('effective_days');
                $table->index(['level', 'earned_at'], 'va_level_earned_idx');
            });
        }

        DB::table('veterancy_awards')
            ->whereNull('earned_at')
            ->update([
                'earned_at' => DB::raw('approved_at'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('veterancy_awards') || ! Schema::hasColumn('veterancy_awards', 'earned_at')) {
            return;
        }

        Schema::table('veterancy_awards', function (Blueprint $table): void {
            $table->dropIndex('va_level_earned_idx');
            $table->dropColumn('earned_at');
        });
    }
};
