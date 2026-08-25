document.addEventListener('DOMContentLoaded', () => {

    /*
    |--------------------------------------------------------------------------
    | Catálogo
    |--------------------------------------------------------------------------
    */

    const catalog =
        document.querySelector(
            '[data-operations-catalog]'
        );

    if (!catalog) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Vista cuadrícula / lista
    |--------------------------------------------------------------------------
    */

    const viewButtons =
        document.querySelectorAll(
            '[data-operation-view]'
        );


    const viewStorageKey =
        'newslot.operations.view';


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


        viewButtons.forEach(
            (button) => {

                const buttonView =
                    button.dataset
                        .operationView;


                const isActive =
                    buttonView
                    === normalizedView;


                button.classList.toggle(
                    'is-active',
                    isActive
                );


                button.setAttribute(
                    'aria-pressed',
                    isActive
                        ? 'true'
                        : 'false'
                );

            }
        );


        try {

            localStorage.setItem(
                viewStorageKey,
                normalizedView
            );

        } catch (error) {

            /*
             * Si localStorage no está
             * disponible, simplemente
             * no recordamos la vista.
             */

        }
    };


    /*
    |--------------------------------------------------------------------------
    | Recuperar vista anterior
    |--------------------------------------------------------------------------
    */

    let initialView =
        'grid';


    try {

        const savedView =
            localStorage.getItem(
                viewStorageKey
            );


        if (
            savedView === 'grid'
            || savedView === 'list'
        ) {
            initialView =
                savedView;
        }

    } catch (error) {

        /*
         * Mantener cuadrícula
         * por defecto.
         */

    }


    applyView(
        initialView
    );


    /*
    |--------------------------------------------------------------------------
    | Botones cuadrícula / lista
    |--------------------------------------------------------------------------
    */

    viewButtons.forEach(
        (button) => {

            button.addEventListener(
                'click',
                () => {

                    applyView(
                        button.dataset
                            .operationView
                    );

                }
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Campañas
    |--------------------------------------------------------------------------
    |
    | Las campañas ya NO utilizan <details>.
    |
    | La ficha de campaña y todos sus operativos
    | son hermanos directos dentro del grid.
    |
    | Al abrir:
    |
    |     campaña
    |     hijo
    |     hijo
    |     operativo normal
    |
    | Al cerrar:
    |
    |     campaña
    |     operativo normal
    |
    | Los elementos con [hidden] desaparecen
    | completamente del layout, por lo que
    | CSS Grid recoloca automáticamente todas
    | las tarjetas y no deja huecos.
    |
    */

    const campaignToggles =
        catalog.querySelectorAll(
            '[data-campaign-toggle]'
        );


    /*
    |--------------------------------------------------------------------------
    | Cambiar estado de una campaña
    |--------------------------------------------------------------------------
    */

    const setCampaignState = (
        toggle,
        isOpen
    ) => {

        const campaignId =
            toggle.dataset
                .campaignId;


        if (!campaignId) {
            return;
        }


        const children =
            catalog.querySelectorAll(
                `[data-campaign-child="${campaignId}"]`
            );


        /*
         * Mostrar / ocultar todos los
         * operativos de la campaña.
         */

        children.forEach(
            (child) => {

                child.hidden =
                    !isOpen;

            }
        );


        /*
         * Estado accesible.
         */

        toggle.setAttribute(
            'aria-expanded',
            isOpen
                ? 'true'
                : 'false'
        );


        /*
         * Clase para controlar posteriormente
         * estilos de campaña abierta/cerrada.
         */

        toggle.classList.toggle(
            'is-open',
            isOpen
        );

    };


    /*
    |--------------------------------------------------------------------------
    | Alternar campaña
    |--------------------------------------------------------------------------
    */

    const toggleCampaign = (
        toggle
    ) => {

        const currentlyOpen =
            toggle.getAttribute(
                'aria-expanded'
            ) === 'true';


        setCampaignState(
            toggle,
            !currentlyOpen
        );

    };


    /*
    |--------------------------------------------------------------------------
    | Inicializar campañas
    |--------------------------------------------------------------------------
    */

    campaignToggles.forEach(
        (toggle) => {

            /*
             * Todas las campañas empiezan
             * cerradas.
             *
             * Esto además garantiza que,
             * aunque el navegador restaurase
             * algún estado raro, los hijos
             * comiencen realmente ocultos.
             */

            setCampaignState(
                toggle,
                false
            );


            /*
             * Click.
             */

            toggle.addEventListener(
                'click',
                () => {

                    toggleCampaign(
                        toggle
                    );

                }
            );


            /*
             * Accesibilidad por teclado.
             *
             * Como la ficha utiliza:
             *
             * role="button"
             * tabindex="0"
             *
             * permitimos activar con:
             *
             * Enter
             * Espacio
             */

            toggle.addEventListener(
                'keydown',
                (event) => {

                    if (
                        event.key !== 'Enter'
                        && event.key !== ' '
                    ) {
                        return;
                    }


                    event.preventDefault();


                    toggleCampaign(
                        toggle
                    );

                }
            );

        }
    );

});
document.addEventListener('DOMContentLoaded', () => {
    const filters = document.querySelector('[data-operations-filters]');

    if (!filters) {
        return;
    }

    const toggle = filters.querySelector('[data-operations-filters-toggle]');
    const advanced = filters.querySelector('[data-operations-filters-advanced]');
    const toggleLabel = filters.querySelector('[data-operations-filters-toggle-label]');

    if (!toggle || !advanced) {
        return;
    }

    const storageKey = 'operationsFiltersExpanded';
    const hasAdvancedFilters = filters.dataset.hasAdvancedFilters === '1';

    let expanded =
        hasAdvancedFilters ||
        window.localStorage.getItem(storageKey) === '1';

    const applyState = () => {
        filters.classList.toggle('is-expanded', expanded);
        advanced.hidden = !expanded;
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

        if (toggleLabel) {
            toggleLabel.textContent = expanded
                ? 'Ocultar filtros'
                : 'Más filtros';
        }

        window.localStorage.setItem(
            storageKey,
            expanded ? '1' : '0'
        );
    };

    toggle.addEventListener('click', () => {
        expanded = !expanded;
        applyState();
    });

    applyState();
});