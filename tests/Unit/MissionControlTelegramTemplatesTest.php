<?php

namespace Tests\Unit;

use App\Services\MissionControl\MissionControlTelegramTemplates as Templates;
use PHPUnit\Framework\TestCase;

class MissionControlTelegramTemplatesTest extends TestCase
{
    public function test_templates_are_plain_text_with_no_emojis(): void
    {
        foreach (Templates::labels() as $key => $label) {
            $preview = Templates::preview($key);
            $this->assertNotSame('', $preview, $label);
            $this->assertSame($preview, Templates::plain($preview), $key);
        }
    }

    public function test_user_customizations_and_variables_are_used(): void
    {
        $rendered = Templates::render('files_ready', ['evento' => 'Prueba', 'automatico' => 'desactivado'], [
            'files_ready' => 'Evento {evento} · Auto {automatico}',
        ]);
        $this->assertSame('Evento Prueba · Auto desactivado', $rendered);
    }

    public function test_preview_values_cannot_leak_into_live_messages(): void
    {
        $this->assertSame('Evento —', Templates::render('agent_notice', [], ['agent_notice' => 'Evento {evento}']));
    }

    public function test_plain_removes_emojis_but_preserves_meaning(): void
    {
        $this->assertSame('[OK] PBO correcto', Templates::plain('✅ PBO correcto'));
        $this->assertSame('[FALTA] HTML no disponible', Templates::plain('❌ HTML no disponible'));
    }
}
