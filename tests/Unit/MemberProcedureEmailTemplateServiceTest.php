<?php

namespace Tests\Unit;

use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Services\MemberProcedures\MemberProcedureEmailTemplateService;
use Tests\TestCase;

class MemberProcedureEmailTemplateServiceTest extends TestCase
{
    public function test_default_copy_is_used_when_custom_text_is_empty(): void
    {
        $setting = new MemberProcedureSetting();
        $service = app(MemberProcedureEmailTemplateService::class);

        $welcome = $service->render(new User(['nick' => 'Rylod']), $setting, false);
        $reactivation = $service->render(new User(['nick' => 'Rylod']), $setting, true);

        $this->assertStringContainsString('Rylod', $welcome['subject']);
        $this->assertStringContainsString('completar tu reclutamiento', $welcome['body']);
        $this->assertStringContainsString('Rylod', $reactivation['subject']);
        $this->assertStringContainsString('reactivación', $reactivation['body']);
    }

    public function test_only_text_templates_are_rendered_with_user_variables(): void
    {
        $setting = new MemberProcedureSetting([
            'member_welcome_email_subject' => 'Alta de {{nick}}',
            'member_welcome_email_body' => 'Hola {{nick}}, texto editable.',
        ]);

        $rendered = app(MemberProcedureEmailTemplateService::class)
            ->render(new User(['nick' => 'Moon']), $setting, false);

        $this->assertSame('Alta de Moon', $rendered['subject']);
        $this->assertSame('Hola Moon, texto editable.', $rendered['body']);
    }
}
