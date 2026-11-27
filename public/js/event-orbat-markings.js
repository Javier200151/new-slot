document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-orbat]');
    if (!root) return;
    const cards = [...root.querySelectorAll('[data-orbat-flip-card]')]
        .filter(card => card.querySelector('[data-orbat-flip]'));
    const allButton = root.querySelector('[data-orbat-flip-all]');
    if (!cards.length) return;

    const colors = {white: 'Blanco', green: 'Verde', yellow: 'Amarillo', red: 'Rojo', blue: 'Azul'};
    const colorOrder = ['red', 'blue', 'yellow', 'green', 'white'];
    const sanitizeBbcode = text => String(text ?? '').replace(/[\[\]\r\n]/g, '').trim();
    function savedTeamTitles(card) {
        try {
            const parsed = JSON.parse(backOf(card)?.dataset.markingTeamTitles || '{}');
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
        } catch (_) { return {}; }
    }

    function activeTeams(card, draft = editing(card)) {
        const teams = {};
        rowsOf(card).forEach(row => {
            if (row.dataset.markingOccupied !== '1') return;
            const color = draft ? colorOf(row) : row.dataset.markingColor;
            if (!colors[color]) return;
            teams[color] = (teams[color] || 0) + 1;
        });
        return teams;
    }

    function refreshTeamFields(card) {
        const active = activeTeams(card, true);
        card.querySelectorAll('[data-marking-team-field]').forEach(field => {
            const color = field.dataset.markingTeamField;
            field.hidden = !editing(card) || !active[color];
            const count = field.querySelector('[data-marking-team-count]');
            if (count) count.textContent = '(' + (active[color] || 0) + ')';
        });
    }

    function renderTeamPreview(card) {
        const target = card.querySelector('[data-marking-team-preview]');
        if (!target) return;
        target.replaceChildren();
        const titles = savedTeamTitles(card);
        const active = activeTeams(card, false);
        const teamEntries = colorOrder.filter(color => active[color]);
        target.hidden = teamEntries.length === 0;
        for (const color of teamEntries) {
            const label = document.createElement('span');
            label.className = 'orbat-fireteam-chip orbat-fireteam-chip--' + color;
            label.textContent = colors[color] + ' (' + active[color] + ')'
                + (titles[color] ? ': ' + titles[color] : '');
            target.append(label);
        }
    }


    // Uses the existing safe [color] tags supported by BbcodeMarkup.
    // Read-only numbers/colors are the source of truth, not unsaved editor values.
    function shareBbcode(card) {
        const back = backOf(card);
        const group = sanitizeBbcode(card.dataset.markingGroup).toUpperCase();
        const rows = rowsOf(card)
            .filter(row => row.dataset.markingNumber !== '' && row.dataset.markingNumber != null)
            .sort((a, b) => colorOrder.indexOf(a.dataset.markingColor) - colorOrder.indexOf(b.dataset.markingColor)
                || Number(a.dataset.markingNumber) - Number(b.dataset.markingNumber));
        const colorFor = color => colors[color] ? color : 'white';
        const headerColor = colorFor(rows.find(row => row.dataset.markingColor !== 'white')?.dataset.markingColor || 'white');
        const header = group;
        const lines = rows.map(row => {
            const number = row.dataset.markingNumber;
            const name = sanitizeBbcode(row.querySelector('.orbat-marking-player > span')?.textContent || '');
            const playerName = row.dataset.markingOccupied === '1' ? name : '—';
            const second = back?.dataset.markingSecond === row.dataset.slotKey ? ' (2.º al mando)' : '';
            return '[color=' + colorFor(row.dataset.markingColor) + ']' +
                number + '.- ' + playerName + second + '[/color]';
        });
        const teams = activeTeams(card, false);
        const titles = savedTeamTitles(card);
        const teamNames = colorOrder.filter(color => teams[color] && titles[color])
            .map(color => '[b][color=' + color + ']Equipo ' + colors[color]
                + ': ' + sanitizeBbcode(titles[color]) + '[/color][/b]');
        return '[b][color=' + headerColor + ']' + header + '[/color][/b]' +
            (teamNames.length ? '\n' + teamNames.join('\n') : '') +
            (lines.length ? '\n\n' + lines.join('\n') : '');
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

    // Small-screen roster: group consecutive rows by their saved fireteam
    // color. These headings are purely presentational and never affect
    // numeration, payloads, BBCode, PDF, or the desktop layout.
    function renderMobileTeamHeadings(card) {
        const container = card.querySelector('[data-marking-rows]');
        if (!container) return;
        container.querySelectorAll('[data-marking-mobile-team]').forEach(heading => heading.remove());

        const totals = activeTeams(card, false);
        const titles = savedTeamTitles(card);
        let previousColor = null;

        rowsOf(card).forEach(row => {
            const color = colors[row.dataset.markingColor] ? row.dataset.markingColor : 'white';
            if (color === previousColor) return;

            const heading = document.createElement('div');
            heading.className = 'orbat-marking-mobile-team orbat-marking-mobile-team--' + color;
            heading.dataset.markingMobileTeam = '';
            heading.textContent = colors[color]
                + (totals[color] ? ' (' + totals[color] + ')' : '')
                + (titles[color] ? ': ' + titles[color] : '');
            container.insertBefore(heading, row);
            previousColor = color;
        });
    }

    function sortRows(card) {
        const container = card.querySelector('[data-marking-rows]');
        if (!container) return;
        rowsOf(card).sort((a, b) => {
            const color = row => editing(card) ? colorOf(row) : row.dataset.markingColor;
            const toNumber = row => {
                const n = editing(card) ? numberOf(row) : row.dataset.markingNumber;
                return n === '' || n == null ? Number.MAX_SAFE_INTEGER : Number(n);
            };
            return colorOrder.indexOf(color(a)) - colorOrder.indexOf(color(b))
                || toNumber(a) - toNumber(b)
                || String(a.dataset.slotKey).localeCompare(String(b.dataset.slotKey));
        }).forEach(row => container.append(row));
        renderMobileTeamHeadings(card);
    }

    function setEditor(card, enabled) {
        if (card.dataset.markingsEditable !== '1') return;
        card.classList.toggle('is-marking-editing', enabled);
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
        refreshTeamFields(card);
        renderTeamPreview(card);
        sortRows(card);
    }

    function restoreEditor(card) {
        rowsOf(card).forEach(row => {
            const number = row.querySelector('[data-marking-input-number]');
            const color = row.querySelector('[data-marking-input-color]');
            if (number) number.value = row.dataset.markingNumber ?? '';
            if (color) color.value = row.dataset.markingColor ?? 'white';
        });
        const titles = savedTeamTitles(card);
        card.querySelectorAll('[data-marking-team-input]').forEach(input => {
            input.value = titles[input.dataset.markingTeamInput] || '';
        });
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
            renderTeamPreview(card);
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
        card.querySelectorAll('[data-marking-input-color]').forEach(select => {
            select.addEventListener('change', () => refreshTeamFields(card));
        });
        renderTeamPreview(card);
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
            const teamTitles = {};
            const active = activeTeams(card, true);
            card.querySelectorAll('[data-marking-team-input]').forEach(input => {
                if (active[input.dataset.markingTeamInput]) {
                    const value = input.value.trim();
                    if (value) teamTitles[input.dataset.markingTeamInput] = value;
                }
            });
            const selectedSecond = card.querySelector('[data-marking-second-input]:checked')?.value ?? null;
            const second = card.querySelector('[data-marking-second-none]:checked') ? null : selectedSecond;

            if (items.some(item => item.number !== null
                    && (!Number.isInteger(item.number) || item.number < 1 || item.number > 999))) {
                status.textContent = 'Introduce números válidos del 1 al 999.';
                return;
            }
            const assignedNumbers = items.filter(item => item.number !== null)
                .map(item => item.color + ':' + item.number);
            if (new Set(assignedNumbers).size !== assignedNumbers.length) {
                status.textContent = 'No se puede repetir un número dentro del mismo color.';
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
                        color_team_titles: teamTitles,
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
                backOf(card).dataset.markingTeamTitles = JSON.stringify(teamTitles);
                renderTeamPreview(card);
                renderMobileTeamHeadings(card);
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
