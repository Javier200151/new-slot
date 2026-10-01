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
        return BbcodeMarkup::render($value);
    }

    /**
     * Devuelve el contenido listo para editar como BBCode.
     */
    public static function toEditor(string | array | null $value): string
    {
        return BbcodeMarkup::toEditor($value);
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
