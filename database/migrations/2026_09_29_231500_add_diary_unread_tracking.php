<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'diary_unread_baseline_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('diary_unread_baseline_at')
                    ->useCurrent()
                    ->after('forum_unread_baseline_at');
            });
        }

        // Evita que al desplegar aparezcan como no leídos todos los diarios históricos.
        DB::table('users')->update([
            'diary_unread_baseline_at' => now(),
        ]);

        if (! Schema::hasTable('community_diary_reads')) {
            Schema::create('community_diary_reads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('community_diary_id')
                    ->constrained('community_diaries')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->timestamp('read_at');
                $table->timestamps();

                $table->unique(
                    ['community_diary_id', 'user_id'],
                    'community_diary_reads_diary_user_unique',
                );
                $table->index(
                    ['user_id', 'read_at'],
                    'community_diary_reads_user_read_index',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_diary_reads');

        if (Schema::hasColumn('users', 'diary_unread_baseline_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('diary_unread_baseline_at');
            });
        }
    }
};
