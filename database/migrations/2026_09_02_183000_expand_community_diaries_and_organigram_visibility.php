<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_diaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('author_nick', 255);
            $table->timestamps();
        });

        Schema::table('community_diary_entries', function (Blueprint $table): void {
            $table->foreignId('community_diary_id')
                ->nullable()
                ->after('id')
                ->constrained('community_diaries')
                ->cascadeOnDelete();
        });

        $userIds = DB::table('community_diary_entries')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = DB::table('users')->where('id', $userId)->first();

            if (! $user) {
                continue;
            }

            $latestUpdatedAt = DB::table('community_diary_entries')
                ->where('user_id', $userId)
                ->max('updated_at');

            $createdAt = DB::table('community_diary_entries')
                ->where('user_id', $userId)
                ->min('created_at');

            $diaryId = DB::table('community_diaries')->insertGetId([
                'user_id' => $userId,
                'author_nick' => $user->nick,
                'created_at' => $createdAt ?: now(),
                'updated_at' => $latestUpdatedAt ?: now(),
            ]);

            DB::table('community_diary_entries')
                ->where('user_id', $userId)
                ->update(['community_diary_id' => $diaryId]);
        }

        Schema::table('community_diary_entries', function (Blueprint $table): void {
            $table->foreignId('community_diary_id')->nullable(false)->change();
        });

        Schema::create('community_diary_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_diary_id')
                ->constrained('community_diaries')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('sqa_groups', function (Blueprint $table): void {
            $table->boolean('show_in_organization')
                ->default(true)
                ->after('display_order');
        });
    }

    public function down(): void
    {
        Schema::table('sqa_groups', function (Blueprint $table): void {
            $table->dropColumn('show_in_organization');
        });

        Schema::dropIfExists('community_diary_comments');

        Schema::table('community_diary_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('community_diary_id');
        });

        Schema::dropIfExists('community_diaries');
    }
};
