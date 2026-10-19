<?php

namespace App\Filament\Forms;

use Illuminate\Support\HtmlString;

/**
 * Visible in server-rendered Filament form markup, including repeater fields.
 * Uses Filament's existing Alpine runtime; no external editor packages.
 */
final class BbcodeToolbar
{
    public static function make(): HtmlString
    {
        $alpine = <<<'JS'
{
    field(source) {
        const wrapper = source.closest('[data-field-wrapper]')
            ?? source.closest('.fi-fo-field-wrp')
            ?? source.closest('.fi-fo-textarea-wrp');
        return wrapper?.querySelector('textarea') ?? null;
    },
    changed(input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.focus();
    },
    replace(source, value, relativeStart = null, relativeEnd = null) {
        const input = this.field(source);
        if (!input) return;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? start;
        input.setRangeText(value, start, end, 'end');
        if (relativeStart !== null) {
            input.setSelectionRange(start + relativeStart, start + (relativeEnd ?? relativeStart));
        }
        this.changed(input);
    },
    wrap(source, tag, parameter = null) {
        const input = this.field(source);
        if (!input) return;
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? start;
        const selection = input.value.slice(start, end) || 'texto';
        const open = parameter === null ? `[${tag}]` : `[${tag}=${parameter}]`;
        this.replace(source, `${open}${selection}[/${tag}]`, open.length, open.length + selection.length);
    },
    promptValue(source, tag, message, placeholder = '') {
        const value = window.prompt(message, placeholder);
        if (value === null) return;
        const clean = value.trim().replace(/[\[\]\r\n]/g, '').slice(0, 100);
        this.wrap(source, tag, clean || null);
    },
    url(source, image = false) {
        const value = window.prompt(image ? 'URL de imagen o GIF (http/https):' : 'URL del enlace (http/https):', 'https://');
        if (!value) return;
        let url;
        try {
            const parsed = new URL(value.trim());
            if (!['http:', 'https:'].includes(parsed.protocol)) return;
            url = parsed.toString();
        } catch (_) { return; }
        if (image) this.replace(source, `[img]${url}[/img]`);
        else this.wrap(source, 'url', url);
    },
    list(source, numbered = false) {
        const input = this.field(source);
        if (!input) return;
        const selected = input.value.slice(input.selectionStart, input.selectionEnd).trim();
        const items = selected ? selected.split(/\r?\n/).map(x => x.trim()).filter(Boolean) : ['Elemento 1', 'Elemento 2'];
        this.replace(source, `[list${numbered ? '=1' : ''}]\n${items.map(x => `[*]${x}`).join('\n')}\n[/list]`);
    },
}
JS;

        $buttons = [
            ['B', 'Negrita', "wrap(\$el, 'b')"],
            ['I', 'Cursiva', "wrap(\$el, 'i')"],
            ['U', 'Subrayado', "wrap(\$el, 'u')"],
            ['S', 'Tachado', "wrap(\$el, 's')"],
            ['H1', 'Encabezado 1', "wrap(\$el, 'h1')"],
            ['H2', 'Encabezado 2', "wrap(\$el, 'h2')"],
            ['H3', 'Encabezado 3', "wrap(\$el, 'h3')"],
            ['H4', 'Encabezado 4', "wrap(\$el, 'h4')"],
            ['H5', 'Encabezado 5', "wrap(\$el, 'h5')"],
            ['H6', 'Encabezado 6', "wrap(\$el, 'h6')"],
            ['↤', 'Alinear izquierda', "wrap(\$el, 'left')"],
            ['↔', 'Centrar', "wrap(\$el, 'center')"],
            ['↦', 'Alinear derecha', "wrap(\$el, 'right')"],
            ['≡', 'Justificar', "wrap(\$el, 'justify')"],
            ['❝', 'Cita', "promptValue(\$el, 'quote', 'Autor de la cita (opcional):')"],
            ['Spoiler', 'Spoiler', "promptValue(\$el, 'spoiler', 'Título del spoiler (opcional):')"],
            ['</>', 'Código', "wrap(\$el, 'code')"],
            ['☷', 'Lista', 'list($el)'],
            ['1.', 'Lista numerada', 'list($el, true)'],
            ['🔗', 'Enlace', 'url($el)'],
            ['🖼', 'Imagen o GIF', 'url($el, true)'],
            ['―', 'Separador', "replace(\$el, '[hr]')"],
        ];
        $html = '<div class="ns-bbcode-toolbar" data-sqa-bbcode-toolbar role="toolbar" aria-label="Formato BBCode" x-data="' . e($alpine) . '">';
        foreach ($buttons as [$label, $title, $action]) {
            $html .= '<button type="button" class="ns-bbcode-toolbar__button" title="'
                . e($title) . '" aria-label="' . e($title)
                . '" x-on:click.prevent.stop="' . e($action) . '">' . e($label) . '</button>';
        }

        $colors = [
            '#f8fafc' => 'Blanco', '#94a3b8' => 'Gris', '#f87171' => 'Rojo',
            '#fb923c' => 'Naranja', '#facc15' => 'Amarillo', '#4ade80' => 'Verde',
            '#22d3ee' => 'Cian', '#60a5fa' => 'Azul', '#c084fc' => 'Morado',
            '#f472b6' => 'Rosa',
        ];
        $html .= '<span class="ns-bbcode-toolbar__colors" aria-label="Color de texto">';
        foreach ($colors as $color => $label) {
            $html .= '<button type="button" class="ns-bbcode-toolbar__button ns-bbcode-toolbar__color"'
                . ' title="' . e($label) . '" aria-label="' . e($label) . '"'
                . ' style="--ns-bbcode-color:' . e($color) . '"'
                . ' x-on:click.prevent.stop="' . e("wrap(\$el, 'color', '{$color}')") . '"></button>';
        }

        return new HtmlString($html . '</span></div>');
    }
}
