/* Navegación lateral de las secciones de un evento.
   Usa siempre los enlaces visibles del encabezado: no inventa apartados. */
document.addEventListener('DOMContentLoaded', () => {
    const eventPage = document.querySelector('.event-detail');
    const headerNav = eventPage?.querySelector('.event-detail__section-nav');
    if (!eventPage || !headerNav) return;

    const sections = [...headerNav.querySelectorAll('a[href^="#"]')]
        .map((anchor) => {
            const hash = anchor.getAttribute('href');
            const id = hash?.slice(1);
            const target = id ? document.getElementById(id) : null;
            const label = anchor.textContent.trim().replace(/\s+/g, ' ');
            return target && label ? { hash, id, target, label } : null;
        })
        .filter(Boolean)
        // Multimedia se muestra físicamente encima del Briefing al finalizar
        // el evento, por lo que su enlace debe abrir el panel lateral.
        // Solo existe si el propio encabezado lo muestra (condición Blade).
        .sort((a, b) => (a.id === 'multimedia' ? -1 : 0) - (b.id === 'multimedia' ? -1 : 0));

    if (!sections.length) return;

    const nav = document.createElement('nav');
    nav.className = 'event-jump-nav';
    nav.setAttribute('aria-label', 'Saltos a las secciones del evento');
    nav.setAttribute('data-event-jump-nav', '');
    nav.hidden = true;

    const title = document.createElement('span');
    title.className = 'event-jump-nav__title';
    title.textContent = 'SECCIONES';
    nav.append(title);

    const controls = sections.map((section) => {
        const link = document.createElement('a');
        link.className = 'event-jump-nav__link';
        link.setAttribute('href', section.hash);
        link.setAttribute('title', `Ir a ${section.label}`);
        link.setAttribute('aria-label', `Ir a ${section.label}`);

        const full = document.createElement('span');
        full.className = 'event-jump-nav__label';
        full.textContent = section.label;
        link.append(full);

        // Desplegar los <details> antes del salto para no apuntar a contenido oculto.
        link.addEventListener('click', () => {
            let parent = section.target;
            while (parent && parent !== eventPage) {
                if (parent instanceof HTMLDetailsElement) parent.open = true;
                parent = parent.parentElement;
            }
        });
        nav.append(link);
        return { ...section, link };
    });

    document.body.append(nav);
    const desktop = window.matchMedia('(min-width: 1181px) and (hover: hover) and (pointer: fine)');
    const footer = document.querySelector('.landing-footer');
    let scheduled = false;

    const update = () => {
        scheduled = false;
        const headerPassed = headerNav.getBoundingClientRect().bottom < 88;
        const footerReached = footer && footer.getBoundingClientRect().top < 150;
        const visible = desktop.matches && headerPassed && !footerReached;
        nav.hidden = !visible;
        if (!visible) return;

        // El orden de los enlaces del encabezado puede diferir del orden real
        // de los bloques en un evento finalizado (por ejemplo, Multimedia).
        // Calcular por posición evita que quede resaltada una sección equivocada.
        const marker = 165;
        const ordered = controls
            .map((section) => ({ ...section, rect: section.target.getBoundingClientRect() }))
            .filter((section) => section.target.getClientRects().length > 0 && section.rect.height > 0)
            .sort((a, b) => a.rect.top - b.rect.top);

        let active = null;
        for (const section of ordered) {
            if (section.rect.top <= marker) active = section;
            else break;
        }
        // Al dejar atrás la cabecera, pero antes de la primera sección,
        // evitamos que una sección futura se marque por anticipado.
        controls.forEach((section) => {
            const selected = active?.id === section.id;
            section.link.classList.toggle('is-active', selected);
            if (selected) section.link.setAttribute('aria-current', 'location');
            else section.link.removeAttribute('aria-current');
        });
    };

    const scheduleUpdate = () => {
        if (scheduled) return;
        scheduled = true;
        window.requestAnimationFrame(update);
    };

    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    window.addEventListener('hashchange', scheduleUpdate);
    if (desktop.addEventListener) desktop.addEventListener('change', scheduleUpdate);
    update();
});
