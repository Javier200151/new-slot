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
