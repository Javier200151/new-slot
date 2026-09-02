<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_diary_comments', function (Blueprint $table): void {
            $table->foreignId('community_diary_entry_id')
                ->nullable()
                ->after('community_diary_id')
                ->constrained('community_diary_entries')
                ->cascadeOnDelete();

            $table->index(
                ['community_diary_id', 'community_diary_entry_id', 'created_at'],
                'diary_comments_entry_created_idx',
            );
        });

        /*
         * Los comentarios anteriores se asociaban al diario completo. Para no
         * perder el hilo visual, se vinculan a la entrada más reciente que
         * existía en ese diario cuando se ejecuta la migración.
         */
        DB::table('community_diary_comments')
            ->whereNull('community_diary_entry_id')
            ->orderBy('id')
            ->get(['id', 'community_diary_id'])
            ->each(function (object $comment): void {
                $entryId = DB::table('community_diary_entries')
                    ->where('community_diary_id', $comment->community_diary_id)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->value('id');

                if ($entryId) {
                    DB::table('community_diary_comments')
                        ->where('id', $comment->id)
                        ->update(['community_diary_entry_id' => $entryId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('community_diary_comments', function (Blueprint $table): void {
            $table->dropIndex('diary_comments_entry_created_idx');
            $table->dropConstrainedForeignId('community_diary_entry_id');
        });
    }
};
