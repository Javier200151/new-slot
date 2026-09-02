<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('community_diary_entries', 'squad_group')) {
            Schema::table('community_diary_entries', function (Blueprint $table): void {
                $table->string('squad_group', 180)
                    ->nullable()
                    ->after('content');
            });
        }

        if (! Schema::hasColumn('community_diary_entries', 'squad_roster')) {
            Schema::table('community_diary_entries', function (Blueprint $table): void {
                $table->json('squad_roster')
                    ->nullable()
                    ->after('squad_group');
            });
        }
    }

    public function down(): void
    {
        $columns = [];

        if (Schema::hasColumn('community_diary_entries', 'squad_group')) {
            $columns[] = 'squad_group';
        }

        if (Schema::hasColumn('community_diary_entries', 'squad_roster')) {
            $columns[] = 'squad_roster';
        }

        if ($columns !== []) {
            Schema::table('community_diary_entries', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
