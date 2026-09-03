document.addEventListener('DOMContentLoaded', () => {
    const page =
        document.querySelector('.event-detail');

    const toggle =
        document.querySelector(
            '[data-event-editor-toggle]'
        );

    if (!page || !toggle) {
        return;
    }

    toggle.addEventListener('click', () => {
        const enabled =
            !page.classList.contains(
                'is-editor-mode'
            );

        page.classList.toggle(
            'is-editor-mode',
            enabled
        );

        toggle.setAttribute(
            'aria-pressed',
            enabled ? 'true' : 'false'
        );

        toggle.lastChild.textContent =
            enabled
                ? ' Salir del modo edición'
                : ' Modo edición';
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const openDetailsFromHash = () => {
        const hash = window.location.hash;

        if (!hash) {
            return;
        }

        const id = decodeURIComponent(
            hash.substring(1)
        );

        const element = document.getElementById(id);

        if (element instanceof HTMLDetailsElement) {
            element.open = true;
        }
    };

    document
        .querySelectorAll('a[href^="#"]')
        .forEach((link) => {
            link.addEventListener('click', () => {
                const id = decodeURIComponent(
                    link.getAttribute('href')
                        .substring(1)
                );

                const target =
                    document.getElementById(id);

                if (
                    target
                    instanceof HTMLDetailsElement
                ) {
                    target.open = true;
                }
            });
        });

    openDetailsFromHash();

    window.addEventListener(
        'hashchange',
        openDetailsFromHash
    );
});

document.addEventListener('DOMContentLoaded', () => {
    const signatureFrames = [...document.querySelectorAll('[data-signature-frame]')];

    if (signatureFrames.length === 0) {
        return;
    }

    const signatureObservers = new WeakMap();

    const measureSignature = (frame) => {
        try {
            const signature = frame.contentDocument?.querySelector('.firma');

            if (!signature) {
                return;
            }

            const height = Math.ceil(signature.getBoundingClientRect().height);

            if (height > 0) {
                frame.style.height = `${height}px`;
            }
        } catch {
            // La altura inicial de CSS se conserva si la firma no es del mismo dominio.
        }
    };

    const observeSignature = (frame) => {
        try {
            const signatureDocument = frame.contentDocument;
            const signature = signatureDocument?.querySelector('.firma');

            if (!signature) {
                return;
            }

            measureSignature(frame);

            signatureObservers.get(frame)?.disconnect();

            const observer = new ResizeObserver(() => measureSignature(frame));
            observer.observe(signature);
            signatureObservers.set(frame, observer);

            signatureDocument.querySelectorAll('img').forEach((image) => {
                if (!image.complete) {
                    image.addEventListener('load', () => measureSignature(frame), { once: true });
                }
            });
        } catch {
            // La firma mantiene la altura de respaldo cuando no puede inspeccionarse.
        }
    };

    signatureFrames.forEach((frame) => {
        frame.addEventListener('load', () => observeSignature(frame));

        try {
            if (frame.contentDocument?.readyState === 'complete') {
                observeSignature(frame);
            }
        } catch {
            // Se medirá mediante el evento load si el documento aún no está disponible.
        }
    });

    let resizeFrame;

    window.addEventListener('resize', () => {
        window.cancelAnimationFrame(resizeFrame);
        resizeFrame = window.requestAnimationFrame(() => {
            signatureFrames.forEach(measureSignature);
        });
    });
});

document.addEventListener('DOMContentLoaded', () => {
    let dragState = null;
    let currentDropTarget = null;
    let busy = false;

    const getOrbatSection = () => {
        return document.querySelector('[data-orbat]');
    };

    const getOrbatContainer = () => {
        return getOrbatSection()?.querySelector('.event-orbat');
    };

    /*
    |--------------------------------------------------------------------------
    | Toast
    |--------------------------------------------------------------------------
    */

    const showOrbatToast = (message, isError = false) => {
        document.querySelector('.event-orbat-toast')?.remove();

        const toast = document.createElement('div');

        toast.className = 'event-orbat-toast';

        if (isError) {
            toast.classList.add('is-error');
        }

        toast.textContent = message;

        document.body.appendChild(toast);

        window.requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        window.setTimeout(() => {
            toast.classList.remove('is-visible');

            window.setTimeout(() => {
                toast.remove();
            }, 200);
        }, 3500);
    };

    /*
    |--------------------------------------------------------------------------
    | Limpiar estado visual del drag
    |--------------------------------------------------------------------------
    */

    const clearDragState = () => {
        document
            .querySelectorAll('.is-drop-target')
            .forEach((element) => {
                element.classList.remove('is-drop-target');
            });

        document
            .querySelectorAll('.is-drag-source')
            .forEach((element) => {
                element.classList.remove('is-drag-source');
            });

        document
            .querySelectorAll('.is-dragging')
            .forEach((element) => {
                element.classList.remove('is-dragging');
            });

        getOrbatContainer()?.classList.remove('is-managing');

        currentDropTarget = null;
        dragState = null;
    };

    /*
    |--------------------------------------------------------------------------
    | Refrescar únicamente el ORBAT
    |--------------------------------------------------------------------------
    |
    | Después de mover/eliminar no hacemos location.reload().
    | Pedimos el HTML actualizado y sustituimos solamente el ORBAT.
    |
    */

    const refreshOrbat = async (highlightSlotKey = null) => {
        const response = await fetch(
            `${window.location.pathname}${window.location.search}`,
            {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }
        );

        if (!response.ok) {
            throw new Error(
                'No se pudo actualizar visualmente el ORBAT.'
            );
        }

        const html = await response.text();

        const parser = new DOMParser();
        const documentUpdated = parser.parseFromString(
            html,
            'text/html'
        );

        const currentOrbat = getOrbatSection();
        const updatedOrbat = documentUpdated.querySelector(
            '[data-orbat]'
        );

        if (!currentOrbat || !updatedOrbat) {
            throw new Error(
                'No se pudo encontrar el ORBAT actualizado.'
            );
        }

        /*
         * Sustituimos solo el contenido.
         *
         * La página NO se recarga.
         */
        currentOrbat.innerHTML = updatedOrbat.innerHTML;

        /*
         * Actualizamos también el historial de movimientos.
         */
        const currentHistory =
            document.querySelector('#movimientos');

        const updatedHistory =
            documentUpdated.querySelector('#movimientos');

        if (currentHistory && updatedHistory) {
            const wasOpen = currentHistory.open;

            currentHistory.innerHTML =
                updatedHistory.innerHTML;

            currentHistory.open = wasOpen;
        }

        /*
         * Pequeño destello sobre el slot modificado.
         */
        if (highlightSlotKey) {
            const newTarget = [
                ...currentOrbat.querySelectorAll(
                    '[data-orbat-slot]'
                ),
            ].find(
                (slot) =>
                    slot.dataset.slotKey ===
                    highlightSlotKey
            );

            if (newTarget) {
                newTarget.classList.add(
                    'is-orbat-updated'
                );

                window.setTimeout(() => {
                    newTarget.classList.remove(
                        'is-orbat-updated'
                    );
                }, 900);
            }
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Extraer errores de Laravel
    |--------------------------------------------------------------------------
    */

    const getResponseError = async (response) => {
        try {
            const data = await response.json();

            if (data.errors) {
                const firstErrors =
                    Object.values(data.errors);

                if (
                    firstErrors.length > 0
                    && firstErrors[0].length > 0
                ) {
                    return firstErrors[0][0];
                }
            }

            return data.message
                ?? 'No se pudo modificar el ORBAT.';
        } catch {
            return 'No se pudo modificar el ORBAT.';
        }
    };

    /*
    |--------------------------------------------------------------------------
    | PATCH al backend
    |--------------------------------------------------------------------------
    */

    const sendOrbatAction = async (
        slot,
        payload,
        highlightSlotKey = null
    ) => {
        if (busy) {
            return false;
        }

        const orbatSection = getOrbatSection();

        if (!orbatSection) {
            return false;
        }

        const url = slot.dataset.manageUrl;

        const csrfToken =
            orbatSection.dataset.csrfToken;

        if (!url || !csrfToken) {
            showOrbatToast(
                'Falta la configuración necesaria para gestionar el ORBAT.',
                true
            );

            return false;
        }

        busy = true;

        orbatSection.classList.add(
            'is-orbat-busy'
        );

        try {
            const response = await fetch(url, {
                method: 'PATCH',

                credentials: 'same-origin',

                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },

                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error(
                    await getResponseError(response)
                );
            }

            const data =
                await response.json();

            await refreshOrbat(
                highlightSlotKey
            );

            showOrbatToast(
                data.message
                ?? 'ORBAT actualizado correctamente.'
            );

            return true;

        } catch (error) {
            console.error(error);

            showOrbatToast(
                error.message
                ?? 'No se pudo modificar el ORBAT.',
                true
            );

            return false;

        } finally {
            getOrbatSection()
                ?.classList
                .remove(
                    'is-orbat-busy'
                );

            busy = false;
        }
    };

    /*
    |--------------------------------------------------------------------------
    | ASIGNACIÓN MANUAL
    |--------------------------------------------------------------------------
    */

    const getAssignModal = () => {
        return document.querySelector(
            '[data-orbat-assign-modal]'
        );
    };


    /*
    |--------------------------------------------------------------------------
    | Normalizar búsqueda
    |--------------------------------------------------------------------------
    */

    const normalizeAssigneeSearch = (
        value
    ) => {
        return String(value ?? '')
            .normalize('NFD')
            .replace(
                /[\u0300-\u036f]/g,
                ''
            )
            .toLowerCase()
            .trim();
    };


    /*
    |--------------------------------------------------------------------------
    | Resetear búsqueda
    |--------------------------------------------------------------------------
    */

    const resetAssignSearch = (
        modal
    ) => {
        const search =
            modal.querySelector(
                '[data-orbat-assignee-search]'
            );

        if (search) {
            search.value = '';
        }

        modal
            .querySelectorAll(
                '[data-orbat-assignee]'
            )
            .forEach((option) => {
                option.hidden = false;
            });

        modal
            .querySelectorAll(
                '[data-orbat-assignee-group]'
            )
            .forEach((group) => {
                group.hidden = false;
            });

        const empty =
            modal.querySelector(
                '[data-orbat-assignee-empty]'
            );

        if (empty) {
            empty.hidden = true;
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Abrir modal de asignación
    |--------------------------------------------------------------------------
    */

    const openAssignModal = (
        button
    ) => {
        const modal =
            getAssignModal();

        const slot =
            button.closest(
                '[data-orbat-slot]'
            );

        if (
            !modal
            || !slot
            || busy
        ) {
            return;
        }

        const slotKey =
            slot.dataset.slotKey;

        if (!slotKey) {
            return;
        }

        /*
        * Guardamos qué slot estamos gestionando.
        */
        modal.dataset.slotKey =
            slotKey;

        const slotName =
            button.dataset.slotName
            || 'Slot';

        const groupName =
            button.dataset.groupName
            || 'Grupo';

        const context =
            modal.querySelector(
                '[data-orbat-assign-context]'
            );

        if (context) {
            context.textContent =
                `${groupName} · ${slotName}`;
        }

        resetAssignSearch(
            modal
        );

        if (
            modal instanceof
            HTMLDialogElement
        ) {
            if (!modal.open) {
                modal.showModal();
            }
        }

        window.requestAnimationFrame(
            () => {
                modal
                    .querySelector(
                        '[data-orbat-assignee-search]'
                    )
                    ?.focus();
            }
        );
    };


    /*
    |--------------------------------------------------------------------------
    | Cerrar modal
    |--------------------------------------------------------------------------
    */

    const closeAssignModal = () => {
        const modal =
            getAssignModal();

        if (!modal) {
            return;
        }

        delete modal.dataset.slotKey;

        modal.classList.remove(
            'is-submitting'
        );

        resetAssignSearch(
            modal
        );

        if (
            modal instanceof
            HTMLDialogElement
            && modal.open
        ) {
            modal.close();
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Localizar nuevamente un slot
    |--------------------------------------------------------------------------
    |
    | No conservamos referencias DOM antiguas porque refreshOrbat()
    | sustituye el HTML completo del ORBAT.
    |
    */

    const findOrbatSlot = (
        slotKey
    ) => {
        if (!slotKey) {
            return null;
        }

        return [
            ...document.querySelectorAll(
                '[data-orbat-slot]'
            ),
        ].find(
            (slot) =>
                slot.dataset.slotKey
                === slotKey
        ) ?? null;
    };

    /*
    |--------------------------------------------------------------------------
    | COMENZAR A ARRASTRAR
    |--------------------------------------------------------------------------
    */

    document.addEventListener('dragstart', (event) => {
        const player = event.target.closest(
            '[data-orbat-player]'
        );

        if (!player) {
            return;
        }

        /*
         * Evita iniciar drag al intentar pulsar la X.
         */
        if (
            event.target.closest(
                '[data-orbat-remove]'
            )
        ) {
            event.preventDefault();

            return;
        }

        const sourceSlot = player.closest(
            '[data-orbat-slot]'
        );

        if (!sourceSlot) {
            event.preventDefault();

            return;
        }

        dragState = {
            userId: player.dataset.userId,
            userName: player.dataset.userName,
            sourceSlotKey:
                sourceSlot.dataset.slotKey,
            sourceSlot,
            player,
        };

        event.dataTransfer.effectAllowed = 'move';

        event.dataTransfer.setData(
            'text/plain',
            dragState.userId
        );

        /*
         * Dejamos que el navegador genere primero
         * la imagen fantasma del jugador.
         */
        window.requestAnimationFrame(() => {
            player.classList.add('is-dragging');

            sourceSlot.classList.add(
                'is-drag-source'
            );

            getOrbatContainer()?.classList.add(
                'is-managing'
            );
        });
    });

    /*
    |--------------------------------------------------------------------------
    | PASAR POR ENCIMA DE UN SLOT
    |--------------------------------------------------------------------------
    */

    document.addEventListener('dragover', (event) => {
        if (!dragState) {
            return;
        }

        const slot = event.target.closest(
            '[data-orbat-slot]'
        );

        /*
         * Estamos fuera del ORBAT.
         */
        if (!slot) {
            currentDropTarget?.classList.remove(
                'is-drop-target'
            );

            currentDropTarget = null;

            return;
        }

        /*
         * No marcamos como destino el propio slot.
         */
        if (
            slot.dataset.slotKey
            === dragState.sourceSlotKey
        ) {
            currentDropTarget?.classList.remove(
                'is-drop-target'
            );

            currentDropTarget = null;

            return;
        }

        event.preventDefault();

        event.dataTransfer.dropEffect = 'move';

        if (currentDropTarget !== slot) {
            currentDropTarget?.classList.remove(
                'is-drop-target'
            );

            currentDropTarget = slot;

            currentDropTarget.classList.add(
                'is-drop-target'
            );
        }
    });

    /*
    |--------------------------------------------------------------------------
    | SOLTAR JUGADOR
    |--------------------------------------------------------------------------
    */

    document.addEventListener('drop', async (event) => {
        if (!dragState) {
            return;
        }

        const targetSlot = event.target.closest(
            '[data-orbat-slot]'
        );

        if (!targetSlot) {
            clearDragState();

            return;
        }

        event.preventDefault();

        /*
         * Soltar sobre su propio slot.
         */
        if (
            targetSlot.dataset.slotKey
            === dragState.sourceSlotKey
        ) {
            clearDragState();

            return;
        }

        const draggedUserId = dragState.userId;
        const draggedUserName =
            dragState.userName;

        const targetSlotKey =
            targetSlot.dataset.slotKey;

        const targetUserId =
            targetSlot.dataset.occupantUserId
                ?.trim();

        const targetName =
            targetSlot.dataset.occupantName
                ?.trim();

        const targetIsOccupied =
            targetSlot.classList.contains(
                'is-occupied'
            );

        /*
         * Slot ocupado por aliado externo.
         *
         * No hacemos intercambio usuario ↔ aliado.
         */
        if (
            targetIsOccupied
            && !targetUserId
        ) {
            clearDragState();

            showOrbatToast(
                'Ese slot está ocupado por un aliado externo. Elimínalo primero con la X.',
                true
            );

            return;
        }

        /*
         * Destino ocupado por otro usuario:
         * pedimos confirmación antes del intercambio.
         */
        if (
            targetUserId
            && String(targetUserId)
                !== String(draggedUserId)
        ) {
            const confirmed = window.confirm(
                `¿Está seguro de intercambiar a ${draggedUserName} con ${targetName}?`
            );

            if (!confirmed) {
                clearDragState();

                return;
            }
        }

        /*
         * Guardamos referencias antes de limpiar
         * el estado visual.
         */
        const requestSlot = targetSlot;

        clearDragState();

        await sendOrbatAction(
            requestSlot,
            {
                action: 'move',
                user_id: draggedUserId,
            },
            targetSlotKey
        );
    });

    /*
    |--------------------------------------------------------------------------
    | FINAL DEL DRAG
    |--------------------------------------------------------------------------
    */

    document.addEventListener('dragend', () => {
        clearDragState();
    });

    /*
    |--------------------------------------------------------------------------
    | ABRIR SELECTOR DE ASIGNACIÓN
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        (event) => {
            const assignButton =
                event.target.closest(
                    '[data-orbat-assign]'
                );

            if (!assignButton) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            openAssignModal(
                assignButton
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | CERRAR SELECTOR
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        (event) => {
            const closeButton =
                event.target.closest(
                    '[data-orbat-assign-close]'
                );

            if (!closeButton) {
                return;
            }

            event.preventDefault();

            if (busy) {
                return;
            }

            closeAssignModal();
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Cerrar pulsando fuera del panel
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        (event) => {
            const modal =
                event.target.closest(
                    '[data-orbat-assign-modal]'
                );

            if (!modal) {
                return;
            }

            /*
            * En un <dialog>, el clic sobre
            * el fondo tiene como target el propio dialog.
            */

            if (
                event.target === modal
                && !busy
            ) {
                closeAssignModal();
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | BUSCADOR
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'input',
        (event) => {
            const search =
                event.target.closest(
                    '[data-orbat-assignee-search]'
                );

            if (!search) {
                return;
            }

            const modal =
                search.closest(
                    '[data-orbat-assign-modal]'
                );

            if (!modal) {
                return;
            }

            const term =
                normalizeAssigneeSearch(
                    search.value
                );

            let totalVisible = 0;

            modal
                .querySelectorAll(
                    '[data-orbat-assignee]'
                )
                .forEach((option) => {

                    const name =
                        normalizeAssigneeSearch(
                            option.dataset
                                .assigneeName
                        );

                    const matches =
                        term === ''
                        || name.includes(term);

                    option.hidden =
                        !matches;

                    if (matches) {
                        totalVisible++;
                    }
                });


            /*
            * Ocultar completamente una categoría
            * si no contiene resultados.
            */

            modal
                .querySelectorAll(
                    '[data-orbat-assignee-group]'
                )
                .forEach((group) => {

                    const hasVisible =
                        [
                            ...group.querySelectorAll(
                                '[data-orbat-assignee]'
                            ),
                        ].some(
                            (option) =>
                                !option.hidden
                        );

                    group.hidden =
                        !hasVisible;
                });


            /*
            * Mensaje sin resultados.
            */

            const empty =
                modal.querySelector(
                    '[data-orbat-assignee-empty]'
                );

            if (empty) {
                empty.hidden =
                    totalVisible > 0;
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | SELECCIONAR MIEMBRO / ALIADO
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        async (event) => {
            const option =
                event.target.closest(
                    '[data-orbat-assignee]'
                );

            if (!option) {
                return;
            }

            event.preventDefault();

            if (busy) {
                return;
            }

            const modal =
                option.closest(
                    '[data-orbat-assign-modal]'
                );

            if (!modal) {
                return;
            }

            const slotKey =
                modal.dataset.slotKey;

            const slot =
                findOrbatSlot(
                    slotKey
                );

            if (!slot) {
                showOrbatToast(
                    'No se pudo localizar el slot seleccionado.',
                    true
                );

                closeAssignModal();

                return;
            }

            const assigneeType =
                option.dataset.assigneeType;

            const assigneeId =
                option.dataset.assigneeId;

            const assigneeName =
                option.dataset.assigneeName
                || 'Jugador';

            if (
                !assigneeType
                || !assigneeId
            ) {
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Estado visual
            |--------------------------------------------------------------------------
            */

            modal.classList.add(
                'is-submitting'
            );


            /*
            |--------------------------------------------------------------------------
            | Enviar
            |--------------------------------------------------------------------------
            */

            const success =
                await sendOrbatAction(
                    slot,
                    {
                        action:
                            'assign',

                        assignee_type:
                            assigneeType,

                        assignee_id:
                            assigneeId,
                    },
                    slotKey
                );


            /*
            * refreshOrbat() sustituye el HTML del ORBAT.
            *
            * Por tanto, si salió bien, el modal antiguo
            * incluso puede haber desaparecido ya del DOM.
            */

            if (success) {
                closeAssignModal();

                return;
            }


            /*
            * Si falló, permitimos volver a intentarlo.
            */

            getAssignModal()
                ?.classList
                .remove(
                    'is-submitting'
                );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR CON X
    |--------------------------------------------------------------------------
    */

    document.addEventListener('click', async (event) => {
        const removeButton = event.target.closest(
            '[data-orbat-remove]'
        );

        if (!removeButton) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (busy) {
            return;
        }

        const slot = removeButton.closest(
            '[data-orbat-slot]'
        );

        if (!slot) {
            return;
        }

        const userName =
            removeButton.dataset.userName
            || slot.dataset.occupantName
            || 'este jugador';

        /*
         * Confirmación destructiva.
         */
        const confirmed = window.confirm(
            `¿Está seguro de eliminar a ${userName} del ORBAT?`
        );

        if (!confirmed) {
            return;
        }

        await sendOrbatAction(
            slot,
            {
                action: 'clear',
            },
            slot.dataset.slotKey
        );
    });
});
/*
|--------------------------------------------------------------------------
| INDICADOR EN DIRECTO DEL EVENTO
|--------------------------------------------------------------------------
|
| Comprueba periódicamente /directos/estado.
|
| Si existe al menos un stream activo asociado
| al evento actual:
|
|     ● EN DIRECTO
|
| aparece automáticamente.
|
| Si termina el último directo del evento,
| desaparece sin recargar la página.
|
*/

document.addEventListener(
    'DOMContentLoaded',
    () => {

        const liveIndicator =
            document.querySelector(
                '[data-event-live]'
            );

        /*
         * Esta página no tiene indicador
         * de evento en directo.
         */
        if (!liveIndicator) {
            return;
        }


        const eventId =
            String(
                liveIndicator.dataset.eventId
                ?? ''
            );

        const statusUrl =
            liveIndicator.dataset
                .streamStatusUrl;


        if (
            !eventId
            || !statusUrl
        ) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Evitar peticiones simultáneas
        |--------------------------------------------------------------------------
        */

        let checking = false;


        /*
        |--------------------------------------------------------------------------
        | Mostrar / ocultar indicador
        |--------------------------------------------------------------------------
        */

        const setLiveState = (
            isLive
        ) => {

            const wasLive =
                !liveIndicator.hidden;

            /*
             * No hacemos nada si
             * el estado no ha cambiado.
             */
            if (wasLive === isLive) {
                return;
            }

            liveIndicator.hidden =
                !isLive;


            /*
             * Pequeña clase temporal cuando
             * comienza el directo.
             *
             * Luego podremos usarla para una
             * animación de entrada si queremos.
             */
            if (isLive) {

                liveIndicator.classList.add(
                    'is-appearing'
                );

                window.setTimeout(
                    () => {
                        liveIndicator
                            .classList
                            .remove(
                                'is-appearing'
                            );
                    },
                    600
                );
            }
        };


        /*
        |--------------------------------------------------------------------------
        | Consultar estado
        |--------------------------------------------------------------------------
        */

        const checkEventLiveStatus =
            async () => {

                /*
                 * No hacemos varias consultas
                 * simultáneas.
                 */
                if (checking) {
                    return;
                }


                /*
                 * Si el usuario tiene la pestaña
                 * en segundo plano, no hacemos
                 * peticiones innecesarias.
                 */
                if (document.hidden) {
                    return;
                }


                checking = true;


                try {

                    const response =
                        await fetch(
                            statusUrl,
                            {
                                method: 'GET',

                                headers: {
                                    Accept:
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                cache:
                                    'no-store',

                                credentials:
                                    'same-origin',
                            }
                        );


                    if (!response.ok) {
                        return;
                    }


                    const data =
                        await response.json();


                    const streams =
                        Array.isArray(
                            data.streams
                        )
                            ? data.streams
                            : [];


                    /*
                    |--------------------------------------------------------------------------
                    | ¿Hay un directo de ESTE evento?
                    |--------------------------------------------------------------------------
                    */

                    const isLive =
                        streams.some(
                            (stream) =>
                                String(
                                    stream.event_id
                                    ?? ''
                                )
                                === eventId
                        );


                    setLiveState(
                        isLive
                    );

                } catch (error) {

                    /*
                     * Si hay un fallo puntual de red,
                     * mantenemos el estado actual.
                     *
                     * No ocultamos EN DIRECTO por
                     * un timeout o una petición fallida.
                     */

                    console.error(
                        'No se pudo comprobar el estado del directo:',
                        error
                    );

                } finally {

                    checking = false;
                }
            };


        /*
        |--------------------------------------------------------------------------
        | Primera comprobación
        |--------------------------------------------------------------------------
        |
        | No esperamos 10 segundos al cargar.
        |
        */

        checkEventLiveStatus();


        /*
        |--------------------------------------------------------------------------
        | Polling
        |--------------------------------------------------------------------------
        */

        const liveStatusInterval =
            window.setInterval(
                checkEventLiveStatus,
                10000
            );

        /*
        |--------------------------------------------------------------------------
        | Al volver a la pestaña
        |--------------------------------------------------------------------------
        |
        | Mientras estaba oculta no consultamos.
        | En cuanto el usuario vuelve, actualizamos
        | inmediatamente.
        |
        */

        document.addEventListener(
            'visibilitychange',
            () => {

                if (!document.hidden) {
                    checkEventLiveStatus();
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Limpieza
        |--------------------------------------------------------------------------
        */

        window.addEventListener(
            'pagehide',
            () => {
                window.clearInterval(
                    liveStatusInterval
                );
            }
        );
    }
);

/*
|--------------------------------------------------------------------------
| MULTIMEDIA DE EVENTOS
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    () => {

        /*
        |--------------------------------------------------------------------------
        | FORMULARIO AÑADIR MULTIMEDIA
        |--------------------------------------------------------------------------
        */

        const mediaFormContainer =
            document.querySelector(
                '[data-event-media-form]'
            );

        const mediaFormToggle =
            document.querySelector(
                '[data-event-media-form-toggle]'
            );

        const mediaFormCancel =
            document.querySelector(
                '[data-event-media-form-cancel]'
            );


        /*
        |--------------------------------------------------------------------------
        | Abrir / cerrar formulario
        |--------------------------------------------------------------------------
        */

        const setMediaFormOpen = (
            open
        ) => {

            if (!mediaFormContainer) {
                return;
            }


            mediaFormContainer.hidden =
                !open;


            if (mediaFormToggle) {

                mediaFormToggle.setAttribute(
                    'aria-expanded',
                    open
                        ? 'true'
                        : 'false'
                );
            }


            /*
             * Focus al primer campo cuando
             * se abre manualmente.
             */

            if (open) {

                window.requestAnimationFrame(
                    () => {

                        mediaFormContainer
                            .querySelector(
                                'select, input'
                            )
                            ?.focus();
                    }
                );
            }
        };


        /*
        |--------------------------------------------------------------------------
        | Botón + Añadir contenido
        |--------------------------------------------------------------------------
        */

        mediaFormToggle
            ?.addEventListener(
                'click',
                () => {

                    const isCurrentlyOpen =
                        !mediaFormContainer
                            ?.hidden;

                    setMediaFormOpen(
                        !isCurrentlyOpen
                    );
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Cancelar
        |--------------------------------------------------------------------------
        */

        mediaFormCancel
            ?.addEventListener(
                'click',
                () => {

                    setMediaFormOpen(
                        false
                    );
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Evitar doble envío
        |--------------------------------------------------------------------------
        */

        const mediaForm =
            mediaFormContainer
                ?.querySelector(
                    'form'
                );


        mediaForm
            ?.addEventListener(
                'submit',
                () => {

                    const submitButton =
                        mediaForm.querySelector(
                            'button[type="submit"]'
                        );

                    if (!submitButton) {
                        return;
                    }


                    submitButton.disabled =
                        true;

                    submitButton.dataset
                        .originalText =
                        submitButton.textContent;

                    submitButton.textContent =
                        'Publicando...';
                }
            );


        /*
        |--------------------------------------------------------------------------
        | CARRUSELES
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '[data-event-media-carousel]'
            )
            .forEach(
                (carousel) => {

                    const track =
                        carousel.querySelector(
                            '[data-event-media-track]'
                        );

                    const slides =
                        [
                            ...carousel
                                .querySelectorAll(
                                    '[data-event-media-slide]'
                                ),
                        ];


                    if (
                        !track
                        || slides.length === 0
                    ) {
                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Controles
                    |--------------------------------------------------------------------------
                    */

                    const clipsSection =
                        carousel.closest(
                            '.event-media__clips'
                        );

                    const prevButton =
                        clipsSection
                            ?.querySelector(
                                '[data-event-media-prev]'
                            );

                    const nextButton =
                        clipsSection
                            ?.querySelector(
                                '[data-event-media-next]'
                            );

                    const counter =
                        clipsSection
                            ?.querySelector(
                                '[data-event-media-counter]'
                            );


                    /*
                    |--------------------------------------------------------------------------
                    | Estado inicial
                    |--------------------------------------------------------------------------
                    */

                    let activeIndex =
                        Math.max(
                            0,
                            slides.findIndex(
                                (slide) =>
                                    slide.classList
                                        .contains(
                                            'is-active'
                                        )
                            )
                        );


                    /*
                     * Permitimos usar flechas del
                     * teclado cuando el carrusel
                     * tiene foco.
                     */

                    carousel.tabIndex =
                        0;


                    /*
                    |--------------------------------------------------------------------------
                    | Posicionar carrusel
                    |--------------------------------------------------------------------------
                    */

                    const positionCarousel =
                        (
                            animate = true
                        ) => {

                            const activeSlide =
                                slides[
                                    activeIndex
                                ];

                            if (!activeSlide) {
                                return;
                            }


                            /*
                             * Posición necesaria para que
                             * el centro del slide coincida
                             * con el centro del carrusel.
                             */

                            const desiredPosition =
                                activeSlide.offsetLeft
                                - (
                                    (
                                        carousel
                                            .clientWidth
                                        - activeSlide
                                            .offsetWidth
                                    )
                                    / 2
                                );


                            /*
                             * Evitamos salirnos físicamente
                             * de los extremos del track.
                             */

                            const maxPosition =
                                Math.max(
                                    0,
                                    track.scrollWidth
                                    - carousel
                                        .clientWidth
                                );


                            const position =
                                Math.min(
                                    Math.max(
                                        desiredPosition,
                                        0
                                    ),
                                    maxPosition
                                );


                            if (!animate) {

                                track.style
                                    .transition =
                                    'none';
                            }


                            track.style.transform =
                                `translate3d(${-position}px, 0, 0)`;


                            if (!animate) {

                                window
                                    .requestAnimationFrame(
                                        () => {
                                            track.style
                                                .transition =
                                                '';
                                        }
                                    );
                            }
                        };


                    /*
                    |--------------------------------------------------------------------------
                    | Actualizar estado visual
                    |--------------------------------------------------------------------------
                    */

                    const updateCarousel =
                        (
                            animate = true
                        ) => {

                            slides.forEach(
                                (
                                    slide,
                                    index
                                ) => {

                                    const isActive =
                                        index
                                        === activeIndex;


                                    slide.classList
                                        .toggle(
                                            'is-active',
                                            isActive
                                        );


                                    slide.setAttribute(
                                        'aria-hidden',
                                        isActive
                                            ? 'false'
                                            : 'true'
                                    );
                                }
                            );


                            /*
                             * Contador
                             */

                            if (counter) {

                                counter.textContent =
                                    `${activeIndex + 1} / ${slides.length}`;
                            }


                            /*
                             * En un carrusel circular no
                             * necesitamos deshabilitar.
                             */

                            positionCarousel(
                                animate
                            );
                        };


                    /*
                    |--------------------------------------------------------------------------
                    | Ir a un índice
                    |--------------------------------------------------------------------------
                    */

                    const goToSlide =
                        (
                            index,
                            animate = true
                        ) => {

                            if (
                                slides.length
                                <= 1
                            ) {
                                return;
                            }


                            /*
                             * Carrusel circular:
                             *
                             * anterior desde 0
                             * → último.
                             *
                             * siguiente desde último
                             * → primero.
                             */

                            if (index < 0) {

                                activeIndex =
                                    slides.length
                                    - 1;
                            }

                            else if (
                                index
                                >= slides.length
                            ) {

                                activeIndex =
                                    0;
                            }

                            else {

                                activeIndex =
                                    index;
                            }


                            updateCarousel(
                                animate
                            );
                        };


                    /*
                    |--------------------------------------------------------------------------
                    | Flecha izquierda
                    |--------------------------------------------------------------------------
                    */

                    prevButton
                        ?.addEventListener(
                            'click',
                            () => {

                                goToSlide(
                                    activeIndex
                                    - 1
                                );
                            }
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Flecha derecha
                    |--------------------------------------------------------------------------
                    */

                    nextButton
                        ?.addEventListener(
                            'click',
                            () => {

                                goToSlide(
                                    activeIndex
                                    + 1
                                );
                            }
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Pulsar sobre un clip lateral
                    |--------------------------------------------------------------------------
                    */

                    slides.forEach(
                        (
                            slide,
                            index
                        ) => {

                            slide.addEventListener(
                                'click',
                                () => {

                                    if (
                                        index
                                        === activeIndex
                                    ) {
                                        return;
                                    }


                                    goToSlide(
                                        index
                                    );
                                }
                            );
                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Teclado
                    |--------------------------------------------------------------------------
                    */

                    carousel.addEventListener(
                        'keydown',
                        (event) => {

                            if (
                                event.key
                                === 'ArrowLeft'
                            ) {

                                event.preventDefault();

                                goToSlide(
                                    activeIndex
                                    - 1
                                );
                            }


                            if (
                                event.key
                                === 'ArrowRight'
                            ) {

                                event.preventDefault();

                                goToSlide(
                                    activeIndex
                                    + 1
                                );
                            }
                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Swipe móvil
                    |--------------------------------------------------------------------------
                    */

                    let touchStartX =
                        null;


                    carousel.addEventListener(
                        'touchstart',
                        (event) => {

                            touchStartX =
                                event.touches[
                                    0
                                ]?.clientX
                                ?? null;
                        },
                        {
                            passive:
                                true,
                        }
                    );


                    carousel.addEventListener(
                        'touchend',
                        (event) => {

                            if (
                                touchStartX
                                === null
                            ) {
                                return;
                            }


                            const touchEndX =
                                event.changedTouches[
                                    0
                                ]?.clientX
                                ?? touchStartX;


                            const distance =
                                touchEndX
                                - touchStartX;


                            touchStartX =
                                null;


                            /*
                             * Evitar movimientos
                             * accidentales pequeños.
                             */

                            if (
                                Math.abs(
                                    distance
                                )
                                < 45
                            ) {
                                return;
                            }


                            if (
                                distance > 0
                            ) {

                                goToSlide(
                                    activeIndex
                                    - 1
                                );
                            }

                            else {

                                goToSlide(
                                    activeIndex
                                    + 1
                                );
                            }
                        },
                        {
                            passive:
                                true,
                        }
                    );


                    /*
                    |--------------------------------------------------------------------------
                    | Recalcular al cambiar tamaño
                    |--------------------------------------------------------------------------
                    */

                    let resizeTimeout =
                        null;


                    const handleResize =
                        () => {

                            window.clearTimeout(
                                resizeTimeout
                            );


                            resizeTimeout =
                                window.setTimeout(
                                    () => {

                                        positionCarousel(
                                            false
                                        );
                                    },
                                    100
                                );
                        };


                    if (
                        typeof ResizeObserver
                        !== 'undefined'
                    ) {

                        const observer =
                            new ResizeObserver(
                                handleResize
                            );


                        observer.observe(
                            carousel
                        );
                    }

                    else {

                        window.addEventListener(
                            'resize',
                            handleResize
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Primera posición
                    |--------------------------------------------------------------------------
                    */

                    updateCarousel(
                        false
                    );


                    /*
                     * Los iframes pueden alterar ligeramente
                     * las dimensiones cuando terminan
                     * de cargarse.
                     */

                    window.addEventListener(
                        'load',
                        () => {

                            positionCarousel(
                                false
                            );
                        },
                        {
                            once:
                                true,
                        }
                    );
                }
            );
    }
);
document.addEventListener('DOMContentLoaded', () => {
    const rouletteWatch = document.querySelector('[data-event-roulette-watch]');
    const rouletteLock = document.querySelector('[data-event-roulette-lock]');

    if (!rouletteWatch) {
        return;
    }

    const lockStateUrl = rouletteWatch.dataset.rouletteLockStateUrl;
    const initiallyLocked = Boolean(rouletteLock);
    let knownLocked = initiallyLocked;
    let checking = false;

    if (rouletteLock) {
        const close = () => {
            rouletteLock.classList.add('is-dismissed');
            window.setTimeout(() => rouletteLock.remove(), 180);
        };

        rouletteLock.querySelectorAll('[data-event-roulette-lock-close]').forEach((button) => {
            button.addEventListener('click', close);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && document.body.contains(rouletteLock)) {
                close();
            }
        });
    }

    if (!lockStateUrl) {
        return;
    }

    window.setInterval(async () => {
        if (checking || document.visibilityState === 'hidden') {
            return;
        }

        checking = true;
        try {
            const response = await fetch(lockStateUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const state = await response.json();
            const lockedNow = state.locked === true;

            if (lockedNow !== knownLocked) {
                // Si la sala acaba de crearse, recargamos para mostrar el aviso
                // y retirar botones del ORBAT. Si acaba de terminar, recargamos
                // para recuperar las inscripciones sin esperar al usuario.
                window.location.reload();
                return;
            }

            knownLocked = lockedNow;
        } catch {
            // El siguiente sondeo volverá a comprobar el bloqueo.
        } finally {
            checking = false;
        }
    }, 4000);
});
