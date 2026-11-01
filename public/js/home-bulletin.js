(() => {
    const section = document.querySelector('[data-sqa-news-section]');
    if (!section) return;

    const newest = Number(section.dataset.sqaLatest || 0);
    if (!Number.isFinite(newest) || newest <= 0) return;

    const key = `sqa-announcements-seen-${section.dataset.sqaAccount || 'guest'}`;
    const maxAge = 7 * 24 * 60 * 60;
    const heroNotices = document.querySelectorAll('[data-sqa-news-hero]');
    const sectionNotices = document.querySelectorAll('[data-sqa-section-new]');
    const cards = section.querySelectorAll('[data-sqa-news-item]');

    let seen = 0;
    try { seen = Number(localStorage.getItem(key) || 0) || 0; }
    catch { /* El navegador puede bloquear el almacenamiento. */ }

    const updateIndicators = () => {
        const baseline = seen > 0 ? seen : Math.floor(Date.now() / 1000) - maxAge;
        const hasNew = newest > baseline;

        heroNotices.forEach(el => { el.hidden = !hasNew; });
        sectionNotices.forEach(el => { el.hidden = !hasNew; });
        cards.forEach(card => {
            const badge = card.querySelector('[data-sqa-news-new]');
            if (badge) badge.hidden = Number(card.dataset.published || 0) <= baseline;
        });
    };

    let observer = null;
    let active = true;
    const markSeen = () => {
        if (!active) return;
        active = false;
        seen = Math.max(seen, newest);
        try { localStorage.setItem(key, String(seen)); }
        catch { /* El aviso desaparece igualmente durante esta visita. */ }
        updateIndicators();
        observer?.disconnect();
        window.removeEventListener('scroll', checkVisibility);
        window.removeEventListener('resize', checkVisibility);
        window.removeEventListener('hashchange', onHashChange);
    };

    // Al pulsar el aviso, la lectura queda registrada en este mismo instante.
    heroNotices.forEach(el => el.addEventListener('click', markSeen));

    const targets = [
        section.querySelector('.home-section-heading'),
        section.querySelector('[data-sqa-news-item]'),
    ].filter(Boolean);

    // La sección completa puede medir muchas pantallas: observar su cabecera
    // y el primer anuncio evita que el umbral sea inalcanzable en móvil.
    const inView = target => {
        const rect = target.getBoundingClientRect();
        const viewport = window.innerHeight || document.documentElement.clientHeight;
        return rect.height > 0 && rect.bottom > viewport * 0.12 && rect.top < viewport * 0.78;
    };
    const checkVisibility = () => {
        if (active && targets.some(inView)) markSeen();
    };
    const onHashChange = () => {
        if (location.hash === '#actualidad') markSeen();
    };

    updateIndicators();
    if (seen >= newest) return;

    if (location.hash === '#actualidad') {
        markSeen();
        return;
    }

    if ('IntersectionObserver' in window && targets.length) {
        observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting && entry.intersectionRatio >= 0.12)) markSeen();
        }, { threshold: [0, 0.12, 0.3], rootMargin: '0px 0px -12% 0px' });
        targets.forEach(target => observer.observe(target));
    } else {
        window.addEventListener('scroll', checkVisibility, { passive: true });
        window.addEventListener('resize', checkVisibility, { passive: true });
        checkVisibility();
    }
    window.addEventListener('hashchange', onHashChange);
    // Sincronizar lecturas realizadas en otra pestaña del mismo navegador.
    window.addEventListener('storage', event => {
        if (event.key !== key) return;
        const otherSeen = Number(event.newValue || 0);
        if (Number.isFinite(otherSeen) && otherSeen > seen) {
            seen = otherSeen;
            updateIndicators();
            if (seen >= newest) {
                active = false;
                observer?.disconnect();
                window.removeEventListener('scroll', checkVisibility);
                window.removeEventListener('resize', checkVisibility);
                window.removeEventListener('hashchange', onHashChange);
            }
        }
    });
})();
