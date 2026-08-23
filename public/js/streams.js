document.addEventListener(
    'DOMContentLoaded',
    () => {
        /*
        |--------------------------------------------------------------------------
        | Formulario del streamer
        |--------------------------------------------------------------------------
        */

        const platformSelect =
            document.querySelector('#platform');

        const twitchField =
            document.querySelector(
                '[data-twitch-field]'
            );

        const youtubeField =
            document.querySelector(
                '[data-youtube-field]'
            );

        const twitchInput =
            document.querySelector(
                '#twitch_username'
            );

        const youtubeInput =
            document.querySelector(
                '#youtube_url'
            );

        const updatePlatformFields = () => {
            if (! platformSelect) {
                return;
            }

            const platform =
                platformSelect.value;

            const isTwitch =
                platform === 'twitch';

            if (twitchField) {
                twitchField.hidden =
                    ! isTwitch;
            }

            if (youtubeField) {
                youtubeField.hidden =
                    isTwitch;
            }

            if (twitchInput) {
                twitchInput.required =
                    isTwitch;
            }

            if (youtubeInput) {
                youtubeInput.required =
                    ! isTwitch;
            }
        };

        if (platformSelect) {

            platformSelect.addEventListener(
                'change',
                updatePlatformFields
            );

            updatePlatformFields();
        }
        
        const grids =
            document.querySelectorAll(
                '[data-live-grid]'
            );

        grids.forEach((grid) => {

            const eventId =
                grid.dataset.eventId
                ?? 'unknown';

            /*
            |--------------------------------------------------------------------------
            | Claves de preferencias
            |--------------------------------------------------------------------------
            */

            const orderStorageKey =
                `sqa-live-order:${eventId}`;

            const viewStorageKey =
                `sqa-live-view:${eventId}`;

            let draggedCard = null;


            /*
            |--------------------------------------------------------------------------
            | Elementos del selector de vista
            |--------------------------------------------------------------------------
            */

            const eventContainer =
                grid.closest(
                    '.live-event'
                );

            const layoutControls =
                eventContainer
                    ?.querySelector(
                        '[data-live-layout-controls]'
                    );

            const layoutButtons =
                layoutControls
                    ? Array.from(
                        layoutControls
                            .querySelectorAll(
                                '[data-live-view]'
                            )
                    )
                    : [];


            /*
            |--------------------------------------------------------------------------
            | Vista: automática / 2 / 3
            |--------------------------------------------------------------------------
            */

            const applyView = (
                requestedView,
                persist = true
            ) => {

                const allowedViews = [
                    'auto',
                    '2',
                    '3',
                ];

                const view =
                    allowedViews.includes(
                        requestedView
                    )
                        ? requestedView
                        : 'auto';

                grid.dataset.view = view;

                layoutButtons.forEach(
                    (button) => {

                        const active =
                            button.dataset
                                .liveView
                            === view;

                        button.classList.toggle(
                            'is-active',
                            active
                        );

                        button.setAttribute(
                            'aria-pressed',
                            active
                                ? 'true'
                                : 'false'
                        );
                    }
                );

                if (persist) {
                    localStorage.setItem(
                        viewStorageKey,
                        view
                    );
                }
            };


            /*
            |--------------------------------------------------------------------------
            | Recuperar vista guardada
            |--------------------------------------------------------------------------
            */

            const savedView =
                localStorage.getItem(
                    viewStorageKey
                )
                ?? 'auto';

            applyView(
                savedView,
                false
            );


            /*
            |--------------------------------------------------------------------------
            | Botones de vista
            |--------------------------------------------------------------------------
            */

            layoutButtons.forEach(
                (button) => {

                    button.addEventListener(
                        'click',
                        () => {

                            applyView(
                                button.dataset
                                    .liveView
                                ?? 'auto'
                            );
                        }
                    );
                }
            );


            /*
            |--------------------------------------------------------------------------
            | Recuperar orden de streams
            |--------------------------------------------------------------------------
            */

            const restoreOrder = () => {

                let savedOrder = [];

                try {
                    savedOrder =
                        JSON.parse(
                            localStorage
                                .getItem(
                                    orderStorageKey
                                )
                        ) ?? [];
                } catch {
                    savedOrder = [];
                }

                if (
                    ! Array.isArray(
                        savedOrder
                    )
                ) {
                    return;
                }

                const cards =
                    Array.from(
                        grid.querySelectorAll(
                            '[data-stream-id]'
                        )
                    );

                const cardsById =
                    new Map(
                        cards.map(
                            (card) => [
                                card.dataset
                                    .streamId,
                                card,
                            ]
                        )
                    );

                /*
                 * Primero los streams que
                 * el visitante ya había
                 * ordenado.
                 */
                savedOrder.forEach(
                    (streamId) => {

                        const id =
                            String(
                                streamId
                            );

                        const card =
                            cardsById.get(id);

                        if (! card) {
                            return;
                        }

                        grid.appendChild(
                            card
                        );

                        cardsById.delete(
                            id
                        );
                    }
                );

                /*
                 * Los nuevos streams se
                 * añaden después.
                 */
                cardsById.forEach(
                    (card) => {

                        grid.appendChild(
                            card
                        );
                    }
                );
            };


            /*
            |--------------------------------------------------------------------------
            | Guardar orden
            |--------------------------------------------------------------------------
            */

            const saveOrder = () => {

                const order =
                    Array.from(
                        grid.querySelectorAll(
                            '[data-stream-id]'
                        )
                    )
                    .map(
                        (card) =>
                            card.dataset
                                .streamId
                    );

                localStorage.setItem(
                    orderStorageKey,
                    JSON.stringify(
                        order
                    )
                );
            };


            /*
            |--------------------------------------------------------------------------
            | Calcular dónde insertar
            |--------------------------------------------------------------------------
            */

            const getCardAfterPointer = (
                x,
                y
            ) => {

                const cards = [
                    ...grid
                        .querySelectorAll(
                            '.live-card:not(.is-dragging)'
                        ),
                ];

                let closest = {
                    offset:
                        Number
                            .NEGATIVE_INFINITY,
                    element: null,
                };

                cards.forEach(
                    (card) => {

                        const rect =
                            card
                                .getBoundingClientRect();

                        const centerX =
                            rect.left
                            + rect.width / 2;

                        const centerY =
                            rect.top
                            + rect.height / 2;

                        /*
                         * Damos más peso
                         * al eje vertical
                         * para respetar
                         * las filas.
                         */
                        const deltaY =
                            y - centerY;

                        const deltaX =
                            x - centerX;

                        const offset =
                            deltaY * 10000
                            + deltaX;

                        if (
                            offset < 0
                            && offset
                                > closest.offset
                        ) {
                            closest = {
                                offset,
                                element: card,
                            };
                        }
                    }
                );

                return closest.element;
            };


            /*
            |--------------------------------------------------------------------------
            | Drag & Drop
            |--------------------------------------------------------------------------
            */

            grid
                .querySelectorAll(
                    '.live-card'
                )
                .forEach((card) => {

                    card.addEventListener(
                        'dragstart',
                        (event) => {

                            draggedCard =
                                card;

                            card.classList
                                .add(
                                    'is-dragging'
                                );

                            event
                                .dataTransfer
                                .effectAllowed =
                                'move';

                            event
                                .dataTransfer
                                .setData(
                                    'text/plain',
                                    card.dataset
                                        .streamId
                                );
                        }
                    );


                    card.addEventListener(
                        'dragend',
                        () => {

                            card.classList
                                .remove(
                                    'is-dragging'
                                );

                            grid
                                .querySelectorAll(
                                    '.is-drag-target'
                                )
                                .forEach(
                                    (
                                        element
                                    ) => {

                                        element
                                            .classList
                                            .remove(
                                                'is-drag-target'
                                            );
                                    }
                                );

                            draggedCard =
                                null;

                            saveOrder();
                        }
                    );
                });


            grid.addEventListener(
                'dragover',
                (event) => {

                    event.preventDefault();

                    if (! draggedCard) {
                        return;
                    }

                    event
                        .dataTransfer
                        .dropEffect =
                        'move';

                    grid
                        .querySelectorAll(
                            '.is-drag-target'
                        )
                        .forEach(
                            (card) => {

                                card
                                    .classList
                                    .remove(
                                        'is-drag-target'
                                    );
                            }
                        );

                    const afterCard =
                        getCardAfterPointer(
                            event.clientX,
                            event.clientY
                        );

                    if (
                        afterCard === null
                    ) {
                        grid.appendChild(
                            draggedCard
                        );

                        return;
                    }

                    afterCard
                        .classList
                        .add(
                            'is-drag-target'
                        );

                    grid.insertBefore(
                        draggedCard,
                        afterCard
                    );
                }
            );


            grid.addEventListener(
                'drop',
                (event) => {

                    event.preventDefault();

                    saveOrder();
                }
            );


            /*
            |--------------------------------------------------------------------------
            | Aplicar orden guardado
            |--------------------------------------------------------------------------
            */

            restoreOrder();
        });
    }
);