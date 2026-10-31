(() => {
    const section = document.querySelector('[data-sqa-news-section]');
    if (!section) return;

    const newest = Number(section.dataset.sqaLatest || 0);
    if (newest <= 0) return;
    const key = `sqa-announcements-seen-${section.dataset.sqaAccount || 'guest'}`;
    const maxAge = 7 * 24 * 60 * 60;
    let seen = 0;
    try { seen = Number(localStorage.getItem(key) || 0); } catch { /* Navegación privada. */ }
    const baseline = seen > 0 ? seen : Math.floor(Date.now() / 1000) - maxAge;
    const hasNew = newest > baseline;

    section.querySelectorAll('[data-sqa-news-item]').forEach((card) => {
        const badge = card.querySelector('[data-sqa-news-new]');
        if (badge) badge.hidden = Number(card.dataset.published || 0) <= baseline;
    });
    document.querySelectorAll('[data-sqa-news-hero], [data-sqa-section-new]').forEach((el) => { el.hidden = !hasNew; });

    if (!('IntersectionObserver' in window)) return;
    let pending;
    const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            if (!pending) pending = setTimeout(() => {
                try { localStorage.setItem(key, String(Math.max(seen, newest))); } catch { /* Bloqueado. */ }
                observer.disconnect();
            }, 1500);
        } else if (pending) {
            clearTimeout(pending);
            pending = null;
        }
    }, { threshold: 0.2 });
    observer.observe(section);
})();
