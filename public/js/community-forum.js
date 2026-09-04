(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    const insertText = (textarea, text, cursorOffset = null) => {
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? start;
        textarea.setRangeText(text, start, end, 'end');
        textarea.focus();

        if (cursorOffset !== null) {
            const cursor = start + cursorOffset;
            textarea.setSelectionRange(cursor, cursor);
        }

        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const wrapSelection = (textarea, tag, argument = null, placeholder = 'texto') => {
        const start = textarea.selectionStart ?? 0;
        const end = textarea.selectionEnd ?? start;
        const selected = textarea.value.slice(start, end) || placeholder;
        const opening = argument === null ? `[${tag}]` : `[${tag}=${argument}]`;
        const closing = `[/${tag}]`;
        const replacement = `${opening}${selected}${closing}`;

        textarea.setRangeText(replacement, start, end, 'select');

        if (start === end) {
            const selectionStart = start + opening.length;
            textarea.setSelectionRange(selectionStart, selectionStart + selected.length);
        }

        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
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

    const initEditor = (editor) => {
        if (!editor || editor.dataset.forumEditorInitialized === '1') return;

        const textarea = editor.querySelector('.forum-editor__textarea');
        if (!textarea) return;

        editor.dataset.forumEditorInitialized = '1';

        editor.querySelectorAll('[data-forum-wrap]').forEach((button) => {
            button.addEventListener('click', () => wrapSelection(textarea, button.dataset.forumWrap));
        });

        editor.querySelectorAll('[data-forum-color]').forEach((button) => {
            button.addEventListener('click', () => wrapSelection(textarea, 'color', button.dataset.forumColor));
        });

        editor.querySelectorAll('[data-forum-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.dataset.forumAction;

                if (action === 'image') {
                    const url = safeUrl(window.prompt('URL de la imagen (http/https):', 'https://'));
                    if (!url) return;
                    insertText(textarea, `[img]${url}[/img]`);
                    return;
                }

                if (action === 'link') {
                    const url = safeUrl(window.prompt('URL del enlace (http/https):', 'https://'));
                    if (!url) return;
                    wrapSelection(textarea, 'url', url, 'texto del enlace');
                    return;
                }

                if (action === 'spoiler') {
                    const title = (window.prompt('Título del spoiler:', 'Mostrar spoiler') || 'Mostrar spoiler')
                        .replace(/[\[\]]/g, '')
                        .slice(0, 80);
                    wrapSelection(textarea, 'spoiler', title, 'contenido oculto');
                    return;
                }

                if (action === 'quote') {
                    const author = (window.prompt('Autor de la cita (opcional):', '') || '')
                        .replace(/[\[\]]/g, '')
                        .slice(0, 80);
                    wrapSelection(textarea, 'quote', author || null, 'texto citado');
                    return;
                }

                if (action === 'list') {
                    const start = textarea.selectionStart ?? 0;
                    const end = textarea.selectionEnd ?? start;
                    const selected = textarea.value.slice(start, end).trim();
                    const items = selected
                        ? selected.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
                        : ['Elemento 1', 'Elemento 2'];
                    const replacement = `[list]\n${items.map((item) => `[*]${item}`).join('\n')}\n[/list]`;
                    textarea.setRangeText(replacement, start, end, 'select');
                    textarea.focus();
                    return;
                }

                if (action === 'hr') {
                    insertText(textarea, '\n[hr]\n');
                }
            });
        });

        textarea.addEventListener('keydown', (event) => {
            if (!(event.ctrlKey || event.metaKey)) return;

            const shortcut = event.key.toLowerCase();
            const tag = { b: 'b', i: 'i', u: 'u' }[shortcut];
            if (!tag) return;

            event.preventDefault();
            wrapSelection(textarea, tag);
        });
    };

    const initCompose = () => {
        const compose = document.querySelector('[data-forum-compose]');
        if (!compose) return;

        document.querySelectorAll('[data-forum-compose-open]').forEach((button) => {
            button.addEventListener('click', () => {
                compose.hidden = false;
                compose.scrollIntoView({ behavior: 'smooth', block: 'start' });
                compose.querySelector('input[name="title"]')?.focus();
            });
        });

        document.querySelectorAll('[data-forum-compose-close]').forEach((button) => {
            button.addEventListener('click', () => {
                compose.hidden = true;
            });
        });
    };

    const syncThreadType = () => {
        const selected = document.querySelector('[data-forum-thread-type]:checked');
        const processConfig = document.querySelector('[data-forum-process-config]');
        if (!selected || !processConfig) return;

        processConfig.hidden = selected.value !== 'convocatoria';
    };

    const initThreadTypes = () => {
        document.querySelectorAll('[data-forum-thread-type]').forEach((input) => {
            input.addEventListener('change', syncThreadType);
        });
        syncThreadType();
    };

    const syncPollConfig = (scope = document) => {
        scope.querySelectorAll('[data-forum-poll-toggle]').forEach((toggle) => {
            const form = toggle.closest('form');
            const config = form?.querySelector('[data-forum-poll-config]');
            if (config) config.hidden = !toggle.checked;
        });

        scope.querySelectorAll('[data-forum-poll-mode]').forEach((mode) => {
            const config = mode.closest('[data-forum-poll-config]') || mode.closest('form');
            config?.querySelectorAll('[data-forum-multiple-only]').forEach((field) => {
                field.hidden = mode.value !== 'multiple';
            });
        });

        scope.querySelectorAll('[data-forum-candidate-toggle]').forEach((toggle) => {
            const config = toggle.closest('[data-forum-poll-config]');
            const manual = config?.querySelector('[data-forum-manual-options]');
            if (manual) manual.hidden = toggle.checked;
        });
    };

    const initPollConfig = () => {
        document.querySelectorAll('[data-forum-poll-toggle], [data-forum-poll-mode], [data-forum-candidate-toggle]')
            .forEach((input) => input.addEventListener('change', () => syncPollConfig(input.closest('form') || document)));
        syncPollConfig();
    };

    const initQuoteButtons = () => {
        document.querySelectorAll('.forum-quote-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.getElementById(button.dataset.forumQuoteTarget || 'reply-body');
                const source = document.getElementById(button.dataset.forumQuoteSource || '');
                if (!target || !source) return;

                const author = (button.dataset.forumQuoteAuthor || 'Usuario').replace(/[\[\]]/g, '');
                const content = (source.content?.textContent || source.textContent || '').trim();
                const quote = `[quote=${author}]${content}[/quote]\n\n`;

                insertText(target, quote);
                document.getElementById('responder')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    };

    window.NewSlotForumEditor = Object.assign(window.NewSlotForumEditor || {}, {
        init: initEditor,
        initAll(scope = document) {
            scope.querySelectorAll('[data-forum-editor]').forEach(initEditor);
        },
    });

    ready(() => {
        window.NewSlotForumEditor.initAll();
        initCompose();
        initThreadTypes();
        initPollConfig();
        initQuoteButtons();
    });
})();
