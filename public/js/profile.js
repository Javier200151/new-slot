document.addEventListener('DOMContentLoaded', () => {
    const input = document.querySelector(
        '[data-avatar-input]'
    );

    const editor = document.querySelector(
        '[data-avatar-editor]'
    );

    if (!input || !editor) {
        return;
    }

    const viewport = editor.querySelector(
        '[data-avatar-viewport]'
    );

    const image = editor.querySelector(
        '[data-avatar-image]'
    );

    const zoomInput = editor.querySelector(
        '[data-avatar-zoom]'
    );

    const zoomInButton = editor.querySelector(
        '[data-avatar-zoom-in]'
    );

    const zoomOutButton = editor.querySelector(
        '[data-avatar-zoom-out]'
    );

    const resetButton = editor.querySelector(
        '[data-avatar-reset]'
    );

    const applyButton = editor.querySelector(
        '[data-avatar-apply]'
    );

    const cancelButtons = editor.querySelectorAll(
        '[data-avatar-cancel]'
    );

    const currentAvatar = document.querySelector(
        '.profile-avatar img'
    );

    let sourceFile = null;
    let sourceUrl = null;
    let previewUrl = null;

    let frameSize = 0;

    let baseScale = 1;
    let zoom = 1;

    let offsetX = 0;
    let offsetY = 0;

    let dragging = false;

    let pointerStartX = 0;
    let pointerStartY = 0;

    let offsetStartX = 0;
    let offsetStartY = 0;


    /*
    |--------------------------------------------------------------------------
    | Utilidades
    |--------------------------------------------------------------------------
    */

    const clamp = (
        value,
        minimum,
        maximum
    ) => {
        return Math.min(
            Math.max(value, minimum),
            maximum
        );
    };


    const getDisplayedSize = () => {
        return {
            width:
                image.naturalWidth
                * baseScale
                * zoom,

            height:
                image.naturalHeight
                * baseScale
                * zoom,
        };
    };


    /*
    |--------------------------------------------------------------------------
    | Limitar movimiento
    |--------------------------------------------------------------------------
    |
    | Impide que aparezcan zonas vacías dentro del círculo.
    |
    */

    const clampPosition = () => {
        const size = getDisplayedSize();

        const maxX = Math.max(
            0,
            (size.width - frameSize) / 2
        );

        const maxY = Math.max(
            0,
            (size.height - frameSize) / 2
        );

        offsetX = clamp(
            offsetX,
            -maxX,
            maxX
        );

        offsetY = clamp(
            offsetY,
            -maxY,
            maxY
        );
    };


    /*
    |--------------------------------------------------------------------------
    | Renderizar posición
    |--------------------------------------------------------------------------
    */

    const render = () => {
        if (
            !image.naturalWidth
            || !image.naturalHeight
        ) {
            return;
        }

        clampPosition();

        const size = getDisplayedSize();

        image.style.width =
            `${size.width}px`;

        image.style.height =
            `${size.height}px`;

        image.style.transform =
            `translate(
                calc(-50% + ${offsetX}px),
                calc(-50% + ${offsetY}px)
            )`;
    };


    /*
    |--------------------------------------------------------------------------
    | Restablecer
    |--------------------------------------------------------------------------
    */

    const resetCrop = () => {
        const bounds =
            viewport.getBoundingClientRect();

        frameSize = bounds.width;

        baseScale = Math.max(
            frameSize / image.naturalWidth,
            frameSize / image.naturalHeight
        );

        zoom = 1;

        offsetX = 0;
        offsetY = 0;

        zoomInput.value = '1';

        render();
    };


    /*
    |--------------------------------------------------------------------------
    | Abrir / cerrar
    |--------------------------------------------------------------------------
    */

    const openEditor = () => {
        editor.hidden = false;

        document.body.classList.add(
            'avatar-editor-open'
        );

        window.requestAnimationFrame(
            resetCrop
        );
    };


    const closeEditor = (
        clearSelectedFile = false
    ) => {
        editor.hidden = true;

        document.body.classList.remove(
            'avatar-editor-open'
        );

        dragging = false;

        if (clearSelectedFile) {
            input.value = '';
        }
    };


    /*
    |--------------------------------------------------------------------------
    | Selección de archivo
    |--------------------------------------------------------------------------
    */

    input.addEventListener(
        'change',
        () => {
            const file =
                input.files?.[0];

            if (!file) {
                return;
            }

            if (
                !file.type.startsWith('image/')
            ) {
                input.value = '';

                return;
            }

            sourceFile = file;

            if (sourceUrl) {
                URL.revokeObjectURL(
                    sourceUrl
                );
            }

            sourceUrl =
                URL.createObjectURL(file);

            image.onload = () => {
                openEditor();
            };

            image.src = sourceUrl;
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Arrastrar
    |--------------------------------------------------------------------------
    */

    viewport.addEventListener(
        'pointerdown',
        (event) => {
            if (event.button !== 0) {
                return;
            }

            dragging = true;

            pointerStartX =
                event.clientX;

            pointerStartY =
                event.clientY;

            offsetStartX =
                offsetX;

            offsetStartY =
                offsetY;

            viewport.setPointerCapture(
                event.pointerId
            );
        }
    );


    viewport.addEventListener(
        'pointermove',
        (event) => {
            if (!dragging) {
                return;
            }

            offsetX =
                offsetStartX
                + (
                    event.clientX
                    - pointerStartX
                );

            offsetY =
                offsetStartY
                + (
                    event.clientY
                    - pointerStartY
                );

            render();
        }
    );


    const stopDragging = (
        event
    ) => {
        dragging = false;

        if (
            viewport.hasPointerCapture(
                event.pointerId
            )
        ) {
            viewport.releasePointerCapture(
                event.pointerId
            );
        }
    };


    viewport.addEventListener(
        'pointerup',
        stopDragging
    );

    viewport.addEventListener(
        'pointercancel',
        stopDragging
    );


    /*
    |--------------------------------------------------------------------------
    | Zoom
    |--------------------------------------------------------------------------
    */

    zoomInput.addEventListener(
        'input',
        () => {
            zoom = Number(
                zoomInput.value
            );

            render();
        }
    );


    zoomInButton.addEventListener(
        'click',
        () => {
            zoom = clamp(
                zoom + 0.1,
                1,
                3
            );

            zoomInput.value =
                String(zoom);

            render();
        }
    );


    zoomOutButton.addEventListener(
        'click',
        () => {
            zoom = clamp(
                zoom - 0.1,
                1,
                3
            );

            zoomInput.value =
                String(zoom);

            render();
        }
    );


    resetButton.addEventListener(
        'click',
        resetCrop
    );


    /*
    |--------------------------------------------------------------------------
    | Cancelar
    |--------------------------------------------------------------------------
    */

    cancelButtons.forEach(
        (button) => {
            button.addEventListener(
                'click',
                () => {
                    closeEditor(true);
                }
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Aplicar recorte
    |--------------------------------------------------------------------------
    */

    applyButton.addEventListener(
        'click',
        () => {
            if (
                !sourceFile
                || !frameSize
            ) {
                return;
            }

            /*
             * Generamos siempre un avatar de 600 x 600.
             */
            const outputSize = 600;

            const canvas =
                document.createElement(
                    'canvas'
                );

            canvas.width =
                outputSize;

            canvas.height =
                outputSize;

            const context =
                canvas.getContext('2d');

            if (!context) {
                return;
            }

            const displayedSize =
                getDisplayedSize();

            /*
             * Coordenadas de la imagen respecto
             * al viewport del editor.
             */
            const imageX =
                (
                    frameSize
                    - displayedSize.width
                ) / 2
                + offsetX;

            const imageY =
                (
                    frameSize
                    - displayedSize.height
                ) / 2
                + offsetY;

            const ratio =
                outputSize
                / frameSize;

            context.clearRect(
                0,
                0,
                outputSize,
                outputSize
            );

            /*
             * No recortamos físicamente en círculo.
             *
             * Guardamos un cuadrado, y CSS lo muestra
             * redondo mediante border-radius.
             *
             * Así la imagen sigue siendo reutilizable.
             */
            context.drawImage(
                image,

                imageX * ratio,
                imageY * ratio,

                displayedSize.width
                    * ratio,

                displayedSize.height
                    * ratio
            );

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        return;
                    }

                    const originalName =
                        sourceFile.name
                            .replace(
                                /\.[^.]+$/,
                                ''
                            );

                    const croppedFile =
                        new File(
                            [blob],
                            `${originalName}-avatar.webp`,
                            {
                                type:
                                    'image/webp',

                                lastModified:
                                    Date.now(),
                            }
                        );

                    /*
                     * Sustituimos el archivo original
                     * del input por el resultado recortado.
                     *
                     * Laravel recibirá este archivo.
                     */
                    const transfer =
                        new DataTransfer();

                    transfer.items.add(
                        croppedFile
                    );

                    input.files =
                        transfer.files;


                    /*
                     * Actualizamos visualmente el avatar
                     * sin esperar a guardar el formulario.
                     */
                    if (currentAvatar) {
                        if (previewUrl) {
                            URL.revokeObjectURL(
                                previewUrl
                            );
                        }

                        previewUrl =
                            URL.createObjectURL(
                                blob
                            );

                        currentAvatar.src =
                            previewUrl;
                    }

                    closeEditor(false);
                },

                'image/webp',

                0.92
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Escape
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key === 'Escape'
                && !editor.hidden
            ) {
                closeEditor(true);
            }
        }
    );
});