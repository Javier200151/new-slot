<?php

namespace App\Services;

use App\Models\Activity;
class ActivityBriefingSqfExporter
{
    private const BREAK_TOKEN = '___NEWSLOT_SQF_BR___';

    public function export(
        Activity $activity,
        string $side,
        string $bannerPath,
    ): string {
        $side = $this->normalizeSide($side);
        $bannerPath = $this->normalizeBannerPath($bannerPath);
        $sections = $this->descriptionSections($activity);

        $body = '<br/><br/>' . "\r\n\r\n";

        if ($bannerPath !== '') {
            $body .= "<center><img image='"
                . $this->escapeStructuredTextAttribute($bannerPath)
                . "' width='350'/></center><br/>\r\n\r\n";
        }

        foreach ($sections as $section) {
            $title = trim((string) ($section['title'] ?? ''));
            $content = $this->htmlToStructuredText(
                $section['content'] ?? ''
            );

            if ($title === '' && $content === '') {
                continue;
            }

            if ($title !== '') {
                $body .= $this->sectionHeading($title);
            }

            if ($content !== '') {
                $body .= $content . "\r\n";
            }

            $body .= "<br/><br/>\r\n\r\n";
        }

        $body = trim($body);
        $body = $this->escapeSqfString($body);

        return "[\r\n"
            . "\t{$side},\r\n"
            . "\t\t[\"SITREP\",\r\n"
            . "\t\t\t\"{$body}\"]\r\n"
            . "] call FHQ_TT_addBriefing;\r\n";
    }

    private function descriptionSections(Activity $activity): array
    {
        $description = $activity->description ?? [];
        $sections = $description['sections'] ?? [];

        if (
            blank($sections)
            && filled($description['content'] ?? null)
        ) {
            $sections = [
                [
                    'title' => 'Descripción',
                    'content' => $description['content'],
                ],
            ];
        }

        return collect($sections)
            ->filter(fn ($section): bool => is_array($section))
            ->values()
            ->all();
    }

    private function sectionHeading(string $title): string
    {
        $title = html_entity_decode(
            strip_tags($title),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        $title = $this->escapeStructuredText($title);

        return "<font face='PuristaMedium' size=20 color='#ffcc33'>"
            . $title
            . "</font><br/>\r\n"
            . "<font face='PuristaMedium' size=12 color='#ffcc33'>"
            . "__________________________________"
            . "</font><br/>\r\n\r\n";
    }

    private function htmlToStructuredText(mixed $content): string
    {
        if (is_array($content)) {
            $content = $this->flattenRichContent($content);
        }

        $html = trim((string) $content);

        if ($html === '') {
            return '';
        }

        $html = preg_replace_callback(
            '/<ol\b[^>]*>(.*?)<\/ol>/is',
            fn (array $match): string => $this->convertList(
                $match[1],
                true,
            ),
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/<ul\b[^>]*>(.*?)<\/ul>/is',
            fn (array $match): string => $this->convertList(
                $match[1],
                false,
            ),
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is',
            function (array $match): string {
                $label = trim(strip_tags($match[3]));
                $url = trim(html_entity_decode(
                    $match[2],
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                ));

                if ($label === '') {
                    return $url;
                }

                return $label . ' (' . $url . ')';
            },
            $html,
        ) ?? $html;

        $html = preg_replace(
            '/<img\b[^>]*alt\s*=\s*(["\'])(.*?)\1[^>]*>/is',
            '$2',
            $html,
        ) ?? $html;

        $html = preg_replace(
            '/<br\s*\/?\s*>/i',
            self::BREAK_TOKEN,
            $html,
        ) ?? $html;

        $html = preg_replace(
            '/<\/(p|div|h[1-6]|blockquote)>/i',
            self::BREAK_TOKEN . self::BREAK_TOKEN,
            $html,
        ) ?? $html;

        $html = preg_replace(
            '/<(p|div|h[1-6]|blockquote)\b[^>]*>/i',
            '',
            $html,
        ) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        $text = str_replace(
            ["\r\n", "\r", "\n"],
            ' ',
            $text,
        );

        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = $this->escapeStructuredText($text);
        $text = str_replace(self::BREAK_TOKEN, '<br/>', $text);
        $text = preg_replace('/\s*<br\/>\s*/u', '<br/>', $text) ?? $text;
        $text = preg_replace('/(?:<br\/>){3,}/u', '<br/><br/>', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B");
        $text = preg_replace('/^(?:<br\/>)+|(?:<br\/>)+$/u', '', $text) ?? $text;

        return trim($text);
    }

    private function convertList(string $innerHtml, bool $ordered): string
    {
        if (! preg_match_all(
            '/<li\b[^>]*>(.*?)<\/li>/is',
            $innerHtml,
            $matches,
        )) {
            return $innerHtml;
        }

        $lines = [];

        foreach ($matches[1] as $index => $itemHtml) {
            $item = html_entity_decode(
                strip_tags(
                    preg_replace(
                        '/<br\s*\/?\s*>/i',
                        ' ',
                        $itemHtml,
                    ) ?? $itemHtml
                ),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );

            $item = trim(preg_replace('/\s+/u', ' ', $item) ?? $item);

            if ($item === '') {
                continue;
            }

            $prefix = $ordered
                ? ((int) $index + 1) . '. '
                : '- ';

            $lines[] = $prefix . $item;
        }

        return implode(self::BREAK_TOKEN, $lines)
            . self::BREAK_TOKEN;
    }

    private function flattenRichContent(array $value): string
    {
        $parts = [];

        $walk = function (mixed $node) use (&$walk, &$parts): void {
            if (is_string($node)) {
                $parts[] = $node;

                return;
            }

            if (! is_array($node)) {
                return;
            }

            if (isset($node['text']) && is_string($node['text'])) {
                $parts[] = $node['text'];
            }

            foreach ($node as $key => $child) {
                if ($key === 'text') {
                    continue;
                }

                $walk($child);
            }
        };

        $walk($value);

        return implode(' ', $parts);
    }

    private function normalizeSide(string $side): string
    {
        $side = strtoupper(trim($side));

        if (! preg_match('/^[A-Z_][A-Z0-9_]*$/', $side)) {
            return 'WEST';
        }

        return $side;
    }

    private function normalizeBannerPath(string $bannerPath): string
    {
        $bannerPath = trim($bannerPath);
        $bannerPath = str_replace('/', '\\', $bannerPath);
        $bannerPath = str_replace(["\r", "\n"], '', $bannerPath);

        return $bannerPath;
    }

    private function escapeStructuredText(string $value): string
    {
        return str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $value,
        );
    }

    private function escapeStructuredTextAttribute(string $value): string
    {
        return str_replace(
            ['&', "'", '<', '>'],
            ['&amp;', '&apos;', '&lt;', '&gt;'],
            $value,
        );
    }

    private function escapeSqfString(string $value): string
    {
        return str_replace('"', '""', $value);
    }
}
