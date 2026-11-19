/* Recordatorio de guardado para PBO: no guarda automáticamente ni altera FilePond. */
(() => {
    if (window.__sqaActivityPboSavePromptInstalled) return;
    window.__sqaActivityPboSavePromptInstalled = true;

    let dialog;
    let saveButton;
    let currentWireId = null;
    let currentAction = 'save';

    const makeDialog = () => {
        dialog = document.getElementById('sqa-pbo-save-prompt');
        if (dialog) {
            saveButton = dialog.querySelector('[data-sqa-pbo-save]');
            return;
        }

        dialog = document.createElement('dialog');
        dialog.id = 'sqa-pbo-save-prompt';
        dialog.className = 'sqa-pbo-save-prompt';
        dialog.setAttribute('aria-labelledby', 'sqa-pbo-save-title');
        dialog.setAttribute('aria-describedby', 'sqa-pbo-save-description');
        dialog.innerHTML = `
            <div class="sqa-pbo-save-prompt__inner">
                <h2 id="sqa-pbo-save-title">PBO subido</h2>
                <p id="sqa-pbo-save-description">Guarda la actividad para fijar el PBO.</p>
                <div class="sqa-pbo-save-prompt__actions">
                    <button type="button" class="sqa-pbo-save-prompt__cancel" data-sqa-pbo-cancel>Seguir editando</button>
                    <button type="button" class="sqa-pbo-save-prompt__save" data-sqa-pbo-save>Guardar actividad</button>
                </div>
            </div>`;
        document.body.appendChild(dialog);
        dialog.querySelector('[data-sqa-pbo-cancel]').addEventListener('click', () => dialog.close());
        saveButton = dialog.querySelector('[data-sqa-pbo-save]');
        saveButton.addEventListener('click', async () => {
            if (saveButton.disabled) return;
            const $wire = currentWireId && window.Livewire?.find(currentWireId);
            if (!$wire || typeof $wire[currentAction] !== 'function') {
                dialog.close();
                return;
            }

            saveButton.disabled = true;
            // Misma acción del botón Guardar/Crear de la propia página Filament.
            dialog.close();
            try {
                await $wire[currentAction]();
            } catch (error) {
                // Filament muestra las validaciones y errores del propio formulario.
                console.error('No se pudo guardar la actividad:', error);
            } finally {
                saveButton.disabled = false;
            }
        });
    };

    window.addEventListener('sqa-activity-pbo-uploaded', (event) => {
        // El evento solo lo envía el campo PBO después de una subida temporal válida.
        const path = window.location.pathname.replace(/\/+$/, '');
        const editing = /\/activities\/[^/]+\/edit$/.test(path);
        const creating = /\/activities\/create$/.test(path);
        if (!editing && !creating) return;

        const element = event.target instanceof Element ? event.target : null;
        const source = element?.closest('[wire\\:id]');
        const wireId = source?.getAttribute('wire:id');
        if (!wireId) return;

        currentWireId = wireId;
        currentAction = creating ? 'create' : 'save';
        makeDialog();
        if (!dialog.open) dialog.showModal();
    });
})();
