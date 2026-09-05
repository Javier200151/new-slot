(() => {
    const FIELD_SELECTOR = '[data-briefing-bbcode]';
    const COLORS = [
        ['#f8fafc', 'Blanco'],
        ['#94a3b8', 'Gris'],
        ['#f87171', 'Rojo'],
        ['#fb923c', 'Naranja'],
        ['#facc15', 'Amarillo'],
        ['#4ade80', 'Verde'],
        ['#22d3ee', 'Cian'],
        ['#60a5fa', 'Azul'],
        ['#c084fc', 'Morado'],
        ['#f472b6', 'Rosa'],
    ];

    const sync = (field) => {
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const replaceSelection = (field, value, selectionStart = null, selectionEnd = null) => {
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? start;

        field.setRangeText(value, start, end, 'end');
        sync(field);
        field.focus();

        if (selectionStart !== null) {
            const absoluteStart = start + selectionStart;
            const absoluteEnd = start + (selectionEnd ?? selectionStart);
            field.setSelectionRange(absoluteStart, absoluteEnd);
        }
    };

    const wrapSelection = (field, tag, parameter = null) => {
        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? start;
        const selected = field.value.slice(start, end);
        const open = parameter === null ? `[${tag}]` : `[${tag}=${parameter}]`;
        const close = `[/${tag}]`;
        const value = `${open}${selected}${close}`;

        replaceSelection(
            field,
            value,
            open.length,
            open.length + selected.length,
        );
    };

    const makeButton = (label, title, handler, className = '') => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `briefing-bbcode-toolbar__button ${className}`.trim();
        button.title = title;
        button.setAttribute('aria-label', title);
        button.innerHTML = label;
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            handler();
        });

        return button;
    };

    const initField = (field) => {
        if (!(field instanceof HTMLTextAreaElement)) {
            return;
        }

        const inputWrapper = field.closest('.fi-fo-textarea');

        if (!inputWrapper || !inputWrapper.parentElement) {
            return;
        }

        if (
            field.dataset.briefingBbcodeReady === '1'
            && inputWrapper.parentElement.querySelector(':scope > .briefing-bbcode-toolbar')
        ) {
            return;
        }

        field.dataset.briefingBbcodeReady = '1';

        const toolbar = document.createElement('div');
        toolbar.className = 'briefing-bbcode-toolbar';
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', 'Formato BBCode');

        toolbar.append(
            makeButton('<strong>B</strong>', 'Negrita', () => wrapSelection(field, 'b')),
            makeButton('<em>I</em>', 'Cursiva', () => wrapSelection(field, 'i')),
            makeButton('<u>U</u>', 'Subrayado', () => wrapSelection(field, 'u')),
            makeButton('<s>S</s>', 'Tachado', () => wrapSelection(field, 's')),
            makeButton('H2', 'Título grande', () => wrapSelection(field, 'h2')),
            makeButton('H3', 'Subtítulo', () => wrapSelection(field, 'h3')),
            makeButton('❝', 'Cita', () => {
                const author = window.prompt('Autor de la cita (opcional):', '') ?? '';
                wrapSelection(field, 'quote', author.trim() || null);
            }),
            makeButton('Spoiler', 'Spoiler', () => {
                const label = window.prompt('Texto del spoiler (opcional):', '') ?? '';
                wrapSelection(field, 'spoiler', label.trim() || null);
            }),
            makeButton('&lt;/&gt;', 'Código', () => wrapSelection(field, 'code')),
            makeButton('☷', 'Lista', () => {
                const start = field.selectionStart ?? field.value.length;
                const end = field.selectionEnd ?? start;
                const selected = field.value.slice(start, end).trim();
                const lines = selected === ''
                    ? ['Elemento']
                    : selected.split(/\n+/).map((line) => line.trim()).filter(Boolean);
                const value = `[list]\n${lines.map((line) => `[*]${line}`).join('\n')}\n[/list]`;
                replaceSelection(field, value);
            }),
            makeButton('🔗', 'Enlace', () => {
                const url = window.prompt('URL http(s):', 'https://');
                if (!url) return;
                wrapSelection(field, 'url', url.trim());
            }),
            makeButton('🖼', 'Imagen por URL', () => {
                const url = window.prompt('URL de la imagen http(s):', 'https://');
                if (!url) return;
                replaceSelection(field, `[img]${url.trim()}[/img]`);
            }),
            makeButton('―', 'Separador', () => replaceSelection(field, '[hr]')),
        );

        const colors = document.createElement('span');
        colors.className = 'briefing-bbcode-toolbar__colors';
        colors.setAttribute('aria-label', 'Color de texto');

        COLORS.forEach(([color, label]) => {
            const button = makeButton('', label, () => wrapSelection(field, 'color', color), 'briefing-bbcode-toolbar__color');
            button.style.setProperty('--bbcode-color', color);
            colors.append(button);
        });

        toolbar.append(colors);
        inputWrapper.parentElement.insertBefore(toolbar, inputWrapper);
    };

    const initAll = (root = document) => {
        root.querySelectorAll?.(FIELD_SELECTOR).forEach(initField);

        if (root.matches?.(FIELD_SELECTOR)) {
            initField(root);
        }
    };

    document.addEventListener('DOMContentLoaded', () => initAll());
    document.addEventListener('livewire:navigated', () => initAll());

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node instanceof Element) {
                    initAll(node);
                }
            }
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
})();
