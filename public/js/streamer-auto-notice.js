document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.querySelector('[data-streamer-notice]');
    if (!dialog || !dialog.dataset.noticeUrl || typeof dialog.showModal !== 'function') return;

    const message = dialog.querySelector('[data-streamer-notice-message]');
    const dismiss = dialog.querySelector('[data-streamer-notice-dismiss]');
    const panel = dialog.querySelector('[data-streamer-notice-panel]');
    let currentKey = null;
    const seen = (key) => {
        try { return sessionStorage.getItem(key) === '1'; }
        catch (_) { return false; }
    };
    const acknowledge = () => {
        if (currentKey) {
            try { sessionStorage.setItem(currentKey, '1'); } catch (_) { /* storage blocked */ }
        }
        dialog.close();
    };
    dismiss?.addEventListener('click', acknowledge);
    panel?.addEventListener('click', acknowledge);
    dialog.addEventListener('cancel', (event) => { event.preventDefault(); acknowledge(); });

    async function check() {
        if (document.hidden || dialog.open) return;
        try {
            const response = await fetch(dialog.dataset.noticeUrl, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store'
            });
            if (!response.ok) return;
            const notice = (await response.json()).notice;
            if (!notice || !notice.id || !['not_registered', 'not_eligible'].includes(notice.reason)) return;
            const key = 'sqa-streamer-notice:' + notice.platform + ':' + notice.id;
            if (seen(key)) return;
            currentKey = key;
            message.textContent = notice.reason === 'not_registered'
                ? 'Tu canal está emitiendo, pero no hemos añadido el directo automáticamente porque no figuras apuntado en el ORBAT de un evento en curso. Puedes revisar tu emisión y publicarla manualmente desde tu panel.'
                : 'Tu canal está emitiendo, pero no hemos añadido el directo automáticamente porque la categoría y el título no cumplen los filtros de Arma 3 o Arma Reforger y no hay un evento válido en cuyo ORBAT estés apuntado. Puedes revisar la emisión desde tu panel.';
            if (!document.querySelector('dialog[open]')) dialog.showModal();
        } catch (_) { /* fallo de red: no interrumpir la web */ }
    }
    check();
    window.setInterval(check, 60000);
});
