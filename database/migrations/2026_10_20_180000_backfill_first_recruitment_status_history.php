<?php

use App\Services\FirstRecruitmentHistoryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_status_histories')
            || ! Schema::hasColumn('users', 'recruited_at')
            || ! Schema::hasTable('status')) {
            return;
        }

        $service = app(FirstRecruitmentHistoryService::class);
        DB::table('users')
            ->whereNotNull('recruited_at')
            ->select(['id', 'recruited_at', 'member_at'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($service): void {
                foreach ($users as $user) {
                    $service->sync(
                        (int) $user->id,
                        substr((string) $user->recruited_at, 0, 10),
                        $user->member_at ? substr((string) $user->member_at, 0, 10) : null,
                    );
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('user_status_histories')) {
            DB::table('user_status_histories')
                ->where('source', FirstRecruitmentHistoryService::SOURCE)
                ->delete();
        }
    }
};
