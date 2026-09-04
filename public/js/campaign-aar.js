document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-aar-editor]');
    if (!editor) return;

    const sections = editor.querySelector('[data-aar-sections]');
    const template = editor.querySelector('[data-aar-section-template]');
    const addButton = editor.querySelector('[data-aar-add-section]');

    if (!sections || !template || !addButton) return;

    const refreshLabels = () => {
        sections.querySelectorAll('[data-aar-section]').forEach((section, index) => {
            const label = section.querySelector('.aar-editor__section-head > span');
            if (label) {
                label.textContent = `SECCIÓN ${String(index + 1).padStart(2, '0')}`;
            }
        });
    };

    const bindRemove = (root) => {
        root.querySelectorAll('[data-aar-remove-section]').forEach((button) => {
            if (button.dataset.bound === '1') return;
            button.dataset.bound = '1';
            button.addEventListener('click', () => {
                button.closest('[data-aar-section]')?.remove();
                refreshLabels();
            });
        });
    };

    addButton.addEventListener('click', () => {
        const index = Number(sections.dataset.nextIndex || '0');
        sections.dataset.nextIndex = String(index + 1);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
        const section = wrapper.firstElementChild;

        if (!section) return;

        sections.appendChild(section);
        bindRemove(section);
        refreshLabels();
        section.querySelector('input[type="text"]')?.focus();
    });

    bindRemove(editor);
    refreshLabels();
});
