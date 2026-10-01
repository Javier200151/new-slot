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
                return;
            }

            ts3Block.hidden = false;

            const dotRow = ts3Block.querySelector('[data-ts3-row]');
            if (dotRow) {
                setDot(dotRow, Boolean(ts3.available && ts3.online));
            }

            const detail = ts3Block.querySelector('[data-ts3-detail]');
            if (!detail) return;

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
