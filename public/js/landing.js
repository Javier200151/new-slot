document.addEventListener('DOMContentLoaded', () => {
    let activeModal = null;

    function openModal(modalId) {
        const modal = document.getElementById(modalId);

        if (!modal) {
            console.error(`No existe el modal: ${modalId}`);
            return;
        }

        if (activeModal && activeModal !== modal) {
            activeModal.classList.remove('is-open');
            activeModal.hidden = true;
        }

        modal.hidden = false;

        requestAnimationFrame(() => {
            modal.classList.add('is-open');
        });

        document.body.classList.add('modal-open');
        activeModal = modal;

        window.setTimeout(() => {
            const firstInput = modal.querySelector(
                'input:not([type="hidden"])'
            );

            firstInput?.focus();
        }, 200);
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');

        window.setTimeout(() => {
            modal.hidden = true;
        }, 180);

        document.body.classList.remove('modal-open');

        if (activeModal === modal) {
            activeModal = null;
        }
    }

    document
        .querySelectorAll('[data-open-modal]')
        .forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                openModal(button.dataset.openModal);
            });
        });

    document
        .querySelectorAll('[data-close-modal]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(
                    button.closest('.auth-modal')
                );
            });
        });

    document
        .querySelectorAll('[data-switch-modal]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                const currentModal =
                    button.closest('.auth-modal');

                closeModal(currentModal);

                window.setTimeout(() => {
                    openModal(
                        button.dataset.switchModal
                    );
                }, 190);
            });
        });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && activeModal) {
            closeModal(activeModal);
        }
    });

    const urlParameters =
        new URLSearchParams(window.location.search);

    const initialModal =
        document.body.dataset.openAuthModal
        || urlParameters.get('modal');

    if (initialModal === 'login') {
        openModal('login-modal');
    }

    if (initialModal === 'register') {
        openModal('register-modal');
    }
});