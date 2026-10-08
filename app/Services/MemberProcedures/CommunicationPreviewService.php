<?php

namespace App\Services\MemberProcedures;

use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Services\VeterancyService;

class CommunicationPreviewService
{
    public function __construct(
        private readonly TelegramNotificationService $telegramMessages,
        private readonly WeeklyActivityTelegramService $weeklyActivity,
        private readonly MemberProcedureEmailTemplateService $emailTemplates,
    ) {
    }

    /**
     * @return array{welcome:array{subject:string,html:string},reactivation:array{subject:string,html:string}}
     */
    public function emailPreviews(MemberProcedureSetting $setting): array
    {
        $user = new User([
            'nick' => 'Rylod',
            'email' => 'rylod@example.com',
        ]);

        return [
            'welcome' => $this->emailPreview($user, $setting, false),
            'reactivation' => $this->emailPreview($user, $setting, true),
        ];
    }

    /**
     * @return array{subject:string,html:string}
     */
    public function emailPreview(User $user, MemberProcedureSetting $setting, bool $reactivation): array
    {
        $rendered = $this->emailTemplates->render($user, $setting, $reactivation);
        $subject = $rendered['subject'];
        $body = $rendered['body'];

        $links = [
            'ALPHA Cantina' => $this->previewInvite((string) $setting->telegram_cantina_invite_url, 'cantina'),
            'ALPHA Oficial' => $this->previewInvite((string) $setting->telegram_official_invite_url, 'oficial'),
            '= ALPHA FORCE NETWORK =' => $this->previewInvite((string) $setting->telegram_network_invite_url, 'network'),
        ];

        $view = $reactivation ? 'emails.member-reactivation' : 'emails.member-welcome';

        return [
            'subject' => $subject,
            'html' => view($view, [
                'user' => $user,
                'mailSubject' => $subject,
                'mailBody' => $body,
                'links' => $links,
                'reactivation' => $reactivation,
            ])->render(),
        ];
    }

    /**
     * @return array{title:string,message:string,html:string,note:string}
     */
    public function telegramPreview(MemberProcedureSetting $setting, string $kind): array
    {
        $kind = in_array($kind, ['recruit_entry', 'recruit_exit', 'veterancy', 'weekly'], true)
            ? $kind
            : 'recruit_entry';

        if ($kind === 'recruit_entry') {
            $ending = $this->firstEnding($setting->telegram_recruit_entry_endings ?? []);
            $preview = $this->telegramMessages->previewRecruitUpdate('Moon', $setting, 'entry', $ending);

            return $this->telegramResult('Entrada de recluta', (string) $preview['message']);
        }

        if ($kind === 'recruit_exit') {
            $ending = $this->firstEnding($setting->telegram_recruit_exit_endings ?? []);
            $preview = $this->telegramMessages->previewRecruitUpdate('Yerman', $setting, 'not_promoted', $ending);

            return $this->telegramResult('Recluta no promocionado', (string) $preview['message']);
        }

        if ($kind === 'veterancy') {
            $ending = $this->firstEnding($setting->telegram_veterancy_endings ?? []);
            $preview = $this->telegramMessages->previewVeterancyUpdate(
                'https://www.squadalpha.es/area/foro/personal/5',
                [
                    ['nick' => 'Dragut', 'level' => VeterancyService::GOLD],
                    ['nick' => '7orres', 'level' => VeterancyService::SILVER],
                    ['nick' => 'Hausser', 'level' => VeterancyService::SILVER],
                    ['nick' => 'Cetme', 'level' => VeterancyService::BRONZE],
                    ['nick' => 'Miskito', 'level' => VeterancyService::BRONZE],
                    ['nick' => 'Storm', 'level' => VeterancyService::BRONZE],
                ],
                $setting,
                $ending,
            );

            return $this->telegramResult('Veteranías', (string) $preview['message']);
        }

        $ending = $this->firstEnding($setting->telegram_weekly_endings ?? []);
        $preview = $this->weeklyActivity->previewTemplateSample($setting, $ending);

        return $this->telegramResult('Actividad semanal', (string) $preview['message']);
    }

    /** @return array{title:string,message:string,html:string,note:string} */
    private function telegramResult(string $title, string $message): array
    {
        return [
            'title' => $title,
            'message' => $message,
            'html' => $this->telegramMarkdownToHtml($message),
            'note' => 'La previsualización utiliza datos de ejemplo. En los envíos reales el sistema sustituye las variables y escoge un cierre aleatorio.',
        ];
    }

    private function previewInvite(string $value, string $slug): string
    {
        $value = trim($value);

        return $value !== '' ? $value : 'https://t.me/+PREVIEW-' . strtoupper($slug);
    }

    /** @param array<int, mixed> $endings */
    private function firstEnding(array $endings): string
    {
        foreach ($endings as $ending) {
            $value = is_array($ending) ? (string) ($ending['text'] ?? '') : (string) $ending;
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function telegramMarkdownToHtml(string $markdown): string
    {
        $protected = [];
        $markdown = preg_replace_callback('/\\\\([_*\[\]()~`>#+\-=|{}.!\\\\])/', function (array $match) use (&$protected): string {
            $token = '@@TGESC' . count($protected) . '@@';
            $protected[$token] = $match[1];

            return $token;
        }, $markdown) ?? $markdown;

        $html = e($markdown);

        $html = preg_replace_callback('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/', function (array $match): string {
            $url = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                return $match[0];
            }

            return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . $match[1] . '</a>';
        }, $html) ?? $html;

        $html = preg_replace('/\*([^*\n]+)\*/', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/_([^_\n]+)_/', '<em>$1</em>', $html) ?? $html;

        foreach ($protected as $token => $character) {
            $html = str_replace($token, e($character), $html);
        }

        return nl2br($html);
    }
}
