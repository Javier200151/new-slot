<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sqa_groups') && ! Schema::hasColumn('sqa_groups', 'icon')) {
            Schema::table('sqa_groups', function (Blueprint $table): void {
                $table->string('icon')->nullable();
            });
        }

        if (Schema::hasTable('sqa_group_users') && ! Schema::hasColumn('sqa_group_users', 'coordinator')) {
            Schema::table('sqa_group_users', function (Blueprint $table): void {
                $table->boolean('coordinator')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sqa_group_users') && Schema::hasColumn('sqa_group_users', 'coordinator')) {
            Schema::table('sqa_group_users', function (Blueprint $table): void {
                $table->dropColumn('coordinator');
            });
        }

        if (Schema::hasTable('sqa_groups') && Schema::hasColumn('sqa_groups', 'icon')) {
            Schema::table('sqa_groups', function (Blueprint $table): void {
                $table->dropColumn('icon');
            });
        }
    }
};
