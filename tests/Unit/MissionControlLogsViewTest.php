<?php

namespace Tests\Unit;

use Tests\TestCase;

class MissionControlLogsViewTest extends TestCase
{
    public function test_logs_view_is_available_for_every_server_and_keeps_text_untrusted(): void
    {
        $view = file_get_contents(resource_path('views/filament/pages/servidores-juego.blade.php'));
        $js = file_get_contents(public_path('js/filament-game-servers-control.js'));
        $css = file_get_contents(public_path('css/filament-game-servers.css'));

        $this->assertIsString($view);
        $this->assertIsString($js);
        $this->assertIsString($css);

        $this->assertStringContainsString('openLogs(academy.id)', $view);
        $this->assertStringContainsString("openLogs('operativos')", $view);
        $this->assertStringContainsString('x-show="canViewLogs"', $view);
        $this->assertStringContainsString('wire:ignore class="sqa-server-panel__logs-dialog"', $view);
        $this->assertStringContainsString('x-ref="logOutput"', $view);
        $this->assertStringNotContainsString('x-html="logText"', $view);
        $this->assertStringContainsString('pre.textContent = text;', $js);
        $this->assertStringContainsString('this.logOffset = end;', $js);
        $this->assertStringContainsString('this.logText.split', $js);
        $this->assertStringContainsString('lines.slice(-20000)', $js);
        $this->assertStringContainsString('},3000);', $js);
        $this->assertStringContainsString('.sqa-server-panel__logs-dialog', $css);
    }

    public function test_logs_methods_are_renderless_and_do_not_store_rpt_text_in_component_properties(): void
    {
        $page = file_get_contents(app_path('Filament/Pages/ServidoresJuego.php'));
        $this->assertIsString($page);
        $this->assertStringContainsString('public function loadLogs(string $server): array', $page);
        $this->assertStringContainsString('public function readLog(string $server, string $source, string $file, ?int $from = null): array', $page);
        $this->assertSame(2, substr_count($page, '#[\\Livewire\\Attributes\\Renderless]'));
        $this->assertStringNotContainsString('public string $logText', $page);
    }
}
