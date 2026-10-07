<?php

namespace Tests\Unit;

use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\WeeklyActivityTelegramService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WeeklyActivityTelegramServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://www.squadalpha.es');

        Schema::create('activity_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('activity_type_id');
            $table->string('name');
            $table->softDeletes();
        });

        Schema::create('event_status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('activity_id');
            $table->string('name');
            $table->dateTime('date');
            $table->unsignedBigInteger('event_status_id');
            $table->softDeletes();
        });

        DB::table('activity_types')->insert([
            ['id' => 1, 'name' => 'OPERATIVO'],
            ['id' => 2, 'name' => 'INSTRUCCIÓN'],
        ]);
        DB::table('activities')->insert([
            ['id' => 1, 'activity_type_id' => 1, 'name' => 'Operativo'],
            ['id' => 2, 'activity_type_id' => 2, 'name' => 'Prácticas'],
        ]);
        DB::table('event_status')->insert([
            ['id' => 1, 'name' => 'ACTIVO'],
            ['id' => 2, 'name' => 'BORRADOR'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_status');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('activity_types');

        parent::tearDown();
    }

    public function test_sunday_preview_requires_tuesday_and_friday_operatives_and_warns_about_drafts(): void
    {
        DB::table('events')->insert([
            ['id' => 10, 'activity_id' => 2, 'name' => 'Lunes de prácticas', 'date' => '2026-10-12 20:00:00', 'event_status_id' => 1],
            ['id' => 11, 'activity_id' => 1, 'name' => 'OPERATION ICE FREYA', 'date' => '2026-10-13 20:00:00', 'event_status_id' => 1],
            ['id' => 12, 'activity_id' => 1, 'name' => 'Borrador miércoles', 'date' => '2026-10-14 20:00:00', 'event_status_id' => 2],
            ['id' => 13, 'activity_id' => 1, 'name' => 'OPERATION WATCHTOWER', 'date' => '2026-10-16 22:30:00', 'event_status_id' => 1],
        ]);

        $setting = $this->setting();
        $preview = app(WeeklyActivityTelegramService::class)->preview(
            $setting,
            CarbonImmutable::parse('2026-10-11 10:00:00', 'Europe/Madrid'),
        );

        $this->assertTrue($preview['window_open']);
        $this->assertTrue($preview['can_send']);
        $this->assertSame([], $preview['blocking_errors']);
        $this->assertStringContainsString('Miércoles', implode(' ', $preview['warnings']));
        $this->assertStringContainsString('Lunes de prácticas', $preview['message']);
        $this->assertStringContainsString('OPERATION ICE FREYA', $preview['message']);
        $this->assertStringContainsString('OPERATION WATCHTOWER', $preview['message']);
        $this->assertStringNotContainsString('Borrador miércoles', $preview['message']);
    }

    public function test_missing_required_friday_operational_event_blocks_sending(): void
    {
        DB::table('events')->insert([
            ['id' => 20, 'activity_id' => 1, 'name' => 'Operativo martes', 'date' => '2026-10-13 20:00:00', 'event_status_id' => 1],
        ]);

        $preview = app(WeeklyActivityTelegramService::class)->preview(
            $this->setting(),
            CarbonImmutable::parse('2026-10-11 10:00:00', 'Europe/Madrid'),
        );

        $this->assertFalse($preview['can_send']);
        $this->assertStringContainsString('Viernes', implode(' ', $preview['blocking_errors']));
    }

    public function test_weekly_button_window_closes_on_monday_at_1800(): void
    {
        $service = app(WeeklyActivityTelegramService::class);

        $this->assertTrue($service->window(CarbonImmutable::parse('2026-10-12 17:59:59', 'Europe/Madrid'))['open']);
        $this->assertFalse($service->window(CarbonImmutable::parse('2026-10-12 18:00:00', 'Europe/Madrid'))['open']);
    }

    private function setting(): MemberProcedureSetting
    {
        return new MemberProcedureSetting([
            'telegram_network_chat_id' => '-1001234567890',
            'telegram_weekly_active_event_status_id' => 1,
            'telegram_weekly_required_weekdays' => [2, 5],
            'telegram_weekly_required_activity_type_ids' => [1],
            'telegram_weekly_template' => "ACTUALIZADA ACTIVIDAD SEMANAL\n\n☠️☠️ ACTIVIDAD SEMANAL ☠️☠️\n\n{{eventos}}",
        ]);
    }
}
