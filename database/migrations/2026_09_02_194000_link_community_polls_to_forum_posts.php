<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('community_polls', 'community_post_id')) {
            Schema::table('community_polls', function (Blueprint $table): void {
                $table->foreignId('community_post_id')
                    ->nullable()
                    ->unique('community_polls_community_post_id_unique')
                    ->after('community_process_id')
                    ->constrained('community_posts')
                    ->nullOnDelete();
            });
        }

        // Every existing poll becomes part of Personal. Process polls reuse the
        // process thread; legacy standalone polls get a forum thread once.
        DB::table('community_polls')
            ->whereNull('community_post_id')
            ->orderBy('id')
            ->get()
            ->each(function ($poll): void {
                $postId = null;

                if ($poll->community_process_id) {
                    $postId = DB::table('community_posts')
                        ->where('community_process_id', $poll->community_process_id)
                        ->value('id');
                }

                if (! $postId) {
                    $postId = DB::table('community_posts')->insertGetId([
                        'channel' => 'personal',
                        'community_process_id' => null,
                        'user_id' => $poll->created_by,
                        'title' => $poll->title,
                        'body' => $poll->description ?: 'Votación comunitaria.',
                        'is_pinned' => false,
                        'is_locked' => false,
                        'locked_at' => null,
                        'locked_by' => null,
                        'created_at' => $poll->created_at ?? now(),
                        'updated_at' => $poll->updated_at ?? now(),
                        'deleted_at' => $poll->deleted_at,
                    ]);
                }

                DB::table('community_polls')
                    ->where('id', $poll->id)
                    ->update(['community_post_id' => $postId]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('community_polls', 'community_post_id')) {
            Schema::table('community_polls', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('community_post_id');
            });
        }
    }
};
