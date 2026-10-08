<?php

namespace App\Services\MemberProcedures;

use App\Models\MemberProcedureSetting;
use App\Models\User;

class MemberProcedureEmailTemplateService
{
    /** @return array{subject:string,body:string} */
    public function templates(MemberProcedureSetting $setting, bool $reactivation): array
    {
        $subject = trim((string) ($reactivation
            ? $setting->reactivation_email_subject
            : $setting->member_welcome_email_subject));
        $body = trim((string) ($reactivation
            ? $setting->reactivation_email_body
            : $setting->member_welcome_email_body));

        if ($subject === '') {
            $subject = $reactivation
                ? 'Bienvenido de vuelta a Squad ALPHA, {{nick}}'
                : 'Bienvenido a Squad ALPHA, {{nick}}';
        }

        if ($body === '') {
            $body = $reactivation
                ? "Hola {{nick}},\n\nTu reactivación ha sido completada y vuelves a formar parte de Squad ALPHA como miembro ACTIVO. Utiliza los accesos de este correo para reincorporarte a nuestros grupos oficiales de Telegram y ponte al día con la actividad de la comunidad."
                : "Hola {{nick}},\n\n¡Enhorabuena por completar tu reclutamiento! Desde este momento ya formas parte de Squad ALPHA como miembro ACTIVO. A continuación encontrarás los pasos principales y los accesos a nuestros grupos oficiales de Telegram.";
        }

        return compact('subject', 'body');
    }

    /** @return array{subject:string,body:string} */
    public function render(User $user, MemberProcedureSetting $setting, bool $reactivation): array
    {
        $templates = $this->templates($setting, $reactivation);

        return [
            'subject' => $this->renderTemplate($templates['subject'], $user),
            'body' => $this->renderTemplate($templates['body'], $user),
        ];
    }

    private function renderTemplate(string $template, User $user): string
    {
        return strtr($template, [
            '{{nick}}' => (string) $user->nick,
        ]);
    }
}
