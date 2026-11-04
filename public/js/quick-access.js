/* Squad ALPHA: accesos rápidos. El estado de servidores usa <details>/<summary>
   NATIVOS (igual que el footer); este script NO abre su desplegable. */
(() => {
    'use strict';

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const toggle = event.target.closest('[data-quick-access-toggle]');
        if (toggle) {
            const bar = toggle.closest('[data-quick-access]');
            if (!bar) return;
            const expanded = bar.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(expanded));
            if (!expanded) {
                bar.querySelectorAll('details.sqa-quick-access__news[open]').forEach((detail) => { detail.open = false; });
            }
            return;
        }

        const top = event.target.closest('[data-quick-access-top]');
        if (top) {
            event.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            const bar = top.closest('[data-quick-access]');
            if (bar) {
                bar.classList.remove('is-open');
                bar.querySelector('[data-quick-access-toggle]')?.setAttribute('aria-expanded', 'false');
                bar.querySelectorAll('details.sqa-quick-access__server[open], details.sqa-quick-access__news[open]').forEach((detail) => { detail.open = false; });
            }
        }
    });

    document.addEventListener('toggle', (event) => {
        if (!(event.target instanceof Element) || !event.target.matches('details.sqa-quick-access__server')) return;
        event.target.closest('[data-quick-access]')?.classList.toggle('has-open-server', event.target.open);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-quick-access]').forEach((bar) => {
            bar.querySelectorAll('details.sqa-quick-access__server[open]').forEach((detail) => { detail.open = false; });
            bar.classList.remove('is-open');
            bar.querySelector('[data-quick-access-toggle]')?.setAttribute('aria-expanded', 'false');
        });
    });
})();

/* Contadores en vivo del acceso "Novedades en SquadALPHA". */
(() => {
    'use strict';
    const panels = [...document.querySelectorAll('[data-quick-news-url]')];
    if (!panels.length) return;

    let lastRead = 0;
    let inflight = null;

    const update = (data) => {
        for (const details of panels) {
            const total = details.querySelector('[data-quick-news-total]');
            const forum = details.querySelector('[data-quick-news-forum]');
            const events = details.querySelector('[data-quick-news-events]');
            if (forum) forum.textContent = String(data.forum ?? 0);
            if (events) events.textContent = String(data.events ?? 0);
            if (total) {
                const count = Math.max(0, Number(data.total) || 0);
                total.textContent = count > 99 ? '99+' : String(count);
                total.hidden = count === 0;
            }
        }
    };

    const refresh = (force = false) => {
        if (!force && Date.now() - lastRead < 15000) return;
        if (inflight) return;
        const endpoint = panels[0].dataset.quickNewsUrl;
        inflight = fetch(endpoint, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
            cache: 'no-store'
        })
            .then((response) => {
                if (!response.ok) throw new Error('No se pudieron consultar las novedades');
                return response.json();
            })
            .then((result) => { update(result); lastRead = Date.now(); })
            .catch(() => { /* La navegación sigue funcionando aunque el contador no esté disponible. */ })
            .finally(() => { inflight = null; });
    };

    refresh(true);
    document.addEventListener('toggle', (event) => {
        if (!(event.target instanceof Element) || !event.target.matches('[data-quick-news-url]')) return;
        const details = event.target;
        details.closest('[data-quick-access]')?.classList.toggle('has-open-news', details.open);
        if (details.open) {
            refresh(true);
            details.closest('[data-quick-access]')?.querySelectorAll('details.sqa-quick-access__server[open]').forEach((server) => { server.open = false; });
        }
    }, true);
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        panels.forEach((details) => { details.open = false; });
    });
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        for (const details of panels) {
            if (details.open && !details.contains(event.target)) details.open = false;
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refresh();
    });
})();
