<?php

use App\Models\ForumCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_forum_categories')) {
            return;
        }

        if (! Schema::hasColumn('community_forum_categories', 'allow_polls')) {
            Schema::table('community_forum_categories', function (Blueprint $table): void {
                $table->boolean('allow_polls')->default(false)->after('is_enabled');
            });
        }

        if (DB::connection()->pretending()) {
            return;
        }

        $diaryId = DB::table('community_forum_categories')
            ->where(function ($query): void {
                $query->where('slug', 'diario')
                    ->orWhere('system_type', ForumCategory::TYPE_DIARY);
            })
            ->value('id');

        DB::table('community_forum_categories')
            ->when($diaryId, fn ($query) => $query->where('id', '!=', $diaryId))
            ->update([
                'channel' => 'personal',
                'system_type' => ForumCategory::TYPE_STANDARD,
                'is_system' => false,
                'allow_polls' => true,
            ]);


        if (Schema::hasTable('community_posts') && Schema::hasColumn('community_posts', 'forum_category_id')) {
            $normalCategoryIds = DB::table('community_forum_categories')
                ->when($diaryId, fn ($query) => $query->where('id', '!=', $diaryId))
                ->pluck('id');

            DB::table('community_posts')
                ->whereIn('forum_category_id', $normalCategoryIds)
                ->update(['channel' => 'personal']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('community_forum_categories') && Schema::hasColumn('community_forum_categories', 'allow_polls')) {
            Schema::table('community_forum_categories', function (Blueprint $table): void {
                $table->dropColumn('allow_polls');
            });
        }
    }
};
