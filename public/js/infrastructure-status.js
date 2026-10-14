(() => {
    const panels = Array.from(document.querySelectorAll('[data-infrastructure-status]'));

    if (!panels.length) {
        return;
    }

    const endpoint = panels[0].dataset.endpoint;

    if (!endpoint) {
        return;
    }

    const setDot = (row, online) => {
        const dot = row.querySelector('[data-status-dot]');
        if (!dot) return;
        dot.classList.remove('is-loading', 'is-online', 'is-offline');
        dot.classList.add(online ? 'is-online' : 'is-offline');
    };

    const applySnapshot = (panel, snapshot) => {
        const services = Array.isArray(snapshot.services) ? snapshot.services : [];

        const servicesContainer = panel.querySelector('.footer-infrastructure__services');

        if (servicesContainer) {
            servicesContainer.replaceChildren();

            services.forEach((service) => {
                const row = document.createElement('div');
                row.className = 'footer-infrastructure__row';
                row.dataset.service = service.key;

                const dot = document.createElement('span');
                dot.className = 'footer-status-dot is-loading';
                dot.dataset.statusDot = '';
                dot.setAttribute('aria-hidden', 'true');

                const name = document.createElement('span');
                name.className = 'footer-infrastructure__service-name';
                name.textContent = service.label || 'Servidor';

                const detail = document.createElement('small');
                detail.dataset.statusDetail = '';
                detail.textContent = 'Comprobando…';

                row.append(dot, name, detail);
                if (service.has_password) {
                    row.classList.add('footer-infrastructure__row--with-key');
                    row.style.position = 'relative';
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'footer-infrastructure__key';
                    button.title = 'Ver contraseña del servidor'; button.setAttribute('aria-label', `Ver contraseña de ${service.label}`);
                    button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M11 12 21 2M16 7l3 3M13 10l3 3"/></svg>';
                    const pop = document.createElement('div'); pop.className = 'footer-infrastructure__credential'; pop.hidden = true;
                    const header = document.createElement('div'); header.className = 'footer-infrastructure__credential-header';
                    const title = document.createElement('strong'); title.textContent = 'Contraseña de acceso';
                    const close = document.createElement('button'); close.type = 'button'; close.textContent = '×'; close.setAttribute('aria-label','Cerrar contraseña');
                    header.append(title, close);
                    const code = document.createElement('span'); code.className = 'footer-infrastructure__credential-code';
                    const copy = document.createElement('button'); copy.type = 'button'; copy.textContent = 'Copiar';
                    pop.append(header, code, copy); row.append(button, pop);
                    let loadedPassword = null;
                    const hide = () => { pop.hidden = true; code.textContent = ''; loadedPassword = null; };
                    close.addEventListener('click', hide);
                    button.addEventListener('click', async (event) => {
                        event.stopPropagation();
                        if (!pop.hidden) { hide(); return; }
                        panel.querySelectorAll('.footer-infrastructure__credential').forEach((el) => { el.hidden = true; });
                        pop.hidden = false; code.textContent = 'Cargando…'; copy.disabled = true;
                        try {
                            const response = await fetch(`${panel.dataset.credentialBase}/${encodeURIComponent(service.key.replace('arma_server_', ''))}`, {
                                credentials:'same-origin', headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}
                            });
                            if (!response.ok) throw new Error('No disponible');
                            loadedPassword = (await response.json()).password;
                            code.textContent = loadedPassword || 'Sin contraseña'; copy.disabled = !loadedPassword;
                        } catch { code.textContent = 'No se pudo consultar la contraseña'; }
                    });
                    copy.addEventListener('click', async () => {
                        if (!loadedPassword) return;
                        try { await navigator.clipboard.writeText(loadedPassword); copy.textContent = 'Copiado'; }
                        catch { copy.textContent = 'No se pudo copiar'; }
                    });
                }
                servicesContainer.appendChild(row);

                setDot(row, Boolean(service.online));

                if (!service.configured) {
                    detail.textContent = 'Sin configurar';
                } else if (!service.online) {
                    detail.textContent = 'Sin respuesta';
                } else if (Number.isInteger(service.players) && Number.isInteger(service.max_players)) {
                    detail.textContent = `${service.players}/${service.max_players} conectados`;
                } else {
                    detail.textContent = 'En línea';
                }
            });

            if (!services.length) {
                const empty = document.createElement('div');
                empty.className = 'footer-infrastructure__row';

                const name = document.createElement('span');
                name.className = 'footer-infrastructure__service-name';
                name.textContent = 'No hay servidores ArmA configurados';

                empty.appendChild(name);
                servicesContainer.appendChild(empty);
            }
        }

        const ts3 = snapshot.teamspeak || {};
        const ts3Block = panel.querySelector('[data-ts3-block]');

        if (ts3Block) {
            if (!ts3.enabled) {
                ts3Block.hidden = true;
                return;
            }

            ts3Block.hidden = false;

            const dotRow = ts3Block.querySelector('[data-ts3-row]');
            if (dotRow) {
                setDot(dotRow, Boolean(ts3.available && ts3.online));
            }

            const detail = ts3Block.querySelector('[data-ts3-detail]');
            if (!detail) return;

            const channels = Array.isArray(ts3.channels) ? ts3.channels : [];
            const list = ts3Block.querySelector('[data-ts3-users]');
            const floating = ts3Block.querySelector('[data-ts3-floating]');
            const oldToggle = ts3Block.querySelector('[data-ts3-toggle]');
            if (oldToggle) oldToggle.remove();
            if (floating) floating.hidden = true;
            if (list) {
                list.replaceChildren();
                if (ts3.online && channels.length > 0) {
                    const toggle = document.createElement('button');
                    toggle.type = 'button'; toggle.className = 'footer-infrastructure__ts-toggle';
                    toggle.dataset.ts3Toggle = ''; toggle.setAttribute('aria-expanded', 'false');
                    toggle.textContent = 'Ver usuarios por canal ▸';
                    dotRow.after(toggle);
                    const hide = () => { floating.hidden = true; toggle.setAttribute('aria-expanded','false'); toggle.textContent = 'Ver usuarios por canal ▸'; };
                    toggle.addEventListener('click', () => {
                        const show = floating.hidden;
                        floating.hidden = !show;
                        toggle.setAttribute('aria-expanded',String(show));
                        toggle.textContent = show ? 'Ocultar usuarios ◂' : 'Ver usuarios por canal ▸';
                    });
                    floating.querySelector('[data-ts3-close]')?.addEventListener('click', hide);
                    panel.addEventListener('toggle', () => { if (!panel.open) hide(); });
                    channels.forEach((channel) => {
                        if (!Array.isArray(channel.users) || !channel.users.length) return;
                        const item = document.createElement('li'); item.className = 'footer-infrastructure__channel';
                        const title = document.createElement('strong'); title.textContent = `${channel.name} (${channel.users.length})`;
                        const users = document.createElement('ul');
                        channel.users.forEach((nick) => { const user = document.createElement('li'); user.textContent = nick; users.appendChild(user); });
                        item.append(title, users); list.appendChild(item);
                    });
                }
            }

            if (!ts3.configured) {
                detail.textContent = 'TSViewer sin configurar';
            } else if (!ts3.available) {
                detail.textContent = 'TSViewer no disponible';
            } else if (!ts3.online) {
                detail.textContent = 'Fuera de línea';
            } else if (Number.isInteger(ts3.players) && Number.isInteger(ts3.max_players)) {
                detail.textContent = `${ts3.players}/${ts3.max_players} conectados`;
            } else if (Number.isInteger(ts3.players)) {
                detail.textContent = `${ts3.players} conectado${ts3.players === 1 ? '' : 's'}`;
            } else {
                detail.textContent = 'En línea';
            }
        }
    };

    const markFailure = (panel) => {
        panel.querySelectorAll('[data-status-dot]').forEach((dot) => {
            dot.classList.remove('is-loading', 'is-online');
            dot.classList.add('is-offline');
        });

        panel.querySelectorAll('[data-status-detail], [data-ts3-detail]').forEach((detail) => {
            detail.textContent = 'No disponible';
        });
    };

    fetch(endpoint, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    })
        .then((response) => {
            if (!response.ok) throw new Error('Infrastructure status request failed');
            return response.json();
        })
        .then((snapshot) => panels.forEach((panel) => applySnapshot(panel, snapshot)))
        .catch(() => panels.forEach(markFailure));
})();
