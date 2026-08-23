document.addEventListener('DOMContentLoaded', () => {
    const grids = document.querySelectorAll(
        '[data-live-grid]'
    );

    grids.forEach((grid) => {
        const eventId =
            grid.dataset.eventId ?? 'unknown';

        const storageKey =
            `sqa-live-order:${eventId}`;

        let draggedCard = null;

        /*
        |--------------------------------------------------------------------------
        | Recuperar orden guardado
        |--------------------------------------------------------------------------
        */

        const restoreOrder = () => {
            let savedOrder = [];

            try {
                savedOrder =
                    JSON.parse(
                        localStorage.getItem(
                            storageKey
                        )
                    ) ?? [];
            } catch {
                savedOrder = [];
            }

            if (! Array.isArray(savedOrder)) {
                return;
            }

            const cards = Array.from(
                grid.querySelectorAll(
                    '[data-stream-id]'
                )
            );

            const cardsById = new Map(
                cards.map((card) => [
                    card.dataset.streamId,
                    card,
                ])
            );

            /*
             * Primero colocamos los streams
             * cuyo orden ya conocíamos.
             */
            savedOrder.forEach((streamId) => {
                const card =
                    cardsById.get(
                        String(streamId)
                    );

                if (! card) {
                    return;
                }

                grid.appendChild(card);

                cardsById.delete(
                    String(streamId)
                );
            });

            /*
             * Los streams nuevos aparecen
             * después de los ya ordenados.
             */
            cardsById.forEach((card) => {
                grid.appendChild(card);
            });
        };


        /*
        |--------------------------------------------------------------------------
        | Guardar orden
        |--------------------------------------------------------------------------
        */

        const saveOrder = () => {
            const order = Array.from(
                grid.querySelectorAll(
                    '[data-stream-id]'
                )
            ).map(
                (card) =>
                    card.dataset.streamId
            );

            localStorage.setItem(
                storageKey,
                JSON.stringify(order)
            );
        };


        /*
        |--------------------------------------------------------------------------
        | Buscar posición del ratón
        |--------------------------------------------------------------------------
        */

        const getCardAfterPointer = (
            x,
            y
        ) => {
            const cards = [
                ...grid.querySelectorAll(
                    '.live-card:not(.is-dragging)'
                ),
            ];

            let closest = {
                offset:
                    Number.NEGATIVE_INFINITY,
                element: null,
            };

            cards.forEach((card) => {
                const rect =
                    card.getBoundingClientRect();

                const centerX =
                    rect.left
                    + rect.width / 2;

                const centerY =
                    rect.top
                    + rect.height / 2;

                /*
                 * Damos más peso al eje Y
                 * porque la cuadrícula se
                 * organiza fundamentalmente
                 * por filas.
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
            });

            return closest.element;
        };


        /*
        |--------------------------------------------------------------------------
        | Eventos de drag
        |--------------------------------------------------------------------------
        */

        grid
            .querySelectorAll('.live-card')
            .forEach((card) => {

                card.addEventListener(
                    'dragstart',
                    (event) => {
                        draggedCard = card;

                        card.classList.add(
                            'is-dragging'
                        );

                        event.dataTransfer.effectAllowed =
                            'move';

                        event.dataTransfer.setData(
                            'text/plain',
                            card.dataset.streamId
                        );
                    }
                );


                card.addEventListener(
                    'dragend',
                    () => {
                        card.classList.remove(
                            'is-dragging'
                        );

                        grid
                            .querySelectorAll(
                                '.is-drag-target'
                            )
                            .forEach(
                                (element) => {
                                    element
                                        .classList
                                        .remove(
                                            'is-drag-target'
                                        );
                                }
                            );

                        draggedCard = null;

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

                event.dataTransfer.dropEffect =
                    'move';

                grid
                    .querySelectorAll(
                        '.is-drag-target'
                    )
                    .forEach((card) => {
                        card.classList.remove(
                            'is-drag-target'
                        );
                    });

                const afterCard =
                    getCardAfterPointer(
                        event.clientX,
                        event.clientY
                    );

                if (afterCard === null) {
                    grid.appendChild(
                        draggedCard
                    );

                    return;
                }

                afterCard.classList.add(
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
        | Aplicar preferencia del visitante
        |--------------------------------------------------------------------------
        */

        restoreOrder();
    });
});