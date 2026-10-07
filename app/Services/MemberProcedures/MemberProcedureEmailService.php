<?php

namespace App\Services\MemberProcedures;

use App\Mail\MemberTelegramLinksMail;
use App\Models\MemberProcedureSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use LogicException;

class MemberProcedureEmailService
{
    /** @return array<string, mixed> */
    public function sendTelegramLinks(User $user, MemberProcedureSetting $setting, bool $reactivation): array
    {
        $email = trim((string) $user->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new LogicException('El usuario no tiene un correo electrónico válido para enviarle los accesos de Telegram.');
        }

        $links = [
            'ALPHA Cantina' => $this->validatedTelegramInvite((string) $setting->telegram_cantina_invite_url, 'ALPHA Cantina'),
            'ALPHA Oficial' => $this->validatedTelegramInvite((string) $setting->telegram_official_invite_url, 'ALPHA Oficial'),
            '= ALPHA FORCE NETWORK =' => $this->validatedTelegramInvite((string) $setting->telegram_network_invite_url, '= ALPHA FORCE NETWORK ='),
        ];

        $subjectTemplate = trim((string) ($reactivation
            ? $setting->reactivation_email_subject
            : $setting->member_welcome_email_subject));
        $bodyTemplate = trim((string) ($reactivation
            ? $setting->reactivation_email_body
            : $setting->member_welcome_email_body));

        if ($subjectTemplate === '') {
            $subjectTemplate = $reactivation
                ? 'Bienvenido de vuelta a Squad ALPHA, {{nick}}'
                : 'Bienvenido a Squad ALPHA, {{nick}}';
        }

        if ($bodyTemplate === '') {
            $bodyTemplate = $reactivation
                ? "Hola {{nick}},\n\nTu reactivación ya está completada y vuelves a estar ACTIVO en Squad ALPHA. Aquí tienes los enlaces vigentes para reincorporarte a los grupos oficiales de Telegram."
                : "Hola {{nick}},\n\nEnhorabuena por completar tu reclutamiento. Ya formas parte de Squad ALPHA. Aquí tienes los enlaces vigentes para incorporarte a los grupos oficiales de Telegram.";
        }

        $subject = $this->renderTemplate($subjectTemplate, $user);
        $body = $this->renderTemplate($bodyTemplate, $user);

        Mail::to($email)->send(new MemberTelegramLinksMail(
            user: $user,
            mailSubject: $subject,
            mailBody: $body,
            links: $links,
            reactivation: $reactivation,
        ));

        return [
            'to' => $email,
            'subject' => $subject,
            'links' => array_keys($links),
            'reactivation' => $reactivation,
        ];
    }

    private function renderTemplate(string $template, User $user): string
    {
        return strtr($template, [
            '{{nick}}' => (string) $user->nick,
        ]);
    }

    private function validatedTelegramInvite(string $url, string $label): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new LogicException('Falta configurar el enlace de invitación de Telegram para ' . $label . '.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if ($scheme !== 'https'
            || ! in_array($host, ['t.me', 'www.t.me', 'telegram.me', 'www.telegram.me'], true)
            || $path === '') {
            throw new LogicException('El enlace de ' . $label . ' no parece un enlace válido de Telegram (https://t.me/…).');
        }

        return $url;
    }
}
