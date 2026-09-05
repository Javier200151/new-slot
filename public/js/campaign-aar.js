document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-aar-editor]');
    if (!editor) return;

    const sections = editor.querySelector('[data-aar-sections]');
    const template = editor.querySelector('[data-aar-section-template]');
    const addButtons = editor.querySelectorAll('[data-aar-add-section]');

    if (!sections || !template || addButtons.length === 0) return;

    window.NewSlotForumEditor?.initAll(editor);

    const reindexFields = () => {
        sections.querySelectorAll('[data-aar-section]').forEach((section, index) => {
            section.querySelectorAll('[name^="sections["]').forEach((field) => {
                field.name = field.name.replace(/^sections\[[^\]]+\]/, `sections[${index}]`);
            });
        });
    };

    const refreshLabels = () => {
        const sectionNodes = Array.from(sections.querySelectorAll('[data-aar-section]'));

        sectionNodes.forEach((section, index) => {
            const label = section.querySelector('.aar-editor__section-head > span');
            if (label) {
                label.textContent = `SECCIÓN ${String(index + 1).padStart(2, '0')}`;
            }

            section.querySelectorAll('[data-aar-move-section]').forEach((button) => {
                const direction = button.dataset.aarMoveSection;
                button.disabled = (
                    (index === 0 && (direction === 'top' || direction === 'up'))
                    || (index === sectionNodes.length - 1 && (direction === 'down' || direction === 'bottom'))
                );
            });
        });

        reindexFields();
    };

    const moveSection = (section, direction) => {
        if (!section) return;

        if (direction === 'top') {
            sections.prepend(section);
        } else if (direction === 'up') {
            const previous = section.previousElementSibling;
            if (previous) sections.insertBefore(section, previous);
        } else if (direction === 'down') {
            const next = section.nextElementSibling;
            if (next) sections.insertBefore(next, section);
        } else if (direction === 'bottom') {
            sections.append(section);
        }

        refreshLabels();
        section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const bindSectionActions = (root) => {
        root.querySelectorAll('[data-aar-remove-section]').forEach((button) => {
            if (button.dataset.bound === '1') return;
            button.dataset.bound = '1';
            button.addEventListener('click', () => {
                button.closest('[data-aar-section]')?.remove();
                refreshLabels();
            });
        });

        root.querySelectorAll('[data-aar-move-section]').forEach((button) => {
            if (button.dataset.bound === '1') return;
            button.dataset.bound = '1';
            button.addEventListener('click', () => {
                moveSection(
                    button.closest('[data-aar-section]'),
                    button.dataset.aarMoveSection,
                );
            });
        });
    };

    const addSection = (position) => {
        if (sections.querySelectorAll('[data-aar-section]').length >= 20) {
            window.alert('El AAR admite un máximo de 20 secciones.');
            return;
        }

        const index = Number(sections.dataset.nextIndex || '0');
        sections.dataset.nextIndex = String(index + 1);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
        const section = wrapper.firstElementChild;

        if (!section) return;

        if (position === 'start') {
            sections.prepend(section);
        } else {
            sections.appendChild(section);
        }

        bindSectionActions(section);
        window.NewSlotForumEditor?.init(section.querySelector('[data-forum-editor]'));
        refreshLabels();
        section.querySelector('input[type="text"]')?.focus();
        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    addButtons.forEach((button) => {
        button.addEventListener('click', () => {
            addSection(button.dataset.aarAddSection === 'start' ? 'start' : 'end');
        });
    });

    editor.addEventListener('submit', reindexFields);

    bindSectionActions(editor);
    refreshLabels();
});
