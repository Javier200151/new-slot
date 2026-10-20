<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FirstMembershipHistoryService;
use App\Services\VeterancyStatusHistoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FirstMembershipHistoryTest extends TestCase
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
            ['id' => 4, 'name' => 'BAJA'],
            ['id' => 5, 'name' => 'NO PROMOCIONADO'],
        ]);
    }

    public function test_backfill_from_member_at_is_idempotent_and_follow_updates(): void
    {
        $service = app(FirstMembershipHistoryService::class);
        $service->sync(100, '2024-03-16', '2024-01-18');
        $service->sync(100, '2024-03-16', '2024-01-18');

        $this->assertSame(1, DB::table('user_status_histories')->where('user_id', 100)->count());
        $this->assertDatabaseHas('user_status_histories', [
            'user_id' => 100,
            'from_status_id' => 2,
            'to_status_id' => 3,
            'source' => FirstMembershipHistoryService::SOURCE,
            'changed_at' => '2024-03-16 00:00:00',
            'changed_by_user_id' => null,
        ]);

        $service->sync(100, '2024-03-17', '2024-01-18');
        $this->assertSame(1, DB::table('user_status_histories')->where('user_id', 100)->count());
        $this->assertDatabaseHas('user_status_histories', [
            'user_id' => 100,
            'changed_at' => '2024-03-17 00:00:00',
        ]);

        $service->sync(100, null, '2024-01-18');
        $this->assertSame(0, DB::table('user_status_histories')->where('user_id', 100)->count());
    }

    public function test_real_promotion_on_same_day_is_not_duplicated(): void
    {
        DB::table('user_status_histories')->insert([
            'user_id' => 101,
            'from_status_id' => 2,
            'to_status_id' => 3,
            'changed_at' => '2024-03-16 20:02:00',
            'source' => 'automatic',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(FirstMembershipHistoryService::class)->sync(101, '2024-03-16', '2024-01-18');
        $this->assertSame(1, DB::table('user_status_histories')->where('user_id', 101)->count());
        $this->assertSame('automatic', DB::table('user_status_histories')->where('user_id', 101)->value('source'));
    }

    public function test_existing_transition_to_active_from_another_status_is_not_overwritten(): void
    {
        DB::table('user_status_histories')->insert([
            'user_id' => 102,
            'from_status_id' => 4,
            'to_status_id' => 3,
            'changed_at' => '2024-03-16 11:00:00',
            'source' => 'historical_import',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(FirstMembershipHistoryService::class)->sync(102, '2024-03-16');
        $this->assertSame(1, DB::table('user_status_histories')->where('user_id', 102)->count());
    }

    public function test_inconsistent_date_does_not_create_a_promotion(): void
    {
        app(FirstMembershipHistoryService::class)->sync(103, '2024-01-01', '2024-02-01');
        $this->assertSame(0, DB::table('user_status_histories')->where('user_id', 103)->count());
    }

    public function test_regular_logger_preserves_baja_and_no_promocionado_reentries(): void
    {
        $user = new User();
        $user->id = 104;
        $user->status_id = 2;
        app(VeterancyStatusHistoryService::class)->handleTransition($user, 4);
        app(VeterancyStatusHistoryService::class)->handleTransition($user, 5);

        $user->status_id = 4;
        app(VeterancyStatusHistoryService::class)->handleTransition($user, 2);

        $this->assertSame(3, DB::table('user_status_histories')->where('user_id', 104)->count());
        $this->assertDatabaseHas('user_status_histories', ['user_id' => 104, 'from_status_id' => 4, 'to_status_id' => 2, 'source' => 'automatic']);
        $this->assertDatabaseHas('user_status_histories', ['user_id' => 104, 'from_status_id' => 5, 'to_status_id' => 2, 'source' => 'automatic']);
        $this->assertDatabaseHas('user_status_histories', ['user_id' => 104, 'from_status_id' => 2, 'to_status_id' => 4, 'source' => 'automatic']);
    }
}
