<?php

namespace App\Services\MemberProcedures;

use App\Models\CommunityPost;
use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Services\VeterancyService;
use RuntimeException;

class TelegramNotificationService
{
    public function __construct(
        private readonly TelegramService $telegram,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->telegram->isConfigured();
    }

    /** @return array<string, mixed> */
    public function sendRecruitUpdate(User $user, MemberProcedureSetting $setting, string $change): array
    {
        $this->assertDestination($setting);

        $preview = $this->previewRecruitUpdate((string) $user->nick, $setting, $change);

        $result = $this->telegram->sendMessage(
            (string) $setting->telegram_network_chat_id,
            (string) $preview['message'],
            'MarkdownV2',
        );

        $result['message_kind'] = 'recruit_' . $preview['change'];
        $result['ending'] = $preview['ending'];

        return $result;
    }

    /** @return array{message:string,ending:string,change:string} */
    public function previewRecruitUpdate(
        string $nick,
        MemberProcedureSetting $setting,
        string $change,
        ?string $preferredEnding = null,
    ): array {
        $change = strtolower(trim($change));
        if (! in_array($change, ['entry', 'not_promoted'], true)) {
            throw new RuntimeException('Tipo de actualización de reclutamiento no reconocido.');
        }

        $nick = $this->escapeMarkdownV2($nick);
        $changeText = $change === 'entry'
            ? "Nuevo recluta:\n\\- {$nick}"
            : "Recluta no promocionado:\n\\- {$nick}";

        $endings = $change === 'entry'
            ? ($setting->telegram_recruit_entry_endings ?? [])
            : ($setting->telegram_recruit_exit_endings ?? []);

        $endingTemplate = $preferredEnding ?? $this->chooseEnding($endings);
        $ending = $this->renderEnding($endingTemplate, [
            '{{nick}}' => $nick,
            '{{tipo_cambio}}' => $change === 'entry' ? 'Nuevo recluta' : 'Recluta no promocionado',
        ]);

        $template = trim((string) $setting->telegram_recruit_update_template);
        if ($template === '') {
            $template = "Actualización de reclutas:\n\n{{cambio}}\n\n{{cierre}}";
        }

        return [
            'message' => $this->renderTemplate($template, [
                '{{nick}}' => $nick,
                '{{tipo_cambio}}' => $change === 'entry' ? 'Nuevo recluta' : 'Recluta no promocionado',
                '{{cambio}}' => $changeText,
                '{{cierre}}' => $ending,
            ], $ending),
            'ending' => $ending,
            'change' => $change,
        ];
    }

    /**
     * @param array<int, array{nick:string,level:string}> $awards
     * @return array<string, mixed>
     */
    public function sendVeterancyUpdate(CommunityPost $post, array $awards, MemberProcedureSetting $setting): array
    {
        $this->assertDestination($setting);

        if ($awards === []) {
            throw new RuntimeException('No hay veteranías que anunciar en Telegram.');
        }

        $forumUrl = route('community.forum.show', [
            $post->forumCategory?->slug ?: $post->channel,
            $post,
        ]);

        $preview = $this->previewVeterancyUpdate($forumUrl, $awards, $setting);

        $result = $this->telegram->sendMessage(
            (string) $setting->telegram_network_chat_id,
            (string) $preview['message'],
            'MarkdownV2',
        );

        $result['message_kind'] = 'veterancy';
        $result['ending'] = $preview['ending'];

        return $result;
    }

    /**
     * @param array<int, array{nick:string,level:string}> $awards
     * @return array{message:string,ending:string}
     */
    public function previewVeterancyUpdate(
        string $forumUrl,
        array $awards,
        MemberProcedureSetting $setting,
        ?string $preferredEnding = null,
    ): array {
        if ($awards === []) {
            throw new RuntimeException('No hay veteranías que previsualizar.');
        }

        $forumLink = $this->markdownLink($forumUrl, $forumUrl);
        $veterancies = $this->buildVeterancyLines($awards);
        $endingTemplate = $preferredEnding ?? $this->chooseEnding($setting->telegram_veterancy_endings ?? []);
        $ending = $this->renderEnding($endingTemplate, []);

        $template = trim((string) $setting->telegram_veterancy_template);
        if ($template === '') {
            $template = "{{foro_url}}\n\nLa administración tiene el orgullo de galardonar:\n\n{{veteranias}}\n\n{{cierre}}";
        }

        return [
            'message' => $this->renderTemplate($template, [
                '{{foro_url}}' => $forumLink,
                '{{veteranias}}' => $veterancies,
                '{{cierre}}' => $ending,
            ], $ending),
            'ending' => $ending,
        ];
    }

    public function escapeMarkdownV2(string $value): string
    {
        return preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $value) ?? $value;
    }

    public function markdownLink(string $label, string $url): string
    {
        $label = $this->escapeMarkdownV2($label);
        $url = str_replace(['\\', ')'], ['\\\\', '\\)'], $url);

        return '[' . $label . '](' . $url . ')';
    }

    /**
     * @param array<int, array{nick:string,level:string}> $awards
     */
    private function buildVeterancyLines(array $awards): string
    {
        $groups = collect($awards)->groupBy('level');
        $blocks = [];

        foreach ([VeterancyService::GOLD, VeterancyService::SILVER, VeterancyService::BRONZE] as $level) {
            $items = $groups->get($level, collect());
            if ($items->isEmpty()) {
                continue;
            }

            $definition = VeterancyService::LEVELS[$level];
            $medal = match ($level) {
                VeterancyService::GOLD => '🥇',
                VeterancyService::SILVER => '🥈',
                default => '🥉',
            };
            $metal = match ($level) {
                VeterancyService::GOLD => 'oro',
                VeterancyService::SILVER => 'plata',
                default => 'bronce',
            };
            $years = (int) $definition['years'];

            $lines = $items
                ->sortBy(fn (array $award): string => mb_strtolower((string) $award['nick']))
                ->map(function (array $award) use ($medal, $metal, $years): string {
                    $nick = $this->escapeMarkdownV2((string) $award['nick']);

                    return $medal . ' a SQA ' . $nick
                        . ' con la medalla de veterano de ' . $metal
                        . ' \\(' . $years . ' ' . ($years === 1 ? 'año' : 'años') . '\\)';
                })
                ->values()
                ->all();

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /** @param array<int, mixed> $endings */
    private function chooseEnding(array $endings): string
    {
        $values = collect($endings)
            ->map(function ($item): string {
                if (is_array($item)) {
                    return trim((string) ($item['text'] ?? ''));
                }

                return trim((string) $item);
            })
            ->filter()
            ->values();

        if ($values->isEmpty()) {
            return '';
        }

        return (string) $values->get(random_int(0, $values->count() - 1));
    }

    /** @param array<string, string> $variables */
    private function renderEnding(string $ending, array $variables): string
    {
        if ($ending === '') {
            return '';
        }

        return trim(strtr($ending, $variables));
    }

    /** @param array<string, string> $variables */
    private function renderTemplate(string $template, array $variables, string $ending): string
    {
        $hasClosingPlaceholder = str_contains($template, '{{cierre}}');
        $message = strtr($template, $variables);

        if (! $hasClosingPlaceholder && $ending !== '') {
            $message .= "\n\n" . $ending;
        }

        $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? $message;

        return trim($message);
    }

    private function assertDestination(MemberProcedureSetting $setting): void
    {
        if (! $this->telegram->isConfigured()) {
            throw new RuntimeException('Telegram no está configurado. Revisa TELEGRAM_ENABLED y TELEGRAM_BOT_TOKEN.');
        }

        if (blank($setting->telegram_network_chat_id)) {
            throw new RuntimeException('Configura el chat de = ALPHA FORCE NETWORK = en Config. procedimientos → Telegram.');
        }
    }
}
