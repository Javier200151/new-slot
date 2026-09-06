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

    const documentObserver = new MutationObserver(() => {
        scan();

        trackedContainers.forEach((container) => {
            scheduleLayout(container, 80);
        });
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
