<?php

namespace App\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class BriefingMarkup
{
    /**
     * Renderiza BBCode usando el parser seguro ya compartido por foro/AAR.
     * Si el texto procede del antiguo RichEditor, primero lo convierte a
     * BBCode para conservar compatibilidad con briefings existentes.
     */
    public static function render(string | array | null $value): HtmlString
    {
        return ForumMarkup::render(self::toEditor($value));
    }

    /**
     * Devuelve el contenido listo para editar como BBCode.
     */
    public static function toEditor(string | array | null $value): string
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
                $url = html_entity_decode(
                    self::firstMatchedValue($match, [1, 2, 3]),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                );

                if (! self::isSafeHttpUrl($url)) {
                    return '';
                }

                return '[img]' . $url . '[/img]';
            },
            $html,
        );

        $html = preg_replace_callback(
            '~<a\b[^>]*\bhref=(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))[^>]*>(.*?)</a>~is',
            static function (array $match): string {
                $url = html_entity_decode(
                    self::firstMatchedValue($match, [1, 2, 3]),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                );
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
            '~<span\b[^>]*\bstyle=(?:"([^"]*)"|\'([^\']*)\')[^>]*>(.*?)</span>~is',
            static function (array $match): string {
                $style = html_entity_decode(
                    self::firstMatchedValue($match, [1, 2]),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                );
                $content = $match[3] ?? '';

                if (! preg_match('~(?:^|;)\s*color\s*:\s*([^;]+)~i', $style, $colorMatch)) {
                    return $content;
                }

                $color = trim($colorMatch[1]);

                if (! preg_match('/^(?:#[0-9a-f]{6}|white|gray|red|orange|yellow|green|cyan|blue|purple|pink)$/i', $color)) {
                    return $content;
                }

                return '[color=' . $color . ']' . $content . '[/color]';
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
            '~<\s*(?:ul|ol)\b[^>]*>~i' => '[list]',
            '~<\s*/\s*(?:ul|ol)\s*>~i' => '[/list]',
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

    /**
     * Normaliza la referencia que se guarda dentro del JSON del briefing.
     * Acepta rutas del disco public y mantiene URLs http(s) antiguas.
     */
    public static function normalizeImageReference(?string $value): ?string
    {
        $image = trim((string) $value);

        if ($image === '') {
            return null;
        }

        if (self::isSafeHttpUrl($image)) {
            return $image;
        }

        $image = ltrim(str_replace('\\', '/', $image), '/');

        if (
            $image === ''
            || str_contains($image, '..')
            || str_contains($image, '://')
        ) {
            return null;
        }

        return $image;
    }

    public static function imageUrl(?string $value): ?string
    {
        $image = self::normalizeImageReference($value);

        if ($image === null) {
            return null;
        }

        if (self::isSafeHttpUrl($image)) {
            return $image;
        }

        return Storage::disk('public')->url($image);
    }


    /**
     * @param  array<int, string>  $match
     * @param  array<int>  $indexes
     */
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

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }
}
