<?php

namespace Tests\Unit;

use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\CommunicationPreviewService;
use Tests\TestCase;

class CommunicationPreviewServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.url', 'https://www.squadalpha.es');
    }

    public function test_email_previews_use_the_real_branded_mail_pages_and_current_form_values(): void
    {
        $setting = new MemberProcedureSetting([
            'telegram_cantina_invite_url' => 'https://t.me/+cantina',
            'telegram_official_invite_url' => 'https://t.me/+oficial',
            'telegram_network_invite_url' => 'https://t.me/+network',
            'member_welcome_email_subject' => 'Alta de {{nick}}',
            'member_welcome_email_body' => 'Hola {{nick}}, ya eres miembro.',
            'reactivation_email_subject' => 'Vuelve {{nick}}',
            'reactivation_email_body' => 'Hola {{nick}}, vuelves a ACTIVO.',
        ]);

        $previews = app(CommunicationPreviewService::class)->emailPreviews($setting);

        $this->assertSame('Alta de Rylod', $previews['welcome']['subject']);
        $this->assertStringContainsString('Bienvenido a Squad ALPHA', $previews['welcome']['html']);
        $this->assertStringContainsString('REALISMO · DISCIPLINA · EQUIPO', $previews['welcome']['html']);
        $this->assertStringContainsString('https://t.me/+cantina', $previews['welcome']['html']);
        $this->assertSame('Vuelve Rylod', $previews['reactivation']['subject']);
        $this->assertStringContainsString('Bienvenido de vuelta', $previews['reactivation']['html']);
    }

    public function test_telegram_preview_uses_example_data_without_sending_anything(): void
    {
        $setting = new MemberProcedureSetting([
            'telegram_recruit_update_template' => "Actualización de reclutas:\n\n{{cambio}}\n\n{{cierre}}",
            'telegram_recruit_entry_endings' => [
                ['text' => 'Pronto veréis a {{nick}} en los operativos\\!'],
            ],
        ]);

        $preview = app(CommunicationPreviewService::class)->telegramPreview($setting, 'recruit_entry');

        $this->assertSame('Entrada de recluta', $preview['title']);
        $this->assertStringContainsString('Moon', $preview['message']);
        $this->assertStringContainsString('Nuevo recluta', $preview['message']);
        $this->assertStringContainsString('Pronto veréis a Moon', $preview['message']);
        $this->assertStringContainsString('Moon', $preview['html']);
    }
}
