(() => {
    'use strict';

    const wrapSelection = (textarea, tag, argument = null, placeholder = 'texto') => {
        const start = textarea.selectionStart ?? 0;
        const end = textarea.selectionEnd ?? start;
        const selected = textarea.value.slice(start, end) || placeholder;
        const open = argument === null ? `[${tag}]` : `[${tag}=${argument}]`;
        textarea.setRangeText(`${open}${selected}[/${tag}]`, start, end, 'select');
        if (start === end) textarea.setSelectionRange(start + open.length, start + open.length + selected.length);
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const insertText = (textarea, text) => {
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? start;
        textarea.setRangeText(text, start, end, 'end');
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const safeUrl = (value) => {
        if (!value) return null;
        try {
            const url = new URL(value.trim());
            return ['http:', 'https:'].includes(url.protocol) ? url.toString() : null;
        } catch (_) {
            return null;
        }
    };

    const insertList = (textarea, ordered) => {
        const start = textarea.selectionStart ?? 0;
        const end = textarea.selectionEnd ?? start;
        const selected = textarea.value.slice(start, end).trim();
        const items = selected ? selected.split(/\r?\n/).map(x => x.trim()).filter(Boolean) : ['Elemento 1', 'Elemento 2'];
        const opening = ordered ? '[list=1]' : '[list]';
        textarea.setRangeText(`${opening}\n${items.map(x => `[*]${x}`).join('\n')}\n[/list]`, start, end, 'select');
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const button = (label, title, handler) => {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = 'ns-bbcode-toolbar__button';
        el.textContent = label;
        el.title = title;
        el.addEventListener('click', handler);
        return el;
    };

    const init = (textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.newslotBbcodeReady === '1') return;
        if (textarea.disabled || textarea.readOnly) return;
        textarea.dataset.newslotBbcodeReady = '1';

        const toolbar = document.createElement('div');
        toolbar.className = 'ns-bbcode-toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Formato BBCode');

        const addWrap = (label, title, tag) => toolbar.append(button(label, title, () => wrapSelection(textarea, tag)));
        addWrap('B', 'Negrita', 'b');
        addWrap('I', 'Cursiva', 'i');
        addWrap('U', 'Subrayado', 'u');
        addWrap('S', 'Tachado', 's');
        addWrap('H2', 'Título', 'h2');
        addWrap('H3', 'Subtítulo', 'h3');
        addWrap('↤', 'Alinear izquierda', 'left');
        addWrap('↔', 'Centrar', 'center');
        addWrap('↦', 'Alinear derecha', 'right');
        toolbar.append(button('❝', 'Cita', () => {
            const author = (window.prompt('Autor de la cita (opcional):', '') || '').replace(/[\[\]]/g, '').slice(0, 80);
            wrapSelection(textarea, 'quote', author || null, 'texto citado');
        }));
        toolbar.append(button('Spoiler', 'Spoiler', () => {
            const title = (window.prompt('Título del spoiler:', 'Mostrar spoiler') || 'Mostrar spoiler').replace(/[\[\]]/g, '').slice(0, 80);
            wrapSelection(textarea, 'spoiler', title, 'contenido oculto');
        }));
        toolbar.append(button('</>', 'Código', () => wrapSelection(textarea, 'code', null, 'código')));
        toolbar.append(button('☷', 'Lista', () => insertList(textarea, false)));
        toolbar.append(button('1.', 'Lista numerada', () => insertList(textarea, true)));
        toolbar.append(button('🔗', 'Enlace', () => {
            const url = safeUrl(window.prompt('URL del enlace (http/https):', 'https://'));
            if (url) wrapSelection(textarea, 'url', url, 'texto del enlace');
        }));
        toolbar.append(button('🖼', 'Imagen o GIF por URL', () => {
            const url = safeUrl(window.prompt('URL de la imagen o GIF (http/https):', 'https://'));
            if (url) insertText(textarea, `[img]${url}[/img]`);
        }));
        toolbar.append(button('―', 'Separador', () => insertText(textarea, '\n[hr]\n')));

        const colors = document.createElement('span');
        colors.className = 'ns-bbcode-toolbar__colors';
        [
            ['#f8fafc', 'Blanco'], ['#94a3b8', 'Gris'], ['#f87171', 'Rojo'],
            ['#fb923c', 'Naranja'], ['#facc15', 'Amarillo'], ['#4ade80', 'Verde'],
            ['#22d3ee', 'Cian'], ['#60a5fa', 'Azul'], ['#c084fc', 'Morado'], ['#f472b6', 'Rosa'],
        ].forEach(([color, label]) => {
            const colorButton = button('', label, () => wrapSelection(textarea, 'color', color));
            colorButton.classList.add('ns-bbcode-toolbar__color');
            colorButton.style.setProperty('--ns-bbcode-color', color);
            colors.append(colorButton);
        });
        toolbar.append(colors);

        textarea.parentNode?.insertBefore(toolbar, textarea);
        textarea.classList.add('ns-bbcode-textarea');
    };

    const scan = (root = document) => root.querySelectorAll?.('textarea[data-newslot-bbcode="1"]').forEach(init);
    const boot = () => scan(document);

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
    document.addEventListener('livewire:navigated', boot);

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (!(node instanceof Element)) continue;
                if (node.matches?.('textarea[data-newslot-bbcode="1"]')) init(node);
                scan(node);
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
