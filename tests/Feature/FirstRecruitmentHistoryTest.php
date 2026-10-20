<?php

namespace Tests\Feature;

use App\Services\FirstRecruitmentHistoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FirstRecruitmentHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('user_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('from_status_id')->nullable();
            $table->unsignedBigInteger('to_status_id');
            $table->dateTime('changed_at');
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('source');
            $table->string('source_hash')->nullable()->unique();
            $table->string('retutored_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
        DB::table('status')->insert([
            ['id' => 1, 'name' => 'USUARIO'],
            ['id' => 2, 'name' => 'RECLUTA'],
            ['id' => 3, 'name' => 'ACTIVO'],
        ]);
    }

    public function test_documented_first_recruitment_creates_one_historical_transition_without_duplicates(): void
    {
        $service = app(FirstRecruitmentHistoryService::class);

        $service->sync(24, '2023-09-19', '2023-10-17');
        $service->sync(24, '2023-09-19', '2023-10-17');

        $this->assertSame(1, DB::table('user_status_histories')->where('user_id', 24)->count());
        $this->assertDatabaseHas('user_status_histories', [
            'user_id' => 24,
            'from_status_id' => 1,
            'to_status_id' => 2,
            'source' => FirstRecruitmentHistoryService::SOURCE,
            'changed_at' => '2023-09-19 00:00:00',
            'changed_by_user_id' => null,
        ]);

        $service->sync(24, '2023-09-18', '2023-10-17');
        $this->assertSame(1, DB::table('user_status_histories')->where('user_id', 24)->count());
        $this->assertDatabaseHas('user_status_histories', ['user_id' => 24, 'changed_at' => '2023-09-18 00:00:00']);

        $service->sync(24, null, '2023-10-17');
        $this->assertSame(0, DB::table('user_status_histories')->where('user_id', 24)->count());
    }

    public function test_does_not_duplicate_a_real_user_to_recruit_change_on_the_same_date(): void
    {
        DB::table('user_status_histories')->insert([
            'user_id' => 25,
            'from_status_id' => 1,
            'to_status_id' => 2,
            'changed_at' => '2026-09-19 12:05:00',
            'source' => 'automatic',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(FirstRecruitmentHistoryService::class)->sync(25, '2026-09-19', null);

        $this->assertSame(1, DB::table('user_status_histories')->where('user_id', 25)->count());
        $this->assertSame('automatic', DB::table('user_status_histories')->where('user_id', 25)->value('source'));
    }

    public function test_date_after_membership_is_not_added_to_state_history(): void
    {
        app(FirstRecruitmentHistoryService::class)->sync(26, '2025-01-01', '2024-01-01');

        $this->assertSame(0, DB::table('user_status_histories')->where('user_id', 26)->count());
    }
}
