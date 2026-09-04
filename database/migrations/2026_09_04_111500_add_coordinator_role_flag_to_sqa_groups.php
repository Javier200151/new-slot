<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sqa_groups') && ! Schema::hasColumn('sqa_groups', 'has_coordinator_role')) {
            Schema::table('sqa_groups', function (Blueprint $table): void {
                $table->boolean('has_coordinator_role')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sqa_groups') && Schema::hasColumn('sqa_groups', 'has_coordinator_role')) {
            Schema::table('sqa_groups', function (Blueprint $table): void {
                $table->dropColumn('has_coordinator_role');
            });
        }
    }
};
