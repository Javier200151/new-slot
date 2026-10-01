<?php

namespace App\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;

class BbcodeMarkup
{
    private const COLORS = [
        'white' => '#f8fafc',
        'gray' => '#94a3b8',
        'grey' => '#94a3b8',
        'red' => '#f87171',
        'orange' => '#fb923c',
        'yellow' => '#facc15',
        'green' => '#4ade80',
        'cyan' => '#22d3ee',
        'blue' => '#60a5fa',
        'purple' => '#c084fc',
        'pink' => '#f472b6',
    ];

    public static function render(string|array|null $value): HtmlString
    {
        $text = self::toEditor($value);

        if ($text === '') {
            return new HtmlString('');
        }

        $html = e(str_replace(["\r\n", "\r"], "\n", $text));
        $placeholders = [];

        $stash = static function (string $fragment) use (&$placeholders): string {
            $key = '%%BBCODE_FRAGMENT_' . count($placeholders) . '%%';
            $placeholders[$key] = $fragment;

            return $key;
        };

        // Code goes first so BBCode inside a code block stays literal.
        $html = preg_replace_callback(
            '~\[code\](.*?)\[/code\]~is',
            static fn (array $match): string => $stash(
                '<pre class="forum-rich__code bbcode-rich__code"><code>' . $match[1] . '</code></pre>'
            ),
            $html,
        );

        $html = preg_replace_callback(
            '~\[img\](.*?)\[/img\]~is',
            static function (array $match) use ($stash): string {
                $url = self::decoded($match[1]);

                if (! self::isSafeHttpUrl($url)) {
                    return $match[0];
                }

                return $stash(
                    '<a class="forum-rich__image-link bbcode-rich__image-link" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
                    . '<img class="forum-rich__image bbcode-rich__image" src="' . e($url) . '" alt="Imagen insertada" loading="lazy" referrerpolicy="no-referrer">'
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
                    '<a class="forum-rich__link bbcode-rich__link" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
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
                    '<a class="forum-rich__link bbcode-rich__link" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
                    . e($url)
                    . '</a>'
                );
            },
            $html,
        );

        // Repeated passes allow normal nesting of inline tags.
        for ($i = 0; $i < 6; $i++) {
            $html = preg_replace('~\[b\](.*?)\[/b\]~is', '<strong>$1</strong>', $html);
            $html = preg_replace('~\[i\](.*?)\[/i\]~is', '<em>$1</em>', $html);
            $html = preg_replace('~\[u\](.*?)\[/u\]~is', '<u>$1</u>', $html);
            $html = preg_replace('~\[s\](.*?)\[/s\]~is', '<s>$1</s>', $html);
            $html = preg_replace('~\[sup\](.*?)\[/sup\]~is', '<sup>$1</sup>', $html);
            $html = preg_replace('~\[sub\](.*?)\[/sub\]~is', '<sub>$1</sub>', $html);
        }

        $html = preg_replace('~\[h1\](.*?)\[/h1\]~is', '<h2 class="forum-rich__h2 bbcode-rich__h2">$1</h2>', $html);
        $html = preg_replace('~\[h2\](.*?)\[/h2\]~is', '<h2 class="forum-rich__h2 bbcode-rich__h2">$1</h2>', $html);
        $html = preg_replace('~\[h3\](.*?)\[/h3\]~is', '<h3 class="forum-rich__h3 bbcode-rich__h3">$1</h3>', $html);
        $html = str_ireplace('[hr]', '<hr class="forum-rich__hr bbcode-rich__hr">', $html);

        foreach (['left', 'center', 'right', 'justify'] as $alignment) {
            $html = preg_replace(
                '~\[' . $alignment . '\](.*?)\[/' . $alignment . '\]~is',
                '<div class="bbcode-rich__align bbcode-rich__align--' . $alignment . '">$1</div>',
                $html,
            );
        }

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

                // Keep the legacy RichEditor color contract used by existing content/CSS.
                // The value is still constrained to our safe named palette or a #RRGGBB color.
                return '<span class="color" data-color="' . e($color) . '" style="--color:' . e($resolved) . '; color: var(--color);">'
                    . $match[2]
                    . '</span>';
            },
            $html,
        );

        $html = preg_replace_callback(
            '~\[size=([^\]]+)\](.*?)\[/size\]~is',
            static function (array $match): string {
                $value = strtolower(trim(self::decoded($match[1])));
                $sizes = [
                    'small' => '.85em', 'normal' => '1em', 'large' => '1.2em',
                    '1' => '.75em', '2' => '.85em', '3' => '1em', '4' => '1.15em',
                    '5' => '1.3em', '6' => '1.5em', '7' => '1.75em',
                ];

                return isset($sizes[$value])
                    ? '<span class="bbcode-rich__size" style="font-size:' . $sizes[$value] . '">' . $match[2] . '</span>'
                    : $match[2];
            },
            $html,
        );

        $html = preg_replace_callback(
            '~\[quote(?:=([^\]]+))?\](.*?)\[/quote\]~is',
            static function (array $match): string {
                $author = isset($match[1]) ? trim(self::decoded($match[1])) : '';
                $caption = $author !== ''
                    ? '<div class="forum-rich__quote-author bbcode-rich__quote-author">' . e($author) . ' escribió:</div>'
                    : '';

                return '<blockquote class="forum-rich__quote bbcode-rich__quote">' . $caption . $match[2] . '</blockquote>';
            },
            $html,
        );

        $html = preg_replace_callback(
            '~\[spoiler(?:=([^\]]+))?\](.*?)\[/spoiler\]~is',
            static function (array $match): string {
                $label = isset($match[1]) && trim($match[1]) !== ''
                    ? self::decoded($match[1])
                    : 'Mostrar spoiler';

                return '<details class="forum-rich__spoiler bbcode-rich__spoiler">'
                    . '<summary>' . e($label) . '</summary>'
                    . '<div class="forum-rich__spoiler-body bbcode-rich__spoiler-body">' . $match[2] . '</div>'
                    . '</details>';
            },
            $html,
        );

        // Lists. Multiple passes cover the common case of nested lists.
        for ($i = 0; $i < 5; $i++) {
            $before = $html;
            $html = self::replaceLists($html);
            if ($html === $before) {
                break;
            }
        }

        $html = nl2br($html, false);

        // Remove automatic <br> around block-level BBCode output.
        $html = preg_replace('~(?:<br>\s*)+(<(?:h2|h3|hr|blockquote|details|ul|ol|pre|div)\b)~i', '$1', $html);
        $html = preg_replace('~(</(?:h2|h3|blockquote|details|ul|ol|pre|div)>)(?:\s*<br>)+~i', '$1', $html);

        if ($placeholders !== []) {
            $html = strtr($html, $placeholders);
        }

        return new HtmlString($html);
    }

    public static function toEditor(string|array|null $value): string
    {
        if (is_array($value)) {
            $value = RichContentRenderer::make($value)->toHtml();
        }

        $text = trim((string) $value);

        if ($text === '' || ! self::looksLikeLegacyHtml($text)) {
            return $text;
        }

        $html = str_replace(["\r\n", "\r"], "\n", $text);

        $html = preg_replace_callback(
            '~<img\b[^>]*\bsrc=(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>~i',
            static function (array $match): string {
                $url = html_entity_decode(self::firstMatchedValue($match, [1, 2, 3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return self::isSafeHttpUrl($url) ? '[img]' . $url . '[/img]' : '';
            },
            $html,
        );

        $html = preg_replace_callback(
            '~<a\b[^>]*\bhref=(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</a>~is',
            static function (array $match): string {
                $url = html_entity_decode(self::firstMatchedValue($match, [1, 2, 3]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $label = $match[4] ?? '';
                if (! self::isSafeHttpUrl($url)) {
                    return $label;
                }
                $url = str_replace([']', "\n"], '', $url);
                return '[url=' . $url . ']' . $label . '[/url]';
            },
            $html,
        );

        $html = preg_replace_callback(
            '~<span\b[^>]*\bdata-color=(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</span>~is',
            static function (array $match): string {
                $color = strtolower(trim(html_entity_decode(
                    self::firstMatchedValue($match, [1, 2, 3]),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                )));
                $content = $match[4] ?? '';

                if (isset(self::COLORS[$color]) || preg_match('/^#[0-9a-f]{6}$/i', $color)) {
                    return '[color=' . $color . ']' . $content . '[/color]';
                }

                return $content;
            },
            $html,
        );

        $html = preg_replace_callback(
            '~<span\b[^>]*\bstyle=(?:"([^"]*)"|\'([^\']*)\')[^>]*>(.*?)</span>~is',
            static function (array $match): string {
                $style = html_entity_decode(self::firstMatchedValue($match, [1, 2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $content = $match[3] ?? '';
                if (preg_match('~(?:^|;)\s*color\s*:\s*([^;]+)~i', $style, $colorMatch)) {
                    $color = trim($colorMatch[1]);
                    if (preg_match('/^(?:#[0-9a-f]{6}|white|gray|grey|red|orange|yellow|green|cyan|blue|purple|pink)$/i', $color)) {
                        return '[color=' . $color . ']' . $content . '[/color]';
                    }
                }
                return $content;
            },
            $html,
        );

        $replacements = [
            '~<\s*(?:strong|b)\b[^>]*>~i' => '[b]',
            '~<\s*/\s*(?:strong|b)\s*>~i' => '[/b]',
            '~<\s*(?:em|i)\b[^>]*>~i' => '[i]',
            '~<\s*/\s*(?:em|i)\s*>~i' => '[/i]',
            '~<\s*u\b[^>]*>~i' => '[u]',
            '~<\s*/\s*u\s*>~i' => '[/u]',
            '~<\s*(?:s|strike)\b[^>]*>~i' => '[s]',
            '~<\s*/\s*(?:s|strike)\s*>~i' => '[/s]',
            '~<\s*h[12]\b[^>]*>~i' => '[h2]',
            '~<\s*/\s*h[12]\s*>~i' => '[/h2]',
            '~<\s*h[3-6]\b[^>]*>~i' => '[h3]',
            '~<\s*/\s*h[3-6]\s*>~i' => '[/h3]',
            '~<\s*blockquote\b[^>]*>~i' => '[quote]',
            '~<\s*/\s*blockquote\s*>~i' => '[/quote]',
            '~<\s*pre\b[^>]*>~i' => '[code]',
            '~<\s*/\s*pre\s*>~i' => '[/code]',
            '~<\s*code\b[^>]*>~i' => '',
            '~<\s*/\s*code\s*>~i' => '',
            '~<\s*ul\b[^>]*>~i' => '[list]',
            '~<\s*/\s*ul\s*>~i' => '[/list]',
            '~<\s*ol\b[^>]*>~i' => '[list=1]',
            '~<\s*/\s*ol\s*>~i' => '[/list]',
            '~<\s*li\b[^>]*>~i' => '[*]',
            '~<\s*/\s*li\s*>~i' => "\n",
            '~<\s*hr\b[^>]*>~i' => '[hr]',
            '~<\s*br\s*/?\s*>~i' => "\n",
            '~<\s*(?:p|div)\b[^>]*>~i' => '',
            '~<\s*/\s*(?:p|div)\s*>~i' => "\n\n",
        ];

        foreach ($replacements as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html);
        }

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n[\t ]+\n/", "\n\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    private static function replaceLists(string $html): string
    {
        $patterns = [
            ['~\[list(?:=([^\]]+))?\]((?:(?!\[(?:list|ul|ol)(?:=|\])|\[/(?:list|ul|ol)\]).)*)\[/list\]~is', 'list'],
            ['~\[ul\]((?:(?!\[(?:list|ul|ol)(?:=|\])|\[/(?:list|ul|ol)\]).)*)\[/ul\]~is', 'ul'],
            ['~\[ol\]((?:(?!\[(?:list|ul|ol)(?:=|\])|\[/(?:list|ul|ol)\]).)*)\[/ol\]~is', 'ol'],
        ];

        foreach ($patterns as [$pattern, $kind]) {
            $html = preg_replace_callback($pattern, static function (array $match) use ($kind): string {
                $argument = $kind === 'list' ? trim(self::decoded($match[1] ?? '')) : '';
                $content = $kind === 'list' ? ($match[2] ?? '') : ($match[1] ?? '');

                $orderedTypes = ['1', 'a', 'A', 'i', 'I'];
                $unorderedTypes = ['disc', 'circle', 'square'];

                $ordered = $kind === 'ol' || ($kind === 'list' && in_array($argument, $orderedTypes, true));
                $unorderedStyle = $kind === 'list' && in_array(strtolower($argument), $unorderedTypes, true)
                    ? strtolower($argument)
                    : null;

                // Any unknown [list=x] keeps the historical ordered-list behaviour.
                if ($kind === 'list' && $argument !== '' && ! $ordered && $unorderedStyle === null) {
                    $ordered = true;
                }

                $type = $ordered
                    ? (in_array($argument, $orderedTypes, true) ? $argument : '1')
                    : null;

                // Accept both [*]item and [li]item[/li].
                $content = preg_replace('~\[li\](.*?)\[/li\]~is', '[*]$1', $content);
                $items = preg_split('~\[\*\]~', $content) ?: [];
                $items = array_values(array_filter(
                    array_map('trim', $items),
                    static fn (string $item): bool => $item !== ''
                ));

                if ($items === []) {
                    return $content;
                }

                $tag = $ordered ? 'ol' : 'ul';
                $attributes = ' class="forum-rich__list bbcode-rich__list"';

                if ($ordered && $type) {
                    $attributes .= ' type="' . e($type) . '"';
                } elseif ($unorderedStyle) {
                    $attributes .= ' style="list-style-type:' . e($unorderedStyle) . '"';
                }

                return '<' . $tag . $attributes . '>'
                    . implode('', array_map(static fn (string $item): string => '<li>' . $item . '</li>', $items))
                    . '</' . $tag . '>';
            }, $html);
        }

        return $html;
    }

    private static function decoded(string $value): string
    {
        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function firstMatchedValue(array $match, array $indexes): string
    {
        foreach ($indexes as $index) {
            $value = (string) ($match[$index] ?? '');
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private static function looksLikeLegacyHtml(string $value): bool
    {
        return preg_match(
            '~</?(?:p|br|strong|b|em|i|u|s|strike|h[1-6]|a|img|ul|ol|li|blockquote|pre|code|span|div|hr)\b~i',
            $value,
        ) === 1;
    }

    private static function isSafeHttpUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
