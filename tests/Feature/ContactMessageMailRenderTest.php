<?php

namespace Tests\Feature;

use App\Mail\ContactMessageAdminMail;
use App\Mail\ContactMessageConfirmationMail;
use App\Models\ContactSubmission;
use Tests\TestCase;

class ContactMessageMailRenderTest extends TestCase
{
    public function test_simple_contact_mail_views_render_without_recruitment_data(): void
    {
        $submission = new ContactSubmission([
            'nickname' => 'Visitante',
            'email' => 'visitante@example.test',
            'message' => 'Consulta general de prueba.',
            'is_recruitment' => false,
            'accepted_privacy' => true,
            'accepted_contact' => true,
        ]);

        $adminHtml = (new ContactMessageAdminMail($submission))->render();
        $confirmationHtml = (new ContactMessageConfirmationMail($submission))->render();

        $this->assertStringContainsString('Nueva consulta recibida', $adminHtml);
        $this->assertStringContainsString('Consulta general de prueba.', $adminHtml);
        $this->assertStringContainsString('Hemos recibido tu consulta', $confirmationHtml);
        $this->assertStringContainsString('Consulta general de prueba.', $confirmationHtml);
    }
}
