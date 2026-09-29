(() => {
    'use strict';

    const DEFAULT_AVATAR = '/images/sqa-shield-white.png';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    const parseJson = (value, fallback = []) => {
        try {
            const parsed = JSON.parse(value || '');
            return parsed ?? fallback;
        } catch (_) {
            return fallback;
        }
    };

    const allUsers = () => {
        const source = document.getElementById('diary-all-users-data');
        if (!source) return [];
        const parsed = parseJson(source.textContent, []);
        return Array.isArray(parsed) ? parsed : [];
    };

    const normalizeMember = (member = {}) => ({
        user_id: member.user_id ? Number(member.user_id) : null,
        nick: member.nick || '',
        slot_name: member.slot_name || '',
        number: member.number ?? '',
        color: member.color || '',
        avatar: member.avatar || DEFAULT_AVATAR,
        profile_color: member.profile_color || '#fff',
        slot_group: member.slot_group || '',
    });

    const initRosterBuilder = (builder) => {
        const list = builder.querySelector('[data-diary-roster-list]');
        const empty = builder.querySelector('[data-diary-roster-empty]');
        const loading = builder.querySelector('[data-diary-roster-loading]');
        const groupBadge = builder.querySelector('[data-diary-roster-group]');
        const hidden = builder.querySelector('[data-diary-roster-json]');
        const template = builder.querySelector('[data-diary-roster-template]');
        const addExistingSelect = builder.querySelector('[data-roster-add-existing]');
        const addExistingButton = builder.querySelector('[data-roster-add-existing-button]');
        const addUserSelect = builder.querySelector('[data-roster-add-user]');
        const addUserButton = builder.querySelector('[data-roster-add-user-button]');
        const eventSelect = builder.dataset.eventSelect
            ? document.getElementById(builder.dataset.eventSelect)
            : null;
        const squadGroupInput = builder.dataset.squadGroupInput
            ? document.getElementById(builder.dataset.squadGroupInput)
            : null;

        if (!list || !hidden || !template) return;

        const initial = parseJson(builder.dataset.initialRoster, [])
            .map((row, index) => ({ ...row, _initialIndex: index }));
        const initialEventId = String(builder.dataset.initialEventId || '');
        const users = allUsers();

        let members = [];
        let availableMembers = [];
        let dragged = null;
        let lastAutomaticGroup = '';

        const serialize = () => {
            hidden.value = JSON.stringify(members.map((member) => ({
                user_id: member.user_id ? Number(member.user_id) : null,
                nick: member.nick || '',
                slot_name: member.slot_name || '',
                number: member.number === '' || member.number == null ? null : Number(member.number),
                color: member.color || null,
            })));
        };

        const syncSquadGroup = (eventId, detectedGroup = '') => {
            if (!squadGroupInput) return;

            if (eventId) {
                squadGroupInput.value = detectedGroup || '';
                squadGroupInput.readOnly = true;
                squadGroupInput.setAttribute('aria-readonly', 'true');
                lastAutomaticGroup = detectedGroup || '';
                return;
            }

            const wasAutomatic = squadGroupInput.readOnly || squadGroupInput.value === lastAutomaticGroup;
            squadGroupInput.readOnly = false;
            squadGroupInput.removeAttribute('aria-readonly');

            if (wasAutomatic && lastAutomaticGroup !== '') {
                squadGroupInput.value = '';
            }

            lastAutomaticGroup = '';
        };

        const usedUserIds = () => new Set(
            members
                .map((member) => Number(member.user_id))
                .filter((id) => Number.isInteger(id) && id > 0)
        );

        const fillSelect = (select, source, placeholder, optionLabel) => {
            if (!select) return;

            const used = usedUserIds();
            const current = select.value;
            const options = source.filter((member) => !used.has(Number(member.user_id)));

            select.innerHTML = '';
            const first = document.createElement('option');
            first.value = '';
            first.textContent = options.length ? placeholder : 'No hay más usuarios disponibles';
            select.appendChild(first);

            options.forEach((member) => {
                const option = document.createElement('option');
                option.value = String(member.user_id);
                option.textContent = optionLabel(member);
                select.appendChild(option);
            });

            if (options.some((member) => String(member.user_id) === current)) {
                select.value = current;
            }
        };

        const refreshSelectors = () => {
            fillSelect(
                addExistingSelect,
                availableMembers,
                'Selecciona un participante…',
                (member) => {
                    const details = [member.slot_group, member.slot_name].filter(Boolean).join(' · ');
                    return details ? `${member.nick} · ${details}` : member.nick;
                },
            );

            fillSelect(
                addUserSelect,
                users,
                'Selecciona un usuario…',
                (member) => member.nick,
            );
        };

        const syncEmptyState = () => {
            empty.hidden = members.length > 0;
            if (members.length === 0) {
                empty.textContent = 'Selecciona un evento para cargar automáticamente tu escuadra, o añade usuarios desde la lista.';
            }
        };

        const render = () => {
            list.innerHTML = '';
            syncEmptyState();

            members.forEach((member, index) => {
                const row = template.content.firstElementChild.cloneNode(true);
                row.dataset.userId = member.user_id || '';

                const avatar = row.querySelector('[data-roster-avatar]');
                const nick = row.querySelector('[data-roster-nick]');
                const slotName = row.querySelector('[data-roster-slot-name]');
                const number = row.querySelector('[data-roster-number]');
                const color = row.querySelector('[data-roster-color]');

                avatar.src = member.avatar || DEFAULT_AVATAR;
                avatar.alt = member.nick || 'Usuario';
                nick.textContent = member.nick || 'Usuario';
                nick.style.color = member.profile_color || '#fff';
                slotName.value = member.slot_name || '';
                number.value = member.number ?? '';
                color.value = member.color ?? '';

                slotName.addEventListener('input', () => {
                    members[index].slot_name = slotName.value;
                    serialize();
                });
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
                row.querySelector('[data-roster-remove]').addEventListener('click', () => {
                    members.splice(index, 1);
                    render();
                });

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

            refreshSelectors();
            serialize();
        };

        const reorder = (from, to) => {
            if (from === to || from < 0 || to < 0 || from >= members.length || to >= members.length) return;
            const [item] = members.splice(from, 1);
            members.splice(to, 0, item);
            render();
        };

        const addFromSource = (source, userId) => {
            const member = source.find((item) => Number(item.user_id) === Number(userId));
            if (!member) return;
            if (members.some((item) => Number(item.user_id) === Number(member.user_id))) return;

            members.push(normalizeMember(member));
            render();
        };

        const mergeSavedRoster = (savedRows, detectedMembers) => {
            if (!savedRows.length) {
                return detectedMembers.map((member) => normalizeMember(member));
            }

            const knownUsers = new Map(users.map((member) => [String(member.user_id), member]));
            const eventById = new Map(availableMembers.map((member) => [String(member.user_id), member]));
            const ordered = [];

            savedRows
                .slice()
                .sort((a, b) => (a._initialIndex ?? 0) - (b._initialIndex ?? 0))
                .forEach((saved) => {
                    const userId = Number(saved.user_id || 0);
                    if (!userId) return;

                    const source = eventById.get(String(userId)) || knownUsers.get(String(userId));
                    if (!source) return;

                    ordered.push(normalizeMember({
                        ...source,
                        ...saved,
                        user_id: userId,
                        nick: source.nick,
                    }));
                });

            return ordered;
        };

        const loadEvent = async (eventId) => {
            if (!eventId) {
                availableMembers = [];
                if (initialEventId === '' && initial.length > 0) {
                    members = mergeSavedRoster(initial, []);
                } else if (initialEventId !== '') {
                    members = [];
                }
                groupBadge.textContent = 'Sin evento programado';
                syncSquadGroup('', '');
                loading.hidden = true;
                render();
                return;
            }

            loading.hidden = false;
            empty.hidden = true;
            groupBadge.textContent = 'Buscando escuadra…';

            try {
                const url = builder.dataset.squadUrlTemplate.replace('__EVENT__', encodeURIComponent(eventId));
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) throw new Error('No se pudo cargar la escuadra');

                const data = await response.json();
                const detectedMembers = Array.isArray(data.members) ? data.members : [];
                availableMembers = Array.isArray(data.available_members) ? data.available_members : detectedMembers;

                const savedRows = String(eventId) === initialEventId ? initial : [];
                members = mergeSavedRoster(savedRows, detectedMembers);

                groupBadge.textContent = data.group
                    ? `Escuadra detectada · ${data.group}`
                    : 'Sin escuadra detectada';
                syncSquadGroup(eventId, data.group || '');

                if (detectedMembers.length === 0 && members.length === 0) {
                    empty.hidden = false;
                    empty.textContent = 'No se detectó tu escuadra. Puedes añadir usuarios desde la lista para esta entrada.';
                }

                render();
            } catch (error) {
                console.error(error);
                availableMembers = [];
                if (String(eventId) === initialEventId && initial.length > 0) {
                    members = mergeSavedRoster(initial, []);
                }
                groupBadge.textContent = 'Escuadra no disponible';
                syncSquadGroup(eventId, squadGroupInput?.value || '');
                empty.hidden = members.length > 0;
                if (members.length === 0) {
                    empty.textContent = 'No se pudo cargar la escuadra. Puedes añadir usuarios desde la lista.';
                }
                render();
            } finally {
                loading.hidden = true;
            }
        };

        addExistingButton?.addEventListener('click', () => {
            if (!addExistingSelect?.value) return;
            addFromSource(availableMembers, addExistingSelect.value);
        });

        addUserButton?.addEventListener('click', () => {
            if (!addUserSelect?.value) return;
            addFromSource(users, addUserSelect.value);
        });

        if (eventSelect) {
            eventSelect.addEventListener('change', () => loadEvent(eventSelect.value));
            loadEvent(eventSelect.value);
        } else if (builder.dataset.eventId) {
            loadEvent(builder.dataset.eventId);
        } else {
            members = mergeSavedRoster(initial, []);
            groupBadge.textContent = 'Sin evento programado';
            syncSquadGroup('', '');
            render();
        }
    };

    const initDiaryTitleBinding = () => {
        const eventSelect = document.querySelector('[data-diary-event-select]');
        const titleInput = document.querySelector('[data-diary-entry-title]');

        if (!eventSelect || !titleInput) return;

        let lastAutomaticTitle = '';

        const syncTitle = () => {
            const option = eventSelect.options[eventSelect.selectedIndex];
            const eventTitle = option?.dataset?.entryTitle || '';

            if (eventSelect.value && eventTitle) {
                titleInput.value = eventTitle;
                titleInput.readOnly = true;
                titleInput.setAttribute('aria-readonly', 'true');
                lastAutomaticTitle = eventTitle;
                return;
            }

            const wasAutomatic = titleInput.readOnly || titleInput.value === lastAutomaticTitle;
            titleInput.readOnly = false;
            titleInput.removeAttribute('aria-readonly');

            if (wasAutomatic && lastAutomaticTitle !== '') {
                titleInput.value = '';
            }

            lastAutomaticTitle = '';
        };

        eventSelect.addEventListener('change', syncTitle);
        syncTitle();
    };

    ready(() => {
        initDiaryTitleBinding();
        document.querySelectorAll('[data-diary-roster-builder]').forEach(initRosterBuilder);
    });
})();
