document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-orbat]');
    if (!root) return;
    const cards = [...root.querySelectorAll('[data-orbat-flip-card]')];
    const allButton = root.querySelector('[data-orbat-flip-all]');
    if (!cards.length) return;

    function sortRows(card) {
        const container = card.querySelector('[data-marking-rows]');
        if (!container) return;
        const rows = [...container.querySelectorAll('[data-marking-row]')];
        rows.sort((a, b) => {
            const parse = (el) => {
                const raw = el.querySelector('[data-marking-input-number]')?.value
                    ?? el.dataset.markingNumber;
                return raw === '' || raw == null ? Number.MAX_SAFE_INTEGER : Number(raw);
            };
            return parse(a) - parse(b) || String(a.dataset.slotKey).localeCompare(String(b.dataset.slotKey));
        });
        rows.forEach(row => container.append(row));
    }

    function updateAllButton() {
        if (!allButton) return;
        const allFlipped = cards.every(card => card.classList.contains('is-marking-face'));
        allButton.textContent = allFlipped ? 'Ver ORBAT normal' : 'Ver todas las numeraciones y colores';
        allButton.setAttribute('aria-pressed', String(allFlipped));
    }

    function flip(card, show) {
        const back = card.querySelector('[data-orbat-marking-back]');
        if (!back) return;
        if (show) sortRows(card);
        // Keep the normal ORBAT entirely untouched until the user asks for the reverse face.
        back.hidden = !show;
        card.classList.toggle('is-marking-face', show);
        const trigger = card.querySelector('[data-orbat-flip]');
        if (trigger) {
            trigger.setAttribute('aria-label', show ? 'Volver al ORBAT normal' : 'Ver numeraciones y colores');
            trigger.setAttribute('aria-expanded', String(show));
        }
        updateAllButton();
    }

    cards.forEach(card => {
        const flipButton = card.querySelector('[data-orbat-flip]');
        flipButton?.addEventListener('click', () => flip(card, !card.classList.contains('is-marking-face')));
        card.querySelectorAll('[data-marking-row]').forEach(row => {
            row.querySelector('[data-marking-input-color]')?.addEventListener('change', e => {
                row.dataset.markingColor = e.target.value;
            });
        });

        const save = card.querySelector('[data-marking-save]');
        const status = card.querySelector('[data-marking-status]');
        save?.addEventListener('click', async () => {
            const rows = [...card.querySelectorAll('[data-marking-row]')];
            const items = rows.map(row => {
                const raw = row.querySelector('[data-marking-input-number]')?.value ?? '';
                return {
                    slot_key: row.dataset.slotKey,
                    number: raw === '' ? null : Number(raw),
                    color: row.querySelector('[data-marking-input-color]')?.value ?? 'white',
                };
            });
            if (items.some(item => item.number !== null && (!Number.isInteger(item.number) || item.number < 1 || item.number > 999))) {
                status.textContent = 'Introduce números válidos del 1 al 999.';
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
                    body: JSON.stringify({ group: card.dataset.markingGroup, items }),
                });
                const data = await response.json();
                if (!response.ok) {
                    status.textContent = data.message || Object.values(data.errors || {})[0]?.[0] || 'No se pudieron guardar los cambios.';
                    return;
                }
                rows.forEach(row => {
                    const input = row.querySelector('[data-marking-input-number]');
                    row.dataset.markingNumber = input?.value ?? '';
                });
                sortRows(card);
                status.textContent = data.message || 'Guardado.';
            } catch (_) {
                status.textContent = 'Error de conexión. Vuelve a intentarlo.';
            } finally {
                save.disabled = false;
            }
        });
    });
    allButton?.addEventListener('click', () => {
        const show = !cards.every(card => card.classList.contains('is-marking-face'));
        cards.forEach(card => flip(card, show));
    });
    if (window.location.hash === '#orbat-numeraciones') {
        cards.forEach(card => flip(card, true));
        document.getElementById('orbat')?.scrollIntoView();
    }
});
