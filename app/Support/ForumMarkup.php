<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class ForumMarkup
{
    private const COLORS = [
        'white' => '#f8fafc',
        'gray' => '#94a3b8',
        'red' => '#f87171',
        'orange' => '#fb923c',
        'yellow' => '#facc15',
        'green' => '#4ade80',
        'cyan' => '#22d3ee',
        'blue' => '#60a5fa',
        'purple' => '#c084fc',
        'pink' => '#f472b6',
    ];

    public static function render(?string $value): HtmlString
    {
        $text = trim((string) $value);

        if ($text === '') {
            return new HtmlString('');
        }

        $html = e(str_replace(["\r\n", "\r"], "\n", $text));
        $placeholders = [];

        $stash = static function (string $fragment) use (&$placeholders): string {
            $key = '%%FORUM_FRAGMENT_' . count($placeholders) . '%%';
            $placeholders[$key] = $fragment;

            return $key;
        };

        // Code is protected before parsing anything else.
        $html = preg_replace_callback(
            '~\[code\](.*?)\[/code\]~is',
            static fn (array $match): string => $stash(
                '<pre class="forum-rich__code"><code>' . $match[1] . '</code></pre>'
            ),
            $html,
        );

        // Images are URL-only, as requested, and never allow javascript/data schemes.
        $html = preg_replace_callback(
            '~\[img\](.*?)\[/img\]~is',
            static function (array $match) use ($stash): string {
                $url = self::decoded($match[1]);

                if (! self::isSafeHttpUrl($url)) {
                    return $match[0];
                }

                return $stash(
                    '<a class="forum-rich__image-link" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
                    . '<img class="forum-rich__image" src="' . e($url) . '" alt="Imagen insertada en el foro" loading="lazy" referrerpolicy="no-referrer">'
                    . '</a>'
                );
            },
            $html,
        );

        $html = preg_replace_callback(
            '~\[url=(.*?)\](.*?)\[/url\]~is',
            static function (array $match) use ($stash): string {
                $url = self::decoded($match[1]);

                if (! self::isSafeHttpUrl($url)) {
                    return $match[2];
                }

                return $stash(
                    '<a class="forum-rich__link" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
                    . $match[2]
                    . '</a>'
                );
            },
            $html,
        );

        $html = preg_replace_callback(
            '~\[url\](.*?)\[/url\]~is',
            static function (array $match) use ($stash): string {
                $url = self::decoded($match[1]);

                if (! self::isSafeHttpUrl($url)) {
                    return $match[1];
                }

                return $stash(
                    '<a class="forum-rich__link" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
                    . e($url)
                    . '</a>'
                );
            },
            $html,
        );

        // A few passes are enough for normal nested BBCode while keeping the parser predictable.
        for ($i = 0; $i < 4; $i++) {
            $html = preg_replace('~\[b\](.*?)\[/b\]~is', '<strong>$1</strong>', $html);
            $html = preg_replace('~\[i\](.*?)\[/i\]~is', '<em>$1</em>', $html);
            $html = preg_replace('~\[u\](.*?)\[/u\]~is', '<u>$1</u>', $html);
            $html = preg_replace('~\[s\](.*?)\[/s\]~is', '<s>$1</s>', $html);
        }

        $html = preg_replace('~\[h2\](.*?)\[/h2\]~is', '<h2 class="forum-rich__h2">$1</h2>', $html);
        $html = preg_replace('~\[h3\](.*?)\[/h3\]~is', '<h3 class="forum-rich__h3">$1</h3>', $html);
        $html = str_ireplace('[hr]', '<hr class="forum-rich__hr">', $html);

        $html = preg_replace_callback(
            '~\[color=([^\]]+)\](.*?)\[/color\]~is',
            static function (array $match): string {
                $color = strtolower(trim(self::decoded($match[1])));
                $resolved = self::COLORS[$color] ?? null;

                if (! $resolved && preg_match('/^#[0-9a-f]{6}$/i', $color)) {
                    $resolved = $color;
                }

                if (! $resolved) {
                    return $match[2];
                }

                return '<span class="forum-rich__color" style="color:' . e($resolved) . '">' . $match[2] . '</span>';
            },
            $html,
        );

        $html = preg_replace_callback(
            '~\[quote(?:=([^\]]+))?\](.*?)\[/quote\]~is',
            static function (array $match): string {
                $author = isset($match[1]) ? trim(self::decoded($match[1])) : '';
                $caption = $author !== ''
                    ? '<div class="forum-rich__quote-author">' . e($author) . ' escribió:</div>'
                    : '';

                return '<blockquote class="forum-rich__quote">' . $caption . $match[2] . '</blockquote>';
            },
            $html,
        );

        $html = preg_replace_callback(
            '~\[spoiler(?:=([^\]]+))?\](.*?)\[/spoiler\]~is',
            static function (array $match): string {
                $label = isset($match[1]) && trim($match[1]) !== ''
                    ? self::decoded($match[1])
                    : 'Mostrar spoiler';

                return '<details class="forum-rich__spoiler">'
                    . '<summary>' . e($label) . '</summary>'
                    . '<div class="forum-rich__spoiler-body">' . $match[2] . '</div>'
                    . '</details>';
            },
            $html,
        );

        $html = preg_replace_callback(
            '~\[list\](.*?)\[/list\]~is',
            static function (array $match): string {
                $items = preg_split('~\[\*\]~', $match[1]);
                $items = array_values(array_filter(array_map('trim', $items), static fn (string $item): bool => $item !== ''));

                if ($items === []) {
                    return $match[1];
                }

                return '<ul class="forum-rich__list">'
                    . implode('', array_map(static fn (string $item): string => '<li>' . $item . '</li>', $items))
                    . '</ul>';
            },
            $html,
        );

        $html = nl2br($html, false);

        // Remove line breaks immediately around block-level fragments generated by the parser.
        $html = preg_replace('~(?:<br>\s*)+(<(?:h2|h3|hr|blockquote|details|ul|pre)\b)~i', '$1', $html);
        $html = preg_replace('~(</(?:h2|h3|blockquote|details|ul|pre)>)(?:\s*<br>)+~i', '$1', $html);

        if ($placeholders !== []) {
            $html = strtr($html, $placeholders);
        }

        return new HtmlString($html);
    }

    private static function decoded(string $value): string
    {
        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function isSafeHttpUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
