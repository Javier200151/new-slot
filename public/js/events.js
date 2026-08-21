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
            return;
        }

        const orbatSection = getOrbatSection();

        if (!orbatSection) {
            return;
        }

        const url = slot.dataset.manageUrl;
        const csrfToken =
            orbatSection.dataset.csrfToken;

        if (!url || !csrfToken) {
            showOrbatToast(
                'Falta la configuración necesaria para gestionar el ORBAT.',
                true
            );

            return;
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

            const data = await response.json();

            await refreshOrbat(highlightSlotKey);

            showOrbatToast(
                data.message
                ?? 'ORBAT actualizado correctamente.'
            );
        } catch (error) {
            console.error(error);

            showOrbatToast(
                error.message
                ?? 'No se pudo modificar el ORBAT.',
                true
            );
        } finally {
            getOrbatSection()?.classList.remove(
                'is-orbat-busy'
            );

            busy = false;
        }
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
