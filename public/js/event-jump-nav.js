/* Navegación de las secciones del evento.
   El salto se alinea con el encabezado visible, no con el espacio superior
   de la sección; también funciona al abrir apartados plegados. */
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
            return target && label ? { hash, id, target, label, headerLink: anchor } : null;
        })
        .filter(Boolean)
        // En los eventos finalizados, Multimedia aparece antes de Briefing.
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

    // Las secciones tienen padding-top (62px), por lo que alinear el nodo
    // completo deja el título demasiado abajo. Alineamos su cabecera visible.
    const visibleHeading = (target) => {
        if (target instanceof HTMLDetailsElement) {
            return target.querySelector(':scope > summary') || target;
        }
        return target.querySelector(':scope > header, :scope > summary') || target;
    };

    const topOffset = () => window.matchMedia('(max-width: 700px)').matches ? 92 : 108;
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const desktop = window.matchMedia('(min-width: 1181px) and (hover: hover) and (pointer: fine)');
    const footer = document.querySelector('.landing-footer');
    let scheduled = false;
    let navigatingTo = null;
    let navigationTimer = null;

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
        nav.append(link);
        return { ...section, link, heading: visibleHeading(section.target) };
    });

    document.body.append(nav);

    const update = () => {
        scheduled = false;
        const headerPassed = headerNav.getBoundingClientRect().bottom < 88;
        const footerReached = footer && footer.getBoundingClientRect().top < 150;
        const visible = desktop.matches && headerPassed && !footerReached;
        nav.hidden = !visible;
        if (!visible) return;

        // Calcular usando los encabezados reales evita marcar Facciones
        // cuando Comunicaciones ya está visualmente en pantalla.
        const marker = topOffset() + 35;
        const ordered = controls
            .map((section) => ({ ...section, rect: section.heading.getBoundingClientRect() }))
            .filter((section) => section.heading.getClientRects().length > 0 && section.rect.height > 0)
            .sort((a, b) => a.rect.top - b.rect.top);

        let active = null;
        for (const section of ordered) {
            if (section.rect.top <= marker) active = section;
            else break;
        }
        const selectedId = navigatingTo || active?.id;
        controls.forEach((section) => {
            const selected = selectedId === section.id;
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

    const navigate = (event, section) => {
        // Mantener el comportamiento normal de abrir en otra pestaña.
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();

        // Desplegar antes de medir, por si cambia la altura del documento.
        let element = section.target;
        while (element && element !== eventPage) {
            if (element instanceof HTMLDetailsElement) element.open = true;
            element = element.parentElement;
        }

        navigatingTo = section.id;
        window.clearTimeout(navigationTimer);
        if (window.location.hash !== section.hash) {
            window.history.pushState(null, '', section.hash);
        }
        scheduleUpdate();

        // Esperamos al layout tras abrir los <details> para que el salto no
        // termine a mitad de la sección anterior.
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                const y = window.scrollY + section.heading.getBoundingClientRect().top - topOffset();
                window.scrollTo({
                    top: Math.max(0, y),
                    behavior: prefersReducedMotion.matches ? 'instant' : 'smooth',
                });
            });
        });

        // Mientras dura el desplazamiento, la selección sigue el clic.
        navigationTimer = window.setTimeout(() => {
            if (navigatingTo === section.id) navigatingTo = null;
            scheduleUpdate();
        }, prefersReducedMotion.matches ? 100 : 1100);
    };

    controls.forEach((section) => {
        section.link.addEventListener('click', (event) => navigate(event, section));
        section.headerLink.addEventListener('click', (event) => navigate(event, section));
    });

    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    window.addEventListener('hashchange', scheduleUpdate);
    if (desktop.addEventListener) desktop.addEventListener('change', scheduleUpdate);
    update();
});
