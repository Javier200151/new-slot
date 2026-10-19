(() => {
    'use strict';

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
        textarea.setRangeText(`${opening}${selected}${closing}`, start, end, 'select');

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

    const insertList = (textarea, ordered = false) => {
        const start = textarea.selectionStart ?? 0;
        const end = textarea.selectionEnd ?? start;
        const selected = textarea.value.slice(start, end).trim();
        const items = selected
            ? selected.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)
            : ['Elemento 1', 'Elemento 2'];
        const opening = ordered ? '[list=1]' : '[list]';
        const replacement = `${opening}\n${items.map((item) => `[*]${item}`).join('\n')}\n[/list]`;
        textarea.setRangeText(replacement, start, end, 'select');
        textarea.focus();
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const initEditor = (editor) => {
        if (!editor || editor.dataset.forumEditorInitialized === '1') return;
        const textarea = editor.querySelector('.forum-editor__textarea, [data-bbcode-input]');
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
                    const url = safeUrl(window.prompt('URL de la imagen o GIF (http/https):', 'https://'));
                    if (url) insertText(textarea, `[img]${url}[/img]`);
                    return;
                }
                if (action === 'link') {
                    const url = safeUrl(window.prompt('URL del enlace (http/https):', 'https://'));
                    if (url) wrapSelection(textarea, 'url', url, 'texto del enlace');
                    return;
                }
                if (action === 'spoiler') {
                    const title = (window.prompt('Título del spoiler:', 'Mostrar spoiler') || 'Mostrar spoiler')
                        .replace(/[\[\]]/g, '').slice(0, 80);
                    wrapSelection(textarea, 'spoiler', title, 'contenido oculto');
                    return;
                }
                if (action === 'quote') {
                    const author = (window.prompt('Autor de la cita (opcional):', '') || '')
                        .replace(/[\[\]]/g, '').slice(0, 80);
                    wrapSelection(textarea, 'quote', author || null, 'texto citado');
                    return;
                }
                if (action === 'list') {
                    insertList(textarea, false);
                    return;
                }
                if (action === 'olist') {
                    insertList(textarea, true);
                    return;
                }
                if (action === 'hr') insertText(textarea, '\n[hr]\n');
            });
        });

        // Preview uses exactly the same safe server renderer as published messages.
        // Does not submit the form; does not modify the textarea or create a draft.
        const previewButton = editor.querySelector('[data-bbcode-preview-toggle]');
        const previewPanel = editor.querySelector('[data-bbcode-preview-panel]');
        if (previewButton && previewPanel) {
            let activeRequest = null;
            const content = previewPanel.querySelector('[data-bbcode-preview-content]');
            const status = previewPanel.querySelector('[data-bbcode-preview-status]');
            previewButton.addEventListener('click', async () => {
                if (!previewPanel.hidden) {
                    activeRequest?.abort();
                    previewPanel.hidden = true;
                    previewButton.setAttribute('aria-expanded', 'false');
                    previewButton.textContent = 'Vista previa';
                    return;
                }
                previewPanel.hidden = false;
                previewButton.setAttribute('aria-expanded', 'true');
                previewButton.textContent = 'Ocultar vista previa';
                content.replaceChildren();
                status.textContent = 'Generando vista previa…';
                const token = editor.closest('form')?.querySelector('input[name="_token"]')?.value;
                if (!token) {
                    status.textContent = 'No se ha encontrado el token del formulario. Recarga la página.';
                    return;
                }
                activeRequest?.abort();
                activeRequest = new AbortController();
                try {
                    const response = await fetch(previewButton.dataset.previewUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ body: textarea.value }),
                        signal: activeRequest.signal,
                    });
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    const result = await response.json();
                    // Returned HTML is rendered by the same escaped/allowlisted PHP BBCode engine.
                    content.innerHTML = result.html || '';
                    status.textContent = textarea.value.trim() ? 'Vista previa generada. No se ha publicado.' : 'El mensaje está vacío.';
                } catch (error) {
                    if (error.name !== 'AbortError') status.textContent = 'No se pudo generar la vista previa. Inténtalo de nuevo.';
                }
            });
            textarea.addEventListener('input', () => {
                if (!previewPanel.hidden) status.textContent = 'Has cambiado el texto. Pulsa Ocultar y después Vista previa para actualizarlo.';
            });
        }

        textarea.addEventListener('keydown', (event) => {
            if (!(event.ctrlKey || event.metaKey)) return;
            const tag = { b: 'b', i: 'i', u: 'u' }[event.key.toLowerCase()];
            if (!tag) return;
            event.preventDefault();
            wrapSelection(textarea, tag);
        });
    };

    const initAll = (scope = document) => scope.querySelectorAll('[data-forum-editor]').forEach(initEditor);

    window.NewSlotBbcodeEditor = { init: initEditor, initAll, wrapSelection, insertText };
    window.NewSlotForumEditor = Object.assign(window.NewSlotForumEditor || {}, { init: initEditor, initAll });

    const boot = () => initAll(document);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();

    document.addEventListener('livewire:navigated', boot);

    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (!(node instanceof Element)) continue;
                if (node.matches?.('[data-forum-editor]')) initEditor(node);
                initAll(node);
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
