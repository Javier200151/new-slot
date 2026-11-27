document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-orbat]');
    if (!root) return;
    const cards = [...root.querySelectorAll('[data-orbat-flip-card]')]
        .filter(card => card.querySelector('[data-orbat-flip]'));
    const allButton = root.querySelector('[data-orbat-flip-all]');
    if (!cards.length) return;

    const colors = {white: 'Blanco', green: 'Verde', yellow: 'Amarillo', red: 'Rojo', blue: 'Azul'};
    const sanitizeBbcode = text => String(text ?? '').replace(/[\[\]\r\n]/g, '').trim();

    // Uses the existing safe [color] and [center] tags supported by BbcodeMarkup.
    // Read-only numbers/colors are the source of truth, not unsaved editor values.
    function shareBbcode(card) {
        const back = backOf(card);
        const title = sanitizeBbcode(back?.dataset.markingTitle);
        const group = sanitizeBbcode(card.dataset.markingGroup).toUpperCase();
        const rows = rowsOf(card)
            .filter(row => row.dataset.markingNumber !== '' && row.dataset.markingNumber != null)
            .sort((a, b) => Number(a.dataset.markingNumber) - Number(b.dataset.markingNumber));
        const colorFor = color => colors[color] ? color : 'white';
        const headerColor = colorFor(rows.find(row => row.dataset.markingColor !== 'white')?.dataset.markingColor || 'white');
        const header = group
            + (headerColor === 'white' ? '' : ' - ' + colors[headerColor].toUpperCase())
            + (title ? ' (' + title.toUpperCase() + ')' : '');
        const lines = rows.map(row => {
            const number = row.dataset.markingNumber;
            const name = sanitizeBbcode(row.querySelector('.orbat-marking-player > span')?.textContent || '');
            const playerName = row.dataset.markingOccupied === '1' ? name : '—';
            const second = back?.dataset.markingSecond === row.dataset.slotKey ? ' (2.º al mando)' : '';
            return '[color=' + colorFor(row.dataset.markingColor) + ']' +
                number + '.- ' + playerName + second + '[/color]';
        });
        return '[center][b][color=' + headerColor + ']' + header + '[/color][/b]' +
            (lines.length ? '\n\n' + lines.join('\n') : '') + '[/center]';
    }

    function appendToComment(text) {
        const textarea = document.getElementById('event-comment-new');
        if (!textarea) return false;
        if (textarea.value.length + text.length + 2 > (Number(textarea.maxLength) || 5000)) {
            return false;
        }
        const separator = textarea.value.trim() ? '\n\n' : '';
        textarea.value += separator + text;
        textarea.dispatchEvent(new Event('input', {bubbles: true}));
        document.getElementById('comentarios')?.scrollIntoView({behavior: 'smooth', block: 'start'});
        textarea.focus({preventScroll: true});
        return true;
    }


    const editing = card => card.classList.contains('is-marking-editing');
    const rowsOf = card => [...card.querySelectorAll('[data-marking-row]')];
    const backOf = card => card.querySelector('[data-orbat-marking-back]');
    const numberOf = row => row.querySelector('[data-marking-input-number]')?.value ?? row.dataset.markingNumber ?? '';
    const colorOf = row => row.querySelector('[data-marking-input-color]')?.value ?? row.dataset.markingColor ?? 'white';

    function sortRows(card) {
        const container = card.querySelector('[data-marking-rows]');
        if (!container) return;
        rowsOf(card).sort((a, b) => {
            const toNumber = row => {
                const n = editing(card) ? numberOf(row) : row.dataset.markingNumber;
                return n === '' || n == null ? Number.MAX_SAFE_INTEGER : Number(n);
            };
            return toNumber(a) - toNumber(b)
                || String(a.dataset.slotKey).localeCompare(String(b.dataset.slotKey));
        }).forEach(row => container.append(row));
    }

    function setEditor(card, enabled) {
        if (card.dataset.markingsEditable !== '1') return;
        card.classList.toggle('is-marking-editing', enabled);
        const title = backOf(card)?.querySelector('[data-marking-display-title]');
        if (title) title.hidden = enabled || !backOf(card)?.dataset.markingTitle;
        card.querySelector('[data-marking-edit]')?.setAttribute('aria-pressed', String(enabled));
        const button = card.querySelector('[data-marking-edit]');
        if (button) {
            button.textContent = enabled ? '✎ Editando' : '✎ Editar';
            button.disabled = enabled;
        }
        card.querySelectorAll('[data-marking-editor-only]').forEach(el => { el.hidden = !enabled; });
        rowsOf(card).forEach(row => {
            ['[data-marking-input-number]', '[data-marking-input-color]'].forEach(selector => {
                const field = row.querySelector(selector);
                if (field) field.hidden = !enabled;
            });
            ['[data-marking-display-number]', '[data-marking-display-color]'].forEach(selector => {
                const field = row.querySelector(selector);
                if (field) field.hidden = enabled;
            });
            row.querySelector('[data-marking-second-badge]')?.toggleAttribute(
                'hidden', enabled || card.querySelector('[data-orbat-marking-back]')?.dataset.markingSecond !== row.dataset.slotKey
            );
        });
        sortRows(card);
    }

    function restoreEditor(card) {
        rowsOf(card).forEach(row => {
            const number = row.querySelector('[data-marking-input-number]');
            const color = row.querySelector('[data-marking-input-color]');
            if (number) number.value = row.dataset.markingNumber ?? '';
            if (color) color.value = row.dataset.markingColor ?? 'white';
        });
        const titleInput = card.querySelector('[data-marking-input-title]');
        if (titleInput) titleInput.value = backOf(card)?.dataset.markingTitle ?? '';
        const selected = backOf(card)?.dataset.markingSecond ?? '';
        card.querySelectorAll('[data-marking-second-input]').forEach(el => {
            el.checked = el.value === selected;
        });
        const none = card.querySelector('[data-marking-second-none]');
        if (none) none.checked = selected === '';
        setEditor(card, false);
    }

    function refreshGlobalButton() {
        if (!allButton) return;
        const all = cards.every(card => card.classList.contains('is-marking-face'));
        allButton.textContent = all ? 'Ver ORBAT normal' : 'Ver todas las numeraciones y colores';
        allButton.setAttribute('aria-pressed', String(all));
    }

    function flip(card, show) {
        const back = backOf(card);
        if (!back || card.classList.contains('is-flipping')) return;
        if (!show && editing(card)) restoreEditor(card);
        const finish = () => {
            back.hidden = !show;
            card.classList.toggle('is-marking-face', show);
            if (!show) {
                const frontMeta = card.querySelector('.orbat-marking-front-meta');
                if (frontMeta) frontMeta.hidden = false;
            }
            sortRows(card);
            const trigger = card.querySelector('[data-orbat-flip]');
            if (trigger) {
                trigger.setAttribute('aria-label', show ? 'Volver al ORBAT normal' : 'Ver numeraciones y colores');
                trigger.setAttribute('aria-expanded', String(show));
            }
            refreshGlobalButton();
        };
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            finish();
        } else {
            card.classList.add('is-flipping');
            window.setTimeout(finish, 160);
            window.setTimeout(() => card.classList.remove('is-flipping'), 330);
        }
    }

    cards.forEach(card => {
        const flipButton = card.querySelector('[data-orbat-flip]');
        flipButton?.addEventListener('click', () => {
            flip(card, !card.classList.contains('is-marking-face'));
        });
        card.querySelector('[data-marking-edit]')?.addEventListener('click', () => setEditor(card, true));
        card.querySelector('[data-marking-cancel]')?.addEventListener('click', () => restoreEditor(card));
        const showShareStatus = text => {
            const status = card.querySelector('[data-marking-status]');
            if (status) status.textContent = text;
        };
        card.querySelector('[data-marking-copy]')?.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(shareBbcode(card));
                showShareStatus('BBCode copiado al portapapeles.');
            } catch (_) {
                showShareStatus('No se pudo copiar. Revisa los permisos del navegador.');
            }
        });
        card.querySelector('[data-marking-comment]')?.addEventListener('click', () => {
            showShareStatus(appendToComment(shareBbcode(card))
                ? 'Numeración insertada en el comentario. Puedes añadir tu mensaje antes de publicar.'
                : 'No se pudo insertar el texto: comprueba que estás conectado y que el comentario no supera el límite.');
        });


        const save = card.querySelector('[data-marking-save]');
        const status = card.querySelector('[data-marking-status]');
        save?.addEventListener('click', async () => {
            const items = rowsOf(card).map(row => {
                const raw = numberOf(row);
                return {
                    slot_key: row.dataset.slotKey,
                    number: raw === '' ? null : Number(raw),
                    color: colorOf(row),
                };
            });
            const selectedSecond = card.querySelector('[data-marking-second-input]:checked')?.value ?? null;
            const second = card.querySelector('[data-marking-second-none]:checked') ? null : selectedSecond;

            if (items.some(item => item.number !== null
                    && (!Number.isInteger(item.number) || item.number < 1 || item.number > 999))) {
                status.textContent = 'Introduce números válidos del 1 al 999.';
                return;
            }
            const assignedNumbers = items.filter(item => item.number !== null).map(item => item.number);
            if (new Set(assignedNumbers).size !== assignedNumbers.length) {
                status.textContent = 'No se pueden repetir números en una misma escuadra.';
                return;
            }
            save.disabled = true;
            status.textContent = 'Guardando...';
            try {
                const response = await fetch(card.dataset.markingsEndpoint, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': root.dataset.csrfToken,
                    },
                    body: JSON.stringify({
                        group: card.dataset.markingGroup,
                        items,
                        second_in_command: second,
                        custom_title: card.querySelector('[data-marking-input-title]')?.value.trim() ?? '',
                    }),
                });
                const data = await response.json();
                if (!response.ok) {
                    status.textContent = data.message
                        || Object.values(data.errors || {})[0]?.[0]
                        || 'No se pudieron guardar los cambios.';
                    return;
                }
                items.forEach(item => {
                    const row = rowsOf(card).find(el => el.dataset.slotKey === item.slot_key);
                    if (!row) return;
                    row.dataset.markingNumber = item.number ?? '';
                    row.dataset.markingColor = item.color;
                    const shownNumber = row.querySelector('[data-marking-display-number]');
                    const shownColor = row.querySelector('[data-marking-display-color]');
                    if (shownNumber) shownNumber.textContent = item.number ?? '—';
                    if (shownColor) shownColor.textContent = colors[item.color] || 'Blanco';
                });
                backOf(card).dataset.markingSecond = second || '';
                const titleValue = card.querySelector('[data-marking-input-title]')?.value.trim() ?? '';
                backOf(card).dataset.markingTitle = titleValue;
                const shownTitle = card.querySelector('[data-marking-display-title]');
                if (shownTitle) {
                    shownTitle.textContent = titleValue;
                    shownTitle.hidden = !titleValue;
                }
                if (data.changed && data.updated_by) {
                    const text = 'Última edición: ' + data.updated_by + ' · ' + data.updated_at;
                    const meta = card.querySelector('[data-marking-meta]');
                    if (meta) meta.textContent = text;
                    let front = card.querySelector('.orbat-marking-front-meta');
                    if (!front) {
                        front = document.createElement('div');
                        front.className = 'orbat-marking-front-meta';
                        front.dataset.orbatNormalFace = '';
                        card.querySelector(':scope > header')?.insertAdjacentElement('afterend', front);
                    }
                    front.textContent = 'Última edición táctica: ' + data.updated_by + ' · ' + data.updated_at;
                    if (card.classList.contains('is-marking-face')) front.hidden = true;
                }
                setEditor(card, false);
                status.textContent = data.message || 'Cambios guardados.';
            } catch (_) {
                status.textContent = 'Error de conexión. Vuelve a intentarlo.';
            } finally {
                save.disabled = false;
            }
        });
    });
    allButton?.addEventListener('click', () => {
        const toBack = !cards.every(card => card.classList.contains('is-marking-face'));
        cards.forEach(card => flip(card, toBack));
    });
    if (window.location.hash === '#orbat-numeraciones') {
        cards.forEach(card => flip(card, true));
        document.getElementById('orbat')?.scrollIntoView();
    }
});
