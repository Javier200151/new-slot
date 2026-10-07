<?php

namespace Tests\Unit;

use App\Mail\MemberTelegramLinksMail;
use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Services\MemberProcedures\MemberProcedureEmailService;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

class MemberProcedureEmailServiceTest extends TestCase
{
    private function setting(): MemberProcedureSetting
    {
        return new MemberProcedureSetting([
            'telegram_cantina_invite_url' => 'https://t.me/+cantina',
            'telegram_official_invite_url' => 'https://t.me/+oficial',
            'telegram_network_invite_url' => 'https://t.me/+network',
            'member_welcome_email_subject' => 'Bienvenido, {{nick}}',
            'member_welcome_email_body' => 'Hola {{nick}}, ya eres miembro.',
            'reactivation_email_subject' => 'Bienvenido de vuelta, {{nick}}',
            'reactivation_email_body' => 'Hola {{nick}}, vuelves a estar ACTIVO.',
        ]);
    }

    public function test_new_member_receives_three_configured_telegram_links(): void
    {
        Mail::fake();

        $user = new User([
            'nick' => 'Rylod',
            'email' => 'rylod@example.com',
        ]);

        $result = app(MemberProcedureEmailService::class)->sendTelegramLinks($user, $this->setting(), false);

        $this->assertSame('rylod@example.com', $result['to']);
        $this->assertSame('Bienvenido, Rylod', $result['subject']);
        $this->assertFalse($result['reactivation']);

        Mail::assertSent(MemberTelegramLinksMail::class, function (MemberTelegramLinksMail $mail): bool {
            return $mail->hasTo('rylod@example.com')
                && $mail->mailSubject === 'Bienvenido, Rylod'
                && $mail->mailBody === 'Hola Rylod, ya eres miembro.'
                && $mail->links === [
                    'ALPHA Cantina' => 'https://t.me/+cantina',
                    'ALPHA Oficial' => 'https://t.me/+oficial',
                    '= ALPHA FORCE NETWORK =' => 'https://t.me/+network',
                ]
                && $mail->reactivation === false;
        });
    }

    public function test_reactivation_uses_welcome_back_template(): void
    {
        Mail::fake();

        $user = new User([
            'nick' => 'Rylod',
            'email' => 'rylod@example.com',
        ]);

        app(MemberProcedureEmailService::class)->sendTelegramLinks($user, $this->setting(), true);

        Mail::assertSent(MemberTelegramLinksMail::class, fn (MemberTelegramLinksMail $mail): bool =>
            $mail->mailSubject === 'Bienvenido de vuelta, Rylod'
            && $mail->mailBody === 'Hola Rylod, vuelves a estar ACTIVO.'
            && $mail->reactivation === true
        );
    }

    public function test_missing_invite_link_stops_the_automatic_email_with_actionable_error(): void
    {
        Mail::fake();

        $setting = $this->setting();
        $setting->telegram_official_invite_url = null;

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Falta configurar el enlace de invitación de Telegram para ALPHA Oficial.');

        app(MemberProcedureEmailService::class)->sendTelegramLinks(new User([
            'nick' => 'Rylod',
            'email' => 'rylod@example.com',
        ]), $setting, false);
    }
    public function test_member_mailable_uses_the_branded_welcome_and_reactivation_pages(): void
    {
        $user = new User(['nick' => 'Rylod', 'email' => 'rylod@example.com']);
        $links = [
            'ALPHA Cantina' => 'https://t.me/+cantina',
            'ALPHA Oficial' => 'https://t.me/+oficial',
            '= ALPHA FORCE NETWORK =' => 'https://t.me/+network',
        ];

        $welcome = new MemberTelegramLinksMail($user, 'Bienvenido', 'Hola Rylod', $links, false);
        $reactivation = new MemberTelegramLinksMail($user, 'Bienvenido de vuelta', 'Hola Rylod', $links, true);

        $this->assertSame('emails.member-welcome', $welcome->content()->view);
        $this->assertSame('emails.member-reactivation', $reactivation->content()->view);
    }

}
