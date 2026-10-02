<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VeterancyHistoryImportService;
use App\Services\VeterancyService;
use App\Services\VeterancyStatusHistoryService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VeterancySystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();

        DB::table('status')->insert([
            ['id' => 1, 'name' => 'RECLUTA', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'ACTIVO', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'RESERVA', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'CESADO', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_first_recruit_to_active_transition_sets_member_at_once_and_records_history(): void
    {
        Carbon::setTestNow('2026-03-18 12:00:00');

        $this->insertUser(10, 'Nuevo', 2, null);
        $user = User::query()->findOrFail(10);

        app(VeterancyStatusHistoryService::class)->handleTransition($user, 1);

        $user->refresh();
        $this->assertSame('2026-03-18', $user->member_at?->toDateString());
        $this->assertDatabaseHas('user_status_histories', [
            'user_id' => 10,
            'from_status_id' => 1,
            'to_status_id' => 2,
            'source' => 'automatic',
        ]);

        // Una reincorporación posterior no puede reescribir el ingreso original.
        $user->forceFill(['status_id' => 2])->saveQuietly();
        app(VeterancyStatusHistoryService::class)->handleTransition($user, 3);
        $this->assertSame('2026-03-18', $user->fresh()->member_at?->toDateString());
    }

    public function test_effective_time_subtracts_reserve_intervals(): void
    {
        $this->insertUser(11, 'Veterano', 2, '2020-01-01');

        DB::table('user_status_histories')->insert([
            [
                'user_id' => 11,
                'from_status_id' => 2,
                'to_status_id' => 3,
                'changed_at' => '2020-06-01 00:00:00',
                'source' => 'historical_import',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => 11,
                'from_status_id' => 3,
                'to_status_id' => 2,
                'changed_at' => '2020-07-01 00:00:00',
                'source' => 'historical_import',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $user = User::query()->with('status')->findOrFail(11);

        $summary = app(VeterancyService::class)->summary(
            $user,
            Carbon::parse('2021-01-01'),
        );

        $this->assertSame(336, $summary['effective_days']);
        $this->assertSame(30, $summary['reserve_days']);
        $this->assertNull($summary['eligible_level']);
        $this->assertSame('2021-01-31', app(VeterancyService::class)->reachedAt($user, VeterancyService::BRONZE, Carbon::parse('2021-02-10'))?->toDateString());
    }

    public function test_historical_import_is_chronological_and_idempotent(): void
    {
        $this->insertUser(12, 'Gilfor', 2, '2014-01-01');

        $path = tempnam(sys_get_temp_dir(), 'veterancy-') . '.csv';
        file_put_contents($path, implode("\n", [
            'NOMBRE,FECHA,ESTADO,RE-TUTO POR',
            // Deliberadamente desordenado: el importador debe ordenar por fecha.
            'Gilfor,2015-06-02,Pasa a activo,Cantero',
            'Gilfor,2014-04-16,Pasa a reserva,',
            'Gilfor,2013-12-01,Pasa a reserva,',
        ]));

        try {
            $service = app(VeterancyHistoryImportService::class);
            $first = $service->import($path);
            $second = $service->import($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(2, $first['imported']);
        $this->assertSame(1, $first['before_member_at']);
        $this->assertSame(0, $second['imported']);
        $this->assertSame(2, $second['duplicates']);

        $this->assertDatabaseHas('user_status_histories', [
            'user_id' => 12,
            'to_status_id' => 3,
            'source' => 'historical_import',
        ]);
        $this->assertDatabaseHas('user_status_histories', [
            'user_id' => 12,
            'to_status_id' => 2,
            'retutored_by' => 'Cantero',
        ]);
    }

    private function insertUser(int $id, string $nick, int $statusId, ?string $memberAt): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'nick' => $nick,
            'email' => strtolower($nick) . '@example.test',
            'password' => 'not-used',
            'status_id' => $statusId,
            'member_at' => $memberAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('status_id');
            $table->date('member_at')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('from_status_id')->nullable();
            $table->unsignedBigInteger('to_status_id');
            $table->dateTime('changed_at');
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('source', 32)->default('automatic');
            $table->char('source_hash', 64)->nullable()->unique();
            $table->string('retutored_by', 120)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
}
