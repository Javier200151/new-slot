(() => {
    const containerSelector = '.orbat-group-cards > .fi-fo-repeater-items';
    const itemClass = 'fi-fo-repeater-item';
    const desktopBreakpoint = 1024;
    const verticalGap = 16;

    const trackedContainers = new Set();
    const containerState = new WeakMap();

    const directItems = (container) =>
        Array.from(container.children).filter((child) =>
            child.classList?.contains(itemClass),
        );

    const clearDesktopPositioning = (container) => {
        directItems(container).forEach((item) => {
            item.style.removeProperty('grid-column');
            item.style.removeProperty('grid-row-start');
            item.style.removeProperty('grid-row-end');
        });
    };

    const layoutContainer = (container) => {
        if (!container.isConnected) {
            trackedContainers.delete(container);
            return;
        }

        const items = directItems(container);

        if (
            window.innerWidth < desktopBreakpoint
            || container.getBoundingClientRect().width < 760
        ) {
            clearDesktopPositioning(container);
            return;
        }

        // Primero fijamos el ancho de cada tarjeta según su posición real.
        // Después medimos la altura ya con ese ancho y calculamos la pila de
        // cada columna de forma independiente. Así el orden visual siempre es:
        // 1 -> 2, 3 -> 4, 5 -> 6... sin huecos verticales artificiales.
        items.forEach((item, index) => {
            item.style.gridColumn = String((index % 2) + 1);
            item.style.gridRowStart = 'auto';
            item.style.gridRowEnd = 'auto';
        });

        const nextRow = [1, 1];

        items.forEach((item, index) => {
            const columnIndex = index % 2;
            const height = Math.max(
                1,
                Math.ceil(item.getBoundingClientRect().height),
            );
            const start = nextRow[columnIndex];
            const span = height + verticalGap;

            item.style.gridColumn = String(columnIndex + 1);
            item.style.gridRowStart = String(start);
            item.style.gridRowEnd = `span ${span}`;

            nextRow[columnIndex] = start + span;
        });
    };

    const scheduleLayout = (container) => {
        const state = containerState.get(container);
        if (!state || state.frame !== null) return;

        state.frame = window.requestAnimationFrame(() => {
            state.frame = null;
            layoutContainer(container);
        });
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
            items: new WeakSet(),
            resizeObserver: null,
            mutationObserver: null,
        };

        state.resizeObserver = new ResizeObserver(() => {
            scheduleLayout(container);
        });

        state.mutationObserver = new MutationObserver(() => {
            observeItems(container);
            scheduleLayout(container);
        });

        containerState.set(container, state);
        trackedContainers.add(container);

        state.mutationObserver.observe(container, {
            childList: true,
        });

        observeItems(container);
        scheduleLayout(container);
    };

    const scan = () => {
        document.querySelectorAll(containerSelector).forEach(trackContainer);
    };

    const documentObserver = new MutationObserver(() => {
        scan();
    });

    const start = () => {
        scan();

        if (document.body) {
            documentObserver.observe(document.body, {
                childList: true,
                subtree: true,
            });
        }

        window.addEventListener('resize', () => {
            trackedContainers.forEach((container) => {
                scheduleLayout(container);
            });
        }, { passive: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
