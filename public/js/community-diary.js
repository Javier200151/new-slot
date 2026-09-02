(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    const parseInitial = (builder) => {
        try {
            const parsed = JSON.parse(builder.dataset.initialRoster || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (_) {
            return [];
        }
    };

    const initRosterBuilder = (builder) => {
        const list = builder.querySelector('[data-diary-roster-list]');
        const empty = builder.querySelector('[data-diary-roster-empty]');
        const loading = builder.querySelector('[data-diary-roster-loading]');
        const group = builder.querySelector('[data-diary-roster-group]');
        const hidden = builder.querySelector('[data-diary-roster-json]');
        const template = builder.querySelector('[data-diary-roster-template]');
        const select = builder.dataset.eventSelect
            ? document.getElementById(builder.dataset.eventSelect)
            : null;

        if (!list || !hidden || !template) return;

        let members = [];
        let dragged = null;
        const initial = parseInitial(builder);
        const initialEventId = String(builder.dataset.initialEventId || '');
        const initialByUser = new Map(initial.map((row, index) => [String(row.user_id), { ...row, index }]));

        const serialize = () => {
            hidden.value = JSON.stringify(members.map((member) => ({
                user_id: Number(member.user_id),
                number: member.number === '' || member.number == null ? null : Number(member.number),
                color: member.color || null,
            })));
        };

        const reorder = (from, to) => {
            if (from === to || from < 0 || to < 0 || from >= members.length || to >= members.length) return;
            const [item] = members.splice(from, 1);
            members.splice(to, 0, item);
            render();
        };

        const render = () => {
            list.innerHTML = '';
            empty.hidden = members.length > 0;

            members.forEach((member, index) => {
                const row = template.content.firstElementChild.cloneNode(true);
                row.dataset.userId = member.user_id;
                row.querySelector('[data-roster-avatar]').src = member.avatar || '/images/sqa-shield-white.png';
                row.querySelector('[data-roster-avatar]').alt = member.nick || 'Jugador';
                const nick = row.querySelector('[data-roster-nick]');
                nick.textContent = member.nick || 'Jugador';
                nick.style.color = member.profile_color || '#fff';
                row.querySelector('[data-roster-slot]').textContent = [member.slot_name, member.slot_type].filter(Boolean).join(' · ') || 'Slot';

                const number = row.querySelector('[data-roster-number]');
                const color = row.querySelector('[data-roster-color]');
                number.value = member.number ?? '';
                color.value = member.color ?? '';

                number.addEventListener('input', () => {
                    members[index].number = number.value;
                    serialize();
                });
                color.addEventListener('change', () => {
                    members[index].color = color.value;
                    serialize();
                });
                row.querySelector('[data-roster-up]').addEventListener('click', () => reorder(index, index - 1));
                row.querySelector('[data-roster-down]').addEventListener('click', () => reorder(index, index + 1));

                row.addEventListener('dragstart', () => {
                    dragged = index;
                    row.classList.add('is-dragging');
                });
                row.addEventListener('dragend', () => {
                    dragged = null;
                    row.classList.remove('is-dragging');
                });
                row.addEventListener('dragover', (event) => event.preventDefault());
                row.addEventListener('drop', (event) => {
                    event.preventDefault();
                    if (dragged !== null) reorder(dragged, index);
                });

                list.appendChild(row);
            });

            serialize();
        };

        const loadEvent = async (eventId) => {
            members = [];
            list.innerHTML = '';
            serialize();

            if (!eventId) {
                empty.hidden = false;
                empty.textContent = 'Selecciona un evento para cargar automáticamente tu escuadra.';
                group.textContent = 'Selecciona primero un evento';
                return;
            }

            loading.hidden = false;
            empty.hidden = true;
            group.textContent = 'Buscando escuadra…';

            try {
                const url = builder.dataset.squadUrlTemplate.replace('__EVENT__', encodeURIComponent(eventId));
                const response = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) throw new Error('No se pudo cargar la escuadra');
                const data = await response.json();
                const incoming = Array.isArray(data.members) ? data.members : [];

                // Si el formulario ya tenía un snapshot (edición o error de validación),
                // conservamos exactamente su orden, número y color y añadimos al final
                // cualquier jugador detectado que no estuviera en él.
                const incomingById = new Map(incoming.map((member) => [String(member.user_id), member]));
                const ordered = [];

                const savedRows = initialEventId === '' || String(eventId) === initialEventId ? initial : [];

                savedRows
                    .slice()
                    .sort((a, b) => (a.index ?? 0) - (b.index ?? 0))
                    .forEach((saved) => {
                        const source = incomingById.get(String(saved.user_id));
                        if (!source) return;
                        ordered.push({ ...source, number: saved.number ?? '', color: saved.color ?? '' });
                        incomingById.delete(String(saved.user_id));
                    });

                incoming.forEach((member) => {
                    const source = incomingById.get(String(member.user_id));
                    if (!source) return;
                    const saved = savedRows.length ? initialByUser.get(String(member.user_id)) : null;
                    ordered.push({ ...source, number: saved?.number ?? '', color: saved?.color ?? '' });
                    incomingById.delete(String(member.user_id));
                });

                members = ordered;
                group.textContent = data.group ? `Escuadra · ${data.group}` : 'Sin grupo ORBAT detectado';
                empty.textContent = incoming.length
                    ? ''
                    : 'No se ha podido detectar una escuadra para este evento. Puedes guardar la entrada igualmente.';
                render();
            } catch (error) {
                console.error(error);
                members = [];
                serialize();
                group.textContent = 'Escuadra no disponible';
                empty.hidden = false;
                empty.textContent = 'No se pudo cargar la escuadra. Puedes guardar la entrada sin numeración.';
            } finally {
                loading.hidden = true;
            }
        };

        if (select) {
            select.addEventListener('change', () => loadEvent(select.value));
            if (select.value) loadEvent(select.value);
        } else if (builder.dataset.eventId) {
            loadEvent(builder.dataset.eventId);
        }
    };

    ready(() => {
        document.querySelectorAll('[data-diary-roster-builder]').forEach(initRosterBuilder);
    });
})();
