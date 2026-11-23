<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Services\MemberProcedures\TelegramService;
use App\Services\MissionControl\MissionControlClient;
use App\Services\MissionControl\MissionControlTelegramMonitor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MissionControlManualLaunchTest extends TestCase
{
    public function test_manual_launch_success_even_when_automatic_is_off(): void
    {
        $this->assertSame('manual', $this->getLaunchStatus(false, 551));
    }

    public function test_no_false_manual_success_when_another_event_is_running(): void
    {
        $this->assertSame('pending', $this->getLaunchStatus(false, 552));
    }

    public function test_automatic_launch_classified_as_ready(): void
    {
        $this->assertSame('ready', $this->getLaunchStatus(true, 551));
    }

    private function getLaunchStatus(bool $automatic, int $runningId): string
    {
        $event = new Event();
        $event->id = 551;
        $auto = ['activo' => $automatic, 'eventos' => [[
            'evento_id' => 551, 'automatico' => true,
            'estado' => $automatic ? 'lanzado' : 'pendiente',
        ]]];
        $servers = [[
            'id' => 'operativos', 'estado' => 'en_marcha',
            'mision' => ['evento' => ['id' => $runningId]],
        ]];
        $monitor = new MissionControlTelegramMonitor(new MissionControlClient(), new TelegramService());
        return (new ReflectionMethod($monitor, 'launchResult'))->invoke($monitor, $event, $auto, $servers, null)[0];
    }
}
