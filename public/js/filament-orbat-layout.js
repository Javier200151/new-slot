(() => {
    const containerSelector = '.orbat-group-cards > .fi-fo-repeater-items';
    const itemClass = 'fi-fo-repeater-item';
    const desktopBreakpoint = 1024;
    const minDesktopWidth = 760;
    const verticalGap = 16;

    const trackedContainers = new Set();
    const containerState = new WeakMap();

    const directItems = (container) =>
        Array.from(container.children).filter((child) =>
            child.classList?.contains(itemClass),
        );

    const clearMasonry = (container) => {
        container.classList.remove('orbat-masonry-active');

        directItems(container).forEach((item) => {
            item.style.removeProperty('--orbat-grid-column');
            item.style.removeProperty('--orbat-grid-row-start');
            item.style.removeProperty('--orbat-grid-row-span');

            // Limpiamos también estilos de la versión anterior del script.
            item.style.removeProperty('grid-column');
            item.style.removeProperty('grid-row-start');
            item.style.removeProperty('grid-row-end');
        });
    };

    const isActuallyVisible = (container) => {
        if (!container.isConnected || container.offsetParent === null) {
            return false;
        }

        const rect = container.getBoundingClientRect();

        return rect.width > 0 && rect.height >= 0;
    };

    const layoutContainer = (container) => {
        if (!container.isConnected) {
            trackedContainers.delete(container);
            return;
        }

        if (!isActuallyVisible(container)) {
            clearMasonry(container);
            return;
        }

        const rect = container.getBoundingClientRect();

        if (
            window.innerWidth < desktopBreakpoint
            || rect.width < minDesktopWidth
        ) {
            clearMasonry(container);
            return;
        }

        const items = directItems(container);

        if (!items.length) {
            clearMasonry(container);
            return;
        }

        // Medimos siempre con el grid normal y seguro. Solo después activamos
        // el modo de filas de 1 px. Esto evita calcular alturas 0 cuando el
        // modal de Filament todavía estaba oculto.
        container.classList.remove('orbat-masonry-active');

        items.forEach((item) => {
            item.style.removeProperty('--orbat-grid-column');
            item.style.removeProperty('--orbat-grid-row-start');
            item.style.removeProperty('--orbat-grid-row-span');
            item.style.removeProperty('grid-column');
            item.style.removeProperty('grid-row-start');
            item.style.removeProperty('grid-row-end');
        });

        // Forzar layout antes de leer alturas naturales.
        void container.offsetHeight;

        const measurements = items.map((item, index) => ({
            item,
            columnIndex: index % 2,
            height: Math.max(
                1,
                Math.ceil(
                    Math.max(
                        item.getBoundingClientRect().height,
                        item.scrollHeight,
                    ),
                ),
            ),
        }));

        // Si por cualquier motivo el modal sigue sin ser medible, nos quedamos
        // en el fallback de grid normal en vez de arriesgar una superposición.
        if (measurements.some(({ height }) => !Number.isFinite(height) || height < 8)) {
            clearMasonry(container);
            return;
        }

        const nextRow = [1, 1];

        measurements.forEach(({ item, columnIndex, height }) => {
            const start = nextRow[columnIndex];
            const span = height + verticalGap;

            item.style.setProperty('--orbat-grid-column', String(columnIndex + 1));
            item.style.setProperty('--orbat-grid-row-start', String(start));
            item.style.setProperty('--orbat-grid-row-span', String(span));

            nextRow[columnIndex] = start + span;
        });

        container.classList.add('orbat-masonry-active');
    };

    const scheduleLayout = (container, delay = 0) => {
        const state = containerState.get(container);
        if (!state) return;

        if (state.timeout !== null) {
            window.clearTimeout(state.timeout);
        }

        state.timeout = window.setTimeout(() => {
            state.timeout = null;

            if (state.frame !== null) {
                window.cancelAnimationFrame(state.frame);
            }

            state.frame = window.requestAnimationFrame(() => {
                state.frame = null;
                layoutContainer(container);
            });
        }, delay);
    };

    const observeItems = (container) => {
        const state = containerState.get(container);
        if (!state) return;

        directItems(container).forEach((item) => {
            if (state.items.has(item)) return;

            state.items.add(item);
            state.resizeObserver.observe(item);
        });
    };

    const trackContainer = (container) => {
        if (containerState.has(container)) return;

        const state = {
            frame: null,
            timeout: null,
            items: new WeakSet(),
            resizeObserver: null,
            mutationObserver: null,
        };

        state.resizeObserver = new ResizeObserver(() => {
            scheduleLayout(container, 40);
        });

        state.mutationObserver = new MutationObserver(() => {
            observeItems(container);
            scheduleLayout(container, 40);
        });

        containerState.set(container, state);
        trackedContainers.add(container);

        state.mutationObserver.observe(container, {
            childList: true,
            subtree: false,
        });

        observeItems(container);

        // Filament puede insertar el contenido antes de que termine la apertura
        // del modal. Hacemos una segunda medición corta una vez visible.
        scheduleLayout(container, 0);
        scheduleLayout(container, 160);
    };

    const scan = () => {
        document.querySelectorAll(containerSelector).forEach(trackContainer);
    };

    const documentObserver = new MutationObserver((mutations) => {
        scan();

        trackedContainers.forEach((container) => {
            scheduleLayout(container, 80);
        });

        // Fallback para reemplazos de nodos realizados por Filament/Livewire.
        // Evitamos reconstruir el estado en un cambio ajeno al modal ORBAT.
        const changedOrbat = mutations.some((mutation) => {
            if (mutation.target.closest?.('.event-orbat-visibility-section')) return true;
            return Array.from(mutation.addedNodes).some((node) => node.nodeType === 1
                && (node.matches?.('.event-orbat-visibility-section')
                    || node.querySelector?.('.event-orbat-visibility-section')));
        });
        if (changedOrbat) queueVisibilityRestore(!livewireHookRegistered);
    });

    const relayoutAll = () => {
        scan();
        trackedContainers.forEach((container) => scheduleLayout(container, 40));
    };

    const start = () => {
        scan();

        if (document.body) {
            documentObserver.observe(document.body, {
                childList: true,
                subtree: true,
            });
        }

        window.addEventListener('resize', relayoutAll, { passive: true });
        window.addEventListener('pageshow', relayoutAll, { passive: true });

        document.addEventListener('livewire:navigated', relayoutAll);
    };

    // Livewire vuelve a pintar los toggles del modal al modificar la visibilidad.
    // El estado plegado es puramente visual: guardarlo fuera del DOM evita
    // que reaparezcan todas las escuadras abiertas tras cada actualización.
    const visibilityCollapsed = new Map();
    let visibilityViewport = null;
    let visibilityRestoreQueued = false;
    let visibilityRestoreViewportRequested = false;
    let livewireHookRegistered = false;

    const visibilityGroupKey = (section) => {
        const eventId = section?.dataset.orbatEventId;
        const groupId = section?.dataset.orbatGroupIndex;
        return eventId !== undefined && groupId !== undefined ? `${eventId}:${groupId}` : null;
    };

    const preserveVisibilityViewport = (element) => {
        const modal = element.closest('.fi-modal-window');
        if (!modal) return;

        const scrollAreas = [];
        for (let node = element; node && node !== document.body; node = node.parentElement) {
            const overflow = window.getComputedStyle(node).overflowY;
            if (/auto|scroll/.test(overflow) && node.scrollHeight > node.clientHeight) {
                scrollAreas.push({ node, top: node.scrollTop, left: node.scrollLeft });
            }
        }

        visibilityViewport = {
            modal,
            scrollAreas,
            windowX: window.scrollX,
            windowY: window.scrollY,
        };
    };

    const restoreVisibilityViewport = () => {
        if (!visibilityViewport) return;
        const snapshot = visibilityViewport;
        visibilityViewport = null;

        for (const { node, top, left } of snapshot.scrollAreas) {
            if (node.isConnected) {
                node.scrollTop = top;
                node.scrollLeft = left;
            }
        }

        if (window.scrollY !== snapshot.windowY || window.scrollX !== snapshot.windowX) {
            window.scrollTo(snapshot.windowX, snapshot.windowY);
        }
    };

    const restoreVisibilitySections = () => {
        document.querySelectorAll('.fi-modal-window .event-orbat-visibility-section').forEach((section) => {
            const key = visibilityGroupKey(section);
            if (key === null || !visibilityCollapsed.has(key)) return;
            setOrbatSectionExpanded(section, !visibilityCollapsed.get(key));
        });
    };

    const queueVisibilityRestore = (restoreViewport = false) => {
        visibilityRestoreViewportRequested ||= restoreViewport;
        if (visibilityRestoreQueued) return;
        visibilityRestoreQueued = true;
        window.requestAnimationFrame(() => {
            visibilityRestoreQueued = false;
            const shouldRestoreViewport = visibilityRestoreViewportRequested;
            visibilityRestoreViewportRequested = false;
            restoreVisibilitySections();
            if (shouldRestoreViewport) restoreVisibilityViewport();
        });
    };

    // Capturamos antes de que Livewire procese el cambio de un toggle.
    const captureVisibilityChange = (event) => {
        const control = event.target.closest?.('.event-orbat-visibility-control');
        if (!control?.closest('.event-orbat-visibility-section')) return;
        preserveVisibilityViewport(control);
    };

    document.addEventListener('pointerdown', captureVisibilityChange, true);
    document.addEventListener('keydown', (event) => {
        if (event.key === ' ' || event.key === 'Enter') captureVisibilityChange(event);
    }, true);

    const registerVisibilityLivewireHook = () => {
        if (livewireHookRegistered || typeof window.Livewire?.hook !== 'function') return;
        livewireHookRegistered = true;
        const isOrbatMorph = ({ el }) => el?.querySelector?.('.event-orbat-visibility-section')
            || el?.closest?.('.event-orbat-visibility-section')
            || el?.matches?.('.event-orbat-visibility-section');

        // `morph.updated` puede suceder muchas veces durante la actualización:
        // recupera el plegado, pero esperamos a `morphed` para restaurar el scroll.
        window.Livewire.hook('morph.updated', (context) => {
            if (isOrbatMorph(context)) queueVisibilityRestore(false);
        });
        window.Livewire.hook('morphed', (context) => {
            if (isOrbatMorph(context)) queueVisibilityRestore(true);
        });
    };

    document.addEventListener('livewire:init', registerVisibilityLivewireHook);
    registerVisibilityLivewireHook();

    const orbatModalScope = (element) =>
        element.closest('.fi-modal-window') || document;

    const setOrbatSectionExpanded = (section, expanded) => {
        if (!section) return;

        section.classList.toggle(
            'event-orbat-visibility-section--collapsed',
            !expanded,
        );

        const toggle = section.querySelector('[data-orbat-toggle-section]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

            const chevron = toggle.querySelector('[data-orbat-collapse-chevron]');
            if (chevron) {
                chevron.textContent = expanded ? '▾' : '▸';
            }
        }
    };

    const handleOrbatCollapseClick = (event) => {
        const individualToggle = event.target.closest('[data-orbat-toggle-section]');

        if (individualToggle) {
            event.preventDefault();
            event.stopPropagation();

            const section = individualToggle.closest('.event-orbat-visibility-section');
            if (!section) return;

            const shouldExpand = section.classList.contains(
                'event-orbat-visibility-section--collapsed',
            );

            const key = visibilityGroupKey(section);
            if (key !== null) visibilityCollapsed.set(key, !shouldExpand);
            setOrbatSectionExpanded(section, shouldExpand);
            return;
        }

        const bulkToggle = event.target.closest('[data-orbat-toggle-all]');
        if (!bulkToggle) return;

        event.preventDefault();
        event.stopPropagation();

        const expanded = bulkToggle.dataset.orbatToggleAll === 'expand';
        const scope = orbatModalScope(bulkToggle);

        scope.querySelectorAll('.event-orbat-visibility-section').forEach((section) => {
            const key = visibilityGroupKey(section);
            if (key !== null) visibilityCollapsed.set(key, !expanded);
            setOrbatSectionExpanded(section, expanded);
        });
    };

    document.addEventListener('click', handleOrbatCollapseClick);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
