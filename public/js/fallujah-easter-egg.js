/* Easter egg Fallujah 2012. Sin peticiones HTTP ni escrituras de usuario. */
(() => {
    const init = () => {
        const root = document.querySelector('[data-fallujah-root]');
        if (!root) return;

        const openButton = root.querySelector('[data-fallujah-open]');
        const modal = root.querySelector('[data-fallujah-dialog]');
        const panels = [...root.querySelectorAll('[data-fallujah-stage]')];
        const dots = [...root.querySelectorAll('[data-fallujah-indicator]')];
        const pictures = [...root.querySelectorAll('[data-fallujah-photo]')];
        const counter = root.querySelector('[data-fallujah-photo-counter]');
        let step = 0;
        let photo = 0;
        let previousFocus = null;
        let previousOverflow = '';

        const setStep = (next) => {
            step = Math.max(0, Math.min(panels.length - 1, next));
            panels.forEach((panel, i) => { panel.hidden = i !== step; });
            dots.forEach((dot, i) => { dot.classList.toggle('is-active', i === step); });
            modal.querySelector('.sqa-fallujah__body').scrollTop = 0;
        };

        const setPhoto = (next) => {
            photo = (next + pictures.length) % pictures.length;
            pictures.forEach((item, i) => { item.hidden = i !== photo; });
            counter.textContent = `${String(photo + 1).padStart(2, '0')} / ${String(pictures.length).padStart(2, '0')}`;
        };

        const open = () => {
            previousFocus = document.activeElement;
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            modal.hidden = false;
            setStep(0);
            setPhoto(0);
            modal.focus();
        };

        const close = () => {
            if (modal.hidden) return;
            modal.hidden = true;
            document.body.style.overflow = previousOverflow;
            if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
        };

        openButton.addEventListener('click', open);
        root.querySelectorAll('[data-fallujah-close]').forEach((el) => el.addEventListener('click', close));
        root.querySelectorAll('[data-fallujah-next]').forEach((el) => el.addEventListener('click', () => setStep(step + 1)));
        root.querySelectorAll('[data-fallujah-back]').forEach((el) => el.addEventListener('click', () => setStep(step - 1)));
        root.querySelector('[data-fallujah-photo-prev]').addEventListener('click', () => setPhoto(photo - 1));
        root.querySelector('[data-fallujah-photo-next]').addEventListener('click', () => setPhoto(photo + 1));

        modal.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.preventDefault(); close(); return; }
            if (e.key !== 'Tab') return;
            const visible = [...modal.querySelectorAll('a[href],button:not([disabled])')]
                .filter(el => el.getClientRects().length > 0);
            if (!visible.length) { e.preventDefault(); return; }
            const first = visible[0];
            const last = visible[visible.length - 1];
            if (e.shiftKey && (document.activeElement === first || document.activeElement === modal)) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault(); first.focus();
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
