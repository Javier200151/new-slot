document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.querySelector(
        '[data-nav-toggle]'
    );

    const navMenu = document.querySelector(
        '[data-nav-menu]'
    );


    /*
    |--------------------------------------------------------------------------
    | Navegación
    |--------------------------------------------------------------------------
    */

    function closeNavigation() {
        if (!navToggle || !navMenu) {
            return;
        }

        navToggle.setAttribute(
            'aria-expanded',
            'false'
        );

        navToggle.setAttribute(
            'aria-label',
            'Mostrar menú'
        );

        navMenu.classList.remove(
            'is-open'
        );
    }


    if (navToggle && navMenu) {

        navToggle.addEventListener(
            'click',
            () => {
                const isOpen =
                    navToggle.getAttribute(
                        'aria-expanded'
                    ) === 'true';

                navToggle.setAttribute(
                    'aria-expanded',
                    String(!isOpen)
                );

                navToggle.setAttribute(
                    'aria-label',
                    isOpen
                        ? 'Mostrar menú'
                        : 'Ocultar menú'
                );

                navMenu.classList.toggle(
                    'is-open',
                    !isOpen
                );
            }
        );


        navMenu
            .querySelectorAll('a')
            .forEach((link) => {
                link.addEventListener(
                    'click',
                    closeNavigation
                );
            });


        document.addEventListener(
            'click',
            (event) => {
                if (
                    !event.target.closest(
                        '.nav-wrapper'
                    )
                ) {
                    closeNavigation();
                }
            }
        );


        document.addEventListener(
            'keydown',
            (event) => {
                if (
                    event.key === 'Escape'
                    && navMenu.classList.contains(
                        'is-open'
                    )
                ) {
                    closeNavigation();

                    navToggle.focus();
                }
            }
        );


        window
            .matchMedia(
                '(min-width: 1001px)'
            )
            .addEventListener(
                'change',
                closeNavigation
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Modales de autenticación
    |--------------------------------------------------------------------------
    */

    let activeModal = null;


    function openModal(modalId) {
        const modal =
            document.getElementById(
                modalId
            );

        if (!modal) {
            console.error(
                `No existe el modal: ${modalId}`
            );

            return;
        }


        if (
            activeModal
            && activeModal !== modal
        ) {
            activeModal.classList.remove(
                'is-open'
            );

            activeModal.hidden = true;
        }


        modal.hidden = false;


        requestAnimationFrame(() => {
            modal.classList.add(
                'is-open'
            );
        });


        document.body.classList.add(
            'modal-open'
        );

        activeModal = modal;


        window.setTimeout(() => {
            const firstInput =
                modal.querySelector(
                    'input:not([type="hidden"])'
                );

            firstInput?.focus();
        }, 200);
    }


    function closeModal(modal) {
        if (!modal) {
            return;
        }


        modal.classList.remove(
            'is-open'
        );


        window.setTimeout(() => {
            modal.hidden = true;
        }, 180);


        document.body.classList.remove(
            'modal-open'
        );


        if (activeModal === modal) {
            activeModal = null;
        }
    }


    document
        .querySelectorAll(
            '[data-open-modal]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                (event) => {
                    event.preventDefault();

                    openModal(
                        button.dataset
                            .openModal
                    );
                }
            );
        });


    document
        .querySelectorAll(
            '[data-close-modal]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    closeModal(
                        button.closest(
                            '.auth-modal'
                        )
                    );
                }
            );
        });


    document
        .querySelectorAll(
            '[data-switch-modal]'
        )
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    const currentModal =
                        button.closest(
                            '.auth-modal'
                        );

                    closeModal(
                        currentModal
                    );


                    window.setTimeout(
                        () => {
                            openModal(
                                button.dataset
                                    .switchModal
                            );
                        },
                        190
                    );
                }
            );
        });


    document.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key === 'Escape'
                && activeModal
            ) {
                closeModal(
                    activeModal
                );
            }
        }
    );


    const urlParameters =
        new URLSearchParams(
            window.location.search
        );


    const initialModal =
        document.body.dataset
            .openAuthModal
        || urlParameters.get(
            'modal'
        );


    if (
        initialModal === 'login'
    ) {
        openModal(
            'login-modal'
        );
    }


    if (
        initialModal === 'register'
    ) {
        openModal(
            'register-modal'
        );
    }


    if (
        initialModal
        === 'forgot-password'
    ) {
        openModal(
            'forgot-password-modal'
        );
    }
});


/*
|--------------------------------------------------------------------------
| Centro de notificaciones
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    () => {

        const centers =
            document.querySelectorAll(
                '[data-notification-center]'
            );


        if (centers.length === 0) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Abrir / cerrar campana
        |--------------------------------------------------------------------------
        */

        const closeAllNotifications = (
            except = null
        ) => {

            centers.forEach(
                (center) => {

                    if (
                        center === except
                    ) {
                        return;
                    }


                    const button =
                        center.querySelector(
                            '[data-notification-toggle]'
                        );


                    const panel =
                        center.querySelector(
                            '[data-notification-panel]'
                        );


                    if (
                        !button
                        || !panel
                    ) {
                        return;
                    }


                    button.setAttribute(
                        'aria-expanded',
                        'false'
                    );


                    panel.hidden = true;
                }
            );
        };


        centers.forEach(
            (center) => {

                const button =
                    center.querySelector(
                        '[data-notification-toggle]'
                    );


                const panel =
                    center.querySelector(
                        '[data-notification-panel]'
                    );


                if (
                    !button
                    || !panel
                ) {
                    return;
                }


                button.addEventListener(
                    'click',
                    (event) => {

                        event.stopPropagation();


                        const willOpen =
                            panel.hidden;


                        closeAllNotifications(
                            center
                        );


                        panel.hidden =
                            !willOpen;


                        button.setAttribute(
                            'aria-expanded',
                            String(willOpen)
                        );
                    }
                );
            }
        );


        document.addEventListener(
            'click',
            (event) => {

                if (
                    event.target.closest(
                        '[data-notification-center]'
                    )
                ) {
                    return;
                }


                closeAllNotifications();
            }
        );


        document.addEventListener(
            'keydown',
            (event) => {

                if (
                    event.key
                    !== 'Escape'
                ) {
                    return;
                }


                closeAllNotifications();
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Polling automático
        |--------------------------------------------------------------------------
        |
        | Consultamos cada 3 segundos si han cambiado
        | las notificaciones del usuario.
        |
        | Si no hay cambios:
        |     No modificamos nada.
        |
        | Si hay cambios:
        |     Actualizamos la campana y el panel.
        |
        */

        const pollingCenter =
            centers[0];


        const notificationPollUrl =
            pollingCenter.dataset
                .notificationPollUrl;


        let notificationSignature =
            pollingCenter.dataset
                .notificationSignature
            ?? '';


        let notificationPolling =
            false;


        /*
        |--------------------------------------------------------------------------
        | Actualizar HTML de las notificaciones
        |--------------------------------------------------------------------------
        */

        const updateNotificationCenter = (
            html,
            signature
        ) => {

            const template =
                document.createElement(
                    'template'
                );


            template.innerHTML =
                html.trim();


            const freshCenter =
                template.content.querySelector(
                    '[data-notification-center]'
                );


            if (!freshCenter) {
                return;
            }


            centers.forEach(
                (center) => {

                    const currentButton =
                        center.querySelector(
                            '[data-notification-toggle]'
                        );


                    const currentPanel =
                        center.querySelector(
                            '[data-notification-panel]'
                        );


                    const freshButton =
                        freshCenter.querySelector(
                            '[data-notification-toggle]'
                        );


                    const freshPanel =
                        freshCenter.querySelector(
                            '[data-notification-panel]'
                        );


                    /*
                     * Importante:
                     *
                     * No sustituimos el botón completo,
                     * porque perderíamos el listener
                     * que abre la campana.
                     *
                     * Solo sustituimos su contenido.
                     */

                    if (
                        currentButton
                        && freshButton
                    ) {
                        currentButton.innerHTML =
                            freshButton.innerHTML;
                    }


                    /*
                     * Actualizamos el contenido
                     * del desplegable.
                     *
                     * El elemento actual sigue siendo
                     * el mismo, por lo que conserva
                     * si estaba abierto o cerrado.
                     */

                    if (
                        currentPanel
                        && freshPanel
                    ) {
                        currentPanel.innerHTML =
                            freshPanel.innerHTML;
                    }


                    center.dataset
                        .notificationSignature =
                        signature;
                }
            );
        };


        /*
        |--------------------------------------------------------------------------
        | Consultar notificaciones
        |--------------------------------------------------------------------------
        */

        const pollNotifications =
            async () => {

                /*
                 * No hacemos nada si:
                 *
                 * - ya hay una petición en curso
                 * - no existe la URL de polling
                 * - la pestaña está en segundo plano
                 */

                if (
                    notificationPolling
                    || !notificationPollUrl
                    || document.hidden
                ) {
                    return;
                }


                notificationPolling =
                    true;


                try {

                    const url =
                        new URL(
                            notificationPollUrl,
                            window.location.origin
                        );


                    url.searchParams.set(
                        'signature',
                        notificationSignature
                    );


                    const response =
                        await fetch(
                            url.toString(),
                            {
                                method:
                                    'GET',

                                credentials:
                                    'same-origin',

                                headers: {
                                    'Accept':
                                        'application/json',

                                    'X-Requested-With':
                                        'XMLHttpRequest',
                                },

                                cache:
                                    'no-store',
                            }
                        );


                    /*
                     * Si falla la petición
                     * simplemente esperamos al
                     * siguiente intento.
                     */

                    if (!response.ok) {
                        return;
                    }


                    const data =
                        await response.json();


                    /*
                     * Solo recibimos HTML completo
                     * cuando realmente ha cambiado
                     * alguna notificación.
                     */

                    if (
                        data.changed
                        && data.html
                    ) {
                        updateNotificationCenter(
                            data.html,
                            data.signature
                        );
                    }


                    /*
                     * Actualizar siempre la firma.
                     */

                    if (data.signature) {
                        notificationSignature =
                            data.signature;
                    }

                } catch (error) {

                    /*
                     * Un fallo temporal de red
                     * no debe romper la web.
                     */

                    console.debug(
                        'No se pudieron actualizar las notificaciones.',
                        error
                    );

                } finally {

                    notificationPolling =
                        false;
                }
            };


        /*
        |--------------------------------------------------------------------------
        | Primera comprobación
        |--------------------------------------------------------------------------
        |
        | La hacemos inmediatamente por si llegó
        | una notificación justo después de cargar
        | la página.
        |
        */

        pollNotifications();


        /*
        |--------------------------------------------------------------------------
        | Consultamos cada 10 segundos si han cambiado
        | las notificaciones del usuario.
        |--------------------------------------------------------------------------
        */

        const notificationPollInterval =
            window.setInterval(
                pollNotifications,
                10000
            );


        /*
        |--------------------------------------------------------------------------
        | Volver a una pestaña
        |--------------------------------------------------------------------------
        |
        | Mientras la pestaña está oculta no hacemos
        | peticiones.
        |
        | Cuando el usuario vuelve, comprobamos
        | inmediatamente.
        |
        */

        document.addEventListener(
            'visibilitychange',
            () => {

                if (
                    !document.hidden
                ) {
                    pollNotifications();
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
                    notificationPollInterval
                );
            }
        );
    }
);

/*
|--------------------------------------------------------------------------
| Easter egg de cumpleaños
|--------------------------------------------------------------------------
*/
document.addEventListener('DOMContentLoaded', () => {
    const celebration = document.querySelector('[data-birthday-celebration]');

    if (!celebration) {
        return;
    }

    const balloonCount = window.matchMedia('(max-width: 700px)').matches ? 8 : 14;

    for (let index = 0; index < balloonCount; index += 1) {
        const balloon = document.createElement('span');
        balloon.className = 'birthday-balloon';
        balloon.style.left = `${4 + Math.random() * 92}%`;
        balloon.style.setProperty('--duration', `${4.2 + Math.random() * 2.8}s`);
        balloon.style.animationDelay = `${Math.random() * 1.6}s`;
        balloon.style.opacity = `${0.58 + Math.random() * 0.4}`;

        if (index % 3 === 1) {
            balloon.style.background = '#ffffff';
        } else if (index % 3 === 2) {
            balloon.style.background = '#fbbf24';
        }

        celebration.appendChild(balloon);
    }

    window.setTimeout(() => {
        celebration.style.transition = 'opacity 600ms ease';
        celebration.style.opacity = '0';
    }, 6500);

    window.setTimeout(() => celebration.remove(), 7200);
});
