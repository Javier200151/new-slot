document.addEventListener('DOMContentLoaded', () => {
    const catalog = document.querySelector(
        '[data-operations-catalog]'
    );

    const buttons = document.querySelectorAll(
        '[data-operation-view]'
    );

    if (!catalog || buttons.length === 0) {
        return;
    }

    const storageKey = 'newslot.operations.view';

    const applyView = (view) => {
        const normalizedView =
            view === 'list'
                ? 'list'
                : 'grid';

        catalog.classList.toggle(
            'is-grid',
            normalizedView === 'grid'
        );

        catalog.classList.toggle(
            'is-list',
            normalizedView === 'list'
        );

        buttons.forEach((button) => {
            const buttonView =
                button.dataset.operationView;

            const isActive =
                buttonView === normalizedView;

            button.classList.toggle(
                'is-active',
                isActive
            );

            button.setAttribute(
                'aria-pressed',
                isActive ? 'true' : 'false'
            );
        });

        try {
            localStorage.setItem(
                storageKey,
                normalizedView
            );
        } catch (error) {
            // Si localStorage no está disponible,
            // la vista simplemente no se recordará.
        }
    };

    let initialView = 'grid';

    try {
        const savedView =
            localStorage.getItem(storageKey);

        if (
            savedView === 'grid'
            || savedView === 'list'
        ) {
            initialView = savedView;
        }
    } catch (error) {
        // Mantener cuadrícula por defecto.
    }

    applyView(initialView);

    buttons.forEach((button) => {
        button.addEventListener(
            'click',
            () => {
                applyView(
                    button.dataset.operationView
                );
            }
        );
    });
});