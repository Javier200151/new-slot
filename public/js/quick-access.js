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
                bar.querySelectorAll('details.sqa-quick-access__server[open]').forEach((detail) => { detail.open = false; });
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
