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

        services.forEach((service) => {
            const row = panel.querySelector(`[data-service="${service.key}"]`);
            if (!row) return;

            setDot(row, Boolean(service.online));

            const detail = row.querySelector('[data-status-detail]');
            if (detail) {
                if (!service.configured) {
                    detail.textContent = 'Sin configurar';
                } else if (!service.online) {
                    detail.textContent = 'Sin respuesta';
                } else if (Number.isInteger(service.players) && Number.isInteger(service.max_players)) {
                    detail.textContent = `${service.players}/${service.max_players} conectados`;
                } else {
                    detail.textContent = 'En línea';
                }
            }
        });

        const ts3 = snapshot.teamspeak || {};
        const ts3Block = panel.querySelector('[data-ts3-block]');

        if (ts3Block) {
            if (!ts3.enabled) {
                ts3Block.hidden = true;
            } else {
                ts3Block.hidden = false;
                const dotRow = ts3Block.querySelector('[data-ts3-row]');
                if (dotRow) setDot(dotRow, Boolean(ts3.available));

                const detail = ts3Block.querySelector('[data-ts3-detail]');
                if (detail) {
                    detail.textContent = ts3.available
                        ? `${(ts3.users || []).length} conectado${(ts3.users || []).length === 1 ? '' : 's'}`
                        : 'ServerQuery no disponible';
                }

                const list = ts3Block.querySelector('[data-ts3-users]');
                if (list) {
                    list.replaceChildren();
                    (ts3.users || []).forEach((nickname) => {
                        const item = document.createElement('li');
                        item.textContent = nickname;
                        list.appendChild(item);
                    });

                    if (ts3.available && !(ts3.users || []).length) {
                        const item = document.createElement('li');
                        item.className = 'infrastructure-ts3-empty';
                        item.textContent = 'Nadie conectado';
                        list.appendChild(item);
                    }
                }
            }
        }
    };

    const markFailure = (panel) => {
        panel.querySelectorAll('[data-status-dot]').forEach((dot) => {
            dot.classList.remove('is-loading', 'is-online');
            dot.classList.add('is-offline');
        });

        panel.querySelectorAll('[data-status-detail]').forEach((detail) => {
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
