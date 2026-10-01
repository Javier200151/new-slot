<x-filament-panels::page>
    <script>
        window.newPublicNavigationBuilder = (
            initialMenu,
            catalog,
            destinations,
            audiences,
            maxVisiblePerAudience,
        ) => ({
            menu: initialMenu,
            catalog,
            destinations,
            audiences,
            maxVisiblePerAudience,
            dragging: null,
            dirty: false,
            saving: false,
            status: '',
            statusType: 'info',
            instanceCounter: 0,
            dropdownSelections: {},
            externalLinkName: '',
            externalLinkUrl: '',

            audienceKeys() {
                return Object.keys(this.audiences);
            },

            pageName(page) {
                if (page?.type === 'external') {
                    return page.label || 'Enlace externo';
                }

                return this.destinations[page.destination]?.label ?? page.destination;
            },

            isValidExternalUrl(value) {
                try {
                    const parsed = new URL((value ?? '').trim());
                    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
                } catch (error) {
                    return false;
                }
            },

            newKey(prefix = 'item') {
                this.instanceCounter++;

                if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                    return `${prefix}-${window.crypto.randomUUID()}`;
                }

                return `${prefix}-${Date.now()}-${this.instanceCounter}`;
            },

            defaultVisibility() {
                return [...this.audienceKeys()];
            },

            createPage(destination) {
                return {
                    type: 'link',
                    key: this.newKey('link'),
                    destination,
                    label: this.destinations[destination]?.label ?? destination,
                    visible_to: this.defaultVisibility(),
                };
            },

            createExternalLink(label, url) {
                return {
                    type: 'external',
                    key: this.newKey('external'),
                    label: (label ?? '').trim(),
                    url: (url ?? '').trim(),
                    visible_to: this.defaultVisibility(),
                };
            },

            addExternalLink() {
                const label = (this.externalLinkName ?? '').trim();
                const url = (this.externalLinkUrl ?? '').trim();

                if (!label) {
                    this.setStatus('Escribe el nombre que aparecerá en el menú.', 'error');
                    return;
                }

                if (!this.isValidExternalUrl(url)) {
                    this.setStatus('La URL externa debe empezar por http:// o https:// y ser válida.', 'error');
                    return;
                }

                this.menu.push(this.createExternalLink(label, url));
                this.externalLinkName = '';
                this.externalLinkUrl = '';
                this.markDirty();
            },

            setStatus(message, type = 'info') {
                this.status = message;
                this.statusType = type;
            },

            markDirty() {
                this.dirty = true;
                this.status = '';
            },

            isAudienceSelected(item, audienceKey) {
                return Array.isArray(item?.visible_to)
                    && item.visible_to.includes(audienceKey);
            },

            toggleAudience(item, audienceKey, checked) {
                if (!Array.isArray(item.visible_to)) {
                    item.visible_to = [];
                }

                if (checked && !item.visible_to.includes(audienceKey)) {
                    item.visible_to.push(audienceKey);
                }

                if (!checked) {
                    item.visible_to = item.visible_to.filter(key => key !== audienceKey);
                }

                this.markDirty();
            },

            selectAllAudiences(item) {
                item.visible_to = this.defaultVisibility();
                this.markDirty();
            },

            isEffectivelyVisible(item, audienceKey) {
                if (!this.isAudienceSelected(item, audienceKey)) {
                    return false;
                }

                if (item.type !== 'dropdown') {
                    return true;
                }

                return (item.children ?? []).some(
                    child => this.isAudienceSelected(child, audienceKey),
                );
            },

            audienceCount(audienceKey) {
                return this.menu.filter(
                    item => this.isEffectivelyVisible(item, audienceKey),
                ).length;
            },

            hasAudienceOverflow() {
                return this.audienceKeys().some(
                    key => this.audienceCount(key) > this.maxVisiblePerAudience,
                );
            },

            addDropdown() {
                this.menu.push({
                    type: 'dropdown',
                    key: this.newKey('dropdown'),
                    label: 'Nuevo desplegable',
                    visible_to: this.defaultVisibility(),
                    children: [],
                });

                this.markDirty();
            },

            deleteDropdown(menuIndex) {
                const item = this.menu[menuIndex];

                if (!item || item.type !== 'dropdown') {
                    return;
                }

                this.menu.splice(menuIndex, 1);
                this.markDirty();
            },

            moveMenuExtreme(menuIndex, toEnd) {
                const [item] = this.menu.splice(menuIndex, 1);

                if (!item) {
                    return;
                }

                if (toEnd) {
                    this.menu.push(item);
                } else {
                    this.menu.unshift(item);
                }

                this.markDirty();
            },

            moveChildExtreme(menuIndex, childIndex, toEnd) {
                const dropdown = this.menu[menuIndex];

                if (!dropdown || dropdown.type !== 'dropdown') {
                    return;
                }

                const [item] = dropdown.children.splice(childIndex, 1);

                if (!item) {
                    return;
                }

                if (toEnd) {
                    dropdown.children.push(item);
                } else {
                    dropdown.children.unshift(item);
                }

                this.markDirty();
            },

            removeMenuPage(menuIndex) {
                const item = this.menu[menuIndex];

                if (!item || !['link', 'external'].includes(item.type)) {
                    return;
                }

                this.menu.splice(menuIndex, 1);
                this.markDirty();
            },

            removeChild(menuIndex, childIndex) {
                const dropdown = this.menu[menuIndex];

                if (!dropdown || dropdown.type !== 'dropdown') {
                    return;
                }

                dropdown.children.splice(childIndex, 1);
                this.markDirty();
            },

            addCatalogAtEnd(catalogIndex) {
                const template = this.catalog[catalogIndex];

                if (!template) {
                    return;
                }

                this.menu.push(this.createPage(template.destination));
                this.markDirty();
            },

            promoteChild(menuIndex, childIndex) {
                const dropdown = this.menu[menuIndex];

                if (!dropdown || dropdown.type !== 'dropdown') {
                    return;
                }

                const [item] = dropdown.children.splice(childIndex, 1);

                if (!item) {
                    return;
                }

                this.menu.push(item);
                this.markDirty();
            },

            addCatalogToDropdown(menuIndex) {
                const dropdown = this.menu[menuIndex];
                const destination = this.dropdownSelections[dropdown?.key] ?? '';

                if (!dropdown || dropdown.type !== 'dropdown' || !destination) {
                    return;
                }

                dropdown.children.push(this.createPage(destination));
                this.dropdownSelections[dropdown.key] = '';
                this.markDirty();
            },

            dragStart(source) {
                this.dragging = source;
            },

            dragEnd() {
                this.dragging = null;
            },

            draggedItem() {
                if (!this.dragging) {
                    return null;
                }

                if (this.dragging.zone === 'menu') {
                    return this.menu[this.dragging.index] ?? null;
                }

                if (this.dragging.zone === 'catalog') {
                    const template = this.catalog[this.dragging.index] ?? null;
                    return template
                        ? { ...template, type: 'link' }
                        : null;
                }

                if (this.dragging.zone === 'dropdown') {
                    return this.menu[this.dragging.menuIndex]
                        ?.children?.[this.dragging.childIndex] ?? null;
                }

                return null;
            },

            takeDragged() {
                if (!this.dragging) {
                    return null;
                }

                if (this.dragging.zone === 'catalog') {
                    const template = this.catalog[this.dragging.index] ?? null;
                    return template ? this.createPage(template.destination) : null;
                }

                if (this.dragging.zone === 'menu') {
                    return this.menu.splice(this.dragging.index, 1)[0] ?? null;
                }

                if (this.dragging.zone === 'dropdown') {
                    const dropdown = this.menu[this.dragging.menuIndex];
                    return dropdown?.children?.splice(
                        this.dragging.childIndex,
                        1,
                    )[0] ?? null;
                }

                return null;
            },

            dropMenuAt(targetIndex) {
                if (!this.dragging) {
                    return;
                }

                const targetKey = this.menu[targetIndex]?.key ?? null;
                const moved = this.takeDragged();

                if (!moved) {
                    this.dragging = null;
                    return;
                }

                let insertionIndex = targetKey
                    ? this.menu.findIndex(item => item.key === targetKey)
                    : this.menu.length;

                if (insertionIndex < 0) {
                    insertionIndex = this.menu.length;
                }

                this.menu.splice(insertionIndex, 0, moved);
                this.dragging = null;
                this.markDirty();
            },

            dropMenuEnd() {
                if (!this.dragging) {
                    return;
                }

                const moved = this.takeDragged();

                if (moved) {
                    this.menu.push(moved);
                    this.markDirty();
                }

                this.dragging = null;
            },

            dropIntoDropdown(targetMenuIndex, targetChildIndex = null) {
                if (!this.dragging) {
                    return;
                }

                const preview = this.draggedItem();

                if (!preview || !['link', 'external'].includes(preview.type)) {
                    this.setStatus('Solo las páginas y enlaces externos pueden colocarse dentro de un desplegable.', 'error');
                    this.dragging = null;
                    return;
                }

                const targetDropdownKey = this.menu[targetMenuIndex]?.key ?? null;
                const targetChildKey = targetChildIndex === null
                    ? null
                    : this.menu[targetMenuIndex]?.children?.[targetChildIndex]?.key ?? null;
                const moved = this.takeDragged();
                const currentMenuIndex = this.menu.findIndex(
                    item => item.key === targetDropdownKey,
                );
                const dropdown = this.menu[currentMenuIndex];

                if (!moved || !dropdown || dropdown.type !== 'dropdown') {
                    this.dragging = null;
                    return;
                }

                let insertionIndex = dropdown.children.length;

                if (targetChildKey) {
                    const located = dropdown.children.findIndex(
                        child => child.key === targetChildKey,
                    );

                    if (located >= 0) {
                        insertionIndex = located;
                    }
                }

                dropdown.children.splice(insertionIndex, 0, moved);
                this.dragging = null;
                this.markDirty();
            },

            dropRemove() {
                if (!this.dragging || this.dragging.zone === 'catalog') {
                    this.dragging = null;
                    return;
                }

                const removed = this.takeDragged();
                this.dragging = null;

                if (removed) {
                    this.markDirty();
                }
            },

            validationMessage() {
                for (const item of this.menu) {
                    if (!Array.isArray(item.visible_to) || item.visible_to.length === 0) {
                        const name = item.type === 'dropdown'
                            ? `El desplegable «${item.label || 'sin nombre'}»`
                            : `La página «${this.pageName(item)}»`;
                        return `${name} debe ser visible al menos para un estado o para invitados.`;
                    }

                    if (item.type === 'external') {
                        if (!(item.label ?? '').trim()) {
                            return 'Todos los enlaces externos deben tener un nombre.';
                        }

                        if (!this.isValidExternalUrl(item.url)) {
                            return `El enlace externo «${item.label}» debe tener una URL válida.`;
                        }
                    }

                    if (item.type === 'dropdown') {
                        if (!(item.label ?? '').trim()) {
                            return 'Todos los desplegables deben tener un nombre.';
                        }

                        if (!Array.isArray(item.children) || item.children.length === 0) {
                            return `El desplegable «${item.label}» debe contener al menos una página.`;
                        }

                        for (const child of item.children) {
                            if (!Array.isArray(child.visible_to) || child.visible_to.length === 0) {
                                return `La página «${this.pageName(child)}» debe ser visible al menos para un estado o para invitados.`;
                            }

                            if (child.type === 'external') {
                                if (!(child.label ?? '').trim()) {
                                    return 'Todos los enlaces externos deben tener un nombre.';
                                }

                                if (!this.isValidExternalUrl(child.url)) {
                                    return `El enlace externo «${child.label}» debe tener una URL válida.`;
                                }
                            }
                        }
                    }
                }

                const overflow = this.audienceKeys()
                    .map(key => ({ key, count: this.audienceCount(key) }))
                    .filter(entry => entry.count > this.maxVisiblePerAudience);

                if (overflow.length > 0) {
                    return 'Cada estado puede ver como máximo '
                        + `${this.maxVisiblePerAudience} columnas. Revisa: `
                        + overflow.map(entry => `${this.audiences[entry.key]} (${entry.count})`).join(', ')
                        + '.';
                }

                return '';
            },

            async save() {
                if (this.saving || !this.dirty) {
                    return;
                }

                const validation = this.validationMessage();

                if (validation) {
                    this.setStatus(validation, 'error');
                    return;
                }

                this.saving = true;
                this.status = '';

                try {
                    const result = await this.$wire.saveLayout(
                        JSON.parse(JSON.stringify(this.menu)),
                        JSON.parse(JSON.stringify(this.catalog)),
                    );

                    if (result?.ok) {
                        this.dirty = false;
                        this.setStatus(result.message, 'success');
                    } else {
                        this.setStatus(
                            result?.message ?? 'No se pudo guardar la navegación.',
                            'error',
                        );
                    }
                } catch (error) {
                    this.setStatus(
                        'No se pudo guardar la navegación. Revisa la conexión e inténtalo de nuevo.',
                        'error',
                    );
                } finally {
                    this.saving = false;
                }
            },
        });
    </script>

    <div
        wire:ignore
        x-data="newPublicNavigationBuilder(
            @js($menuItems),
            @js($availablePages),
            @js($destinations),
            @js($audiences),
            @js($maxTopLevelItems)
        )"
        class="public-nav-builder"
    >
        <section class="public-nav-builder__intro">
            <div>
                <strong>Constructor visual del menú público</strong>
                <p>
                    Las páginas de abajo forman un catálogo permanente: puedes usar la misma página
                    varias veces. Cada colocación y cada desplegable tiene su propia visibilidad por
                    estado. Puedes configurar más de 6 columnas siempre que ningún estado (ni los
                    invitados) llegue a ver más de 6 simultáneamente.
                </p>
            </div>

            <div class="public-nav-builder__main-actions">
                <x-filament::button
                    type="button"
                    color="gray"
                    icon="heroicon-o-plus"
                    x-on:click="addDropdown()"
                >
                    Añadir desplegable
                </x-filament::button>

                <x-filament::button
                    type="button"
                    icon="heroicon-o-check"
                    x-on:click="save()"
                    x-bind:disabled="saving || !dirty"
                >
                    <span x-show="!saving">Guardar navegación</span>
                    <span x-show="saving">Guardando…</span>
                </x-filament::button>
            </div>
        </section>

        <section class="public-nav-builder__external-create">
            <div>
                <span>Enlace externo</span>
                <strong>Añadir URL personalizada</strong>
                <p>Úsalo para Wiki, documentación u otras páginas fuera de NewSlot. Después puedes arrastrarlo dentro de un desplegable.</p>
            </div>

            <label>
                <span>Nombre</span>
                <input
                    type="text"
                    maxlength="80"
                    x-model="externalLinkName"
                    placeholder="Ej. Wiki externa"
                >
            </label>

            <label class="public-nav-builder__external-url">
                <span>URL</span>
                <input
                    type="url"
                    maxlength="2048"
                    x-model="externalLinkUrl"
                    placeholder="https://..."
                    x-on:keydown.enter.prevent="addExternalLink()"
                >
            </label>

            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-o-arrow-top-right-on-square"
                x-on:click="addExternalLink()"
            >
                Añadir enlace externo
            </x-filament::button>
        </section>

        <div
            x-show="status"
            x-cloak
            class="public-nav-builder__status"
            x-bind:class="{
                'is-success': statusType === 'success',
                'is-error': statusType === 'error',
            }"
            role="status"
            aria-live="polite"
        >
            <span x-text="status"></span>
        </div>

        <section class="public-nav-builder__audience-summary">
            <div class="public-nav-builder__audience-summary-title">
                <strong>Columnas visibles por estado</strong>
                <span>Máximo <b x-text="maxVisiblePerAudience"></b> por cada uno.</span>
            </div>

            <div class="public-nav-builder__audience-counts">
                <template x-for="(audienceLabel, audienceKey) in audiences" :key="audienceKey">
                    <span
                        class="public-nav-builder__audience-count"
                        x-bind:class="{ 'is-over': audienceCount(audienceKey) > maxVisiblePerAudience }"
                    >
                        <span x-text="audienceLabel"></span>
                        <b><span x-text="audienceCount(audienceKey)"></span>/<span x-text="maxVisiblePerAudience"></span></b>
                    </span>
                </template>
            </div>
        </section>

        <section class="public-nav-builder__section">
            <header class="public-nav-builder__section-head">
                <div>
                    <span>Frontend</span>
                    <h2>Menú principal</h2>
                    <p>
                        El orden visual es exactamente el orden del header, de izquierda a derecha.
                        Puedes tener más de 6 configuradas si ningún estado llega a ver más de 6.
                    </p>
                </div>
                <strong><span x-text="menu.length"></span> configuradas</strong>
            </header>

            <div
                class="public-nav-builder__menu"
                x-on:dragover.prevent
                x-on:drop.prevent="dropMenuEnd()"
            >
                <template x-for="(item, menuIndex) in menu" :key="item.key">
                    <article
                        class="public-nav-builder__menu-card"
                        x-bind:class="{
                            'is-dropdown': item.type === 'dropdown',
                            'is-page': ['link', 'external'].includes(item.type),
                            'is-external': item.type === 'external',
                        }"
                        draggable="true"
                        x-on:dragstart.stop="dragStart({ zone: 'menu', index: menuIndex })"
                        x-on:dragend="dragEnd()"
                        x-on:dragover.prevent.stop
                        x-on:drop.prevent.stop="dropMenuAt(menuIndex)"
                    >
                        <template x-if="['link', 'external'].includes(item.type)">
                            <div class="public-nav-builder__page-card">
                                <header class="public-nav-builder__card-head">
                                    <span class="public-nav-builder__drag" aria-hidden="true">⋮⋮</span>
                                    <div>
                                        <small x-text="item.type === 'external' ? 'Enlace externo' : 'Página'"></small>
                                        <strong x-text="pageName(item)"></strong>
                                    </div>
                                </header>

                                <label class="public-nav-builder__label-field">
                                    <span>Texto mostrado</span>
                                    <input
                                        type="text"
                                        maxlength="80"
                                        x-model="item.label"
                                        x-on:input="markDirty()"
                                    >
                                </label>

                                <label class="public-nav-builder__label-field" x-show="item.type === 'external'">
                                    <span>URL externa</span>
                                    <input
                                        type="url"
                                        maxlength="2048"
                                        x-model="item.url"
                                        x-on:input="markDirty()"
                                        placeholder="https://..."
                                    >
                                </label>

                                <div class="public-nav-builder__visibility">
                                    <div class="public-nav-builder__visibility-head">
                                        <span>Visible para</span>
                                        <button type="button" x-on:click="selectAllAudiences(item)">Todos</button>
                                    </div>
                                    <div class="public-nav-builder__checks">
                                        <template x-for="(audienceLabel, audienceKey) in audiences" :key="`${item.key}-${audienceKey}`">
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    x-bind:checked="isAudienceSelected(item, audienceKey)"
                                                    x-on:change="toggleAudience(item, audienceKey, $event.target.checked)"
                                                >
                                                <span x-text="audienceLabel"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>

                                <div class="public-nav-builder__card-actions">
                                    <button type="button" title="Mover a la izquierda del todo" x-on:click="moveMenuExtreme(menuIndex, false)">⇤</button>
                                    <button type="button" title="Mover a la derecha del todo" x-on:click="moveMenuExtreme(menuIndex, true)">⇥</button>
                                    <button type="button" class="is-muted" title="Quitar esta colocación" x-on:click="removeMenuPage(menuIndex)">Quitar</button>
                                </div>
                            </div>
                        </template>

                        <template x-if="item.type === 'dropdown'">
                            <div class="public-nav-builder__dropdown-card">
                                <header class="public-nav-builder__dropdown-head">
                                    <span class="public-nav-builder__drag" aria-hidden="true">⋮⋮</span>
                                    <div class="public-nav-builder__dropdown-title">
                                        <small>Desplegable</small>
                                        <input
                                            type="text"
                                            maxlength="80"
                                            x-model="item.label"
                                            x-on:input="markDirty()"
                                            aria-label="Nombre del desplegable"
                                        >
                                    </div>
                                    <div class="public-nav-builder__dropdown-actions">
                                        <button type="button" title="Mover a la izquierda del todo" x-on:click="moveMenuExtreme(menuIndex, false)">⇤</button>
                                        <button type="button" title="Mover a la derecha del todo" x-on:click="moveMenuExtreme(menuIndex, true)">⇥</button>
                                        <button type="button" class="is-danger" title="Eliminar desplegable" x-on:click="deleteDropdown(menuIndex)">×</button>
                                    </div>
                                </header>

                                <div class="public-nav-builder__visibility public-nav-builder__visibility--dropdown">
                                    <div class="public-nav-builder__visibility-head">
                                        <span>Mostrar columna a</span>
                                        <button type="button" x-on:click="selectAllAudiences(item)">Todos</button>
                                    </div>
                                    <div class="public-nav-builder__checks">
                                        <template x-for="(audienceLabel, audienceKey) in audiences" :key="`${item.key}-dropdown-${audienceKey}`">
                                            <label>
                                                <input
                                                    type="checkbox"
                                                    x-bind:checked="isAudienceSelected(item, audienceKey)"
                                                    x-on:change="toggleAudience(item, audienceKey, $event.target.checked)"
                                                >
                                                <span x-text="audienceLabel"></span>
                                            </label>
                                        </template>
                                    </div>
                                </div>

                                <div
                                    class="public-nav-builder__children"
                                    x-on:dragover.prevent.stop
                                    x-on:drop.prevent.stop="dropIntoDropdown(menuIndex)"
                                >
                                    <template x-for="(child, childIndex) in item.children" :key="child.key">
                                        <div
                                            class="public-nav-builder__child"
                                            draggable="true"
                                            x-on:dragstart.stop="dragStart({ zone: 'dropdown', menuIndex, childIndex })"
                                            x-on:dragend="dragEnd()"
                                            x-on:dragover.prevent.stop
                                            x-on:drop.prevent.stop="dropIntoDropdown(menuIndex, childIndex)"
                                        >
                                            <div class="public-nav-builder__child-row">
                                                <div class="public-nav-builder__child-main">
                                                    <span class="public-nav-builder__drag" aria-hidden="true">⋮⋮</span>
                                                    <div>
                                                        <strong x-text="pageName(child)"></strong>
                                                        <input
                                                            type="text"
                                                            maxlength="80"
                                                            x-model="child.label"
                                                            x-on:input="markDirty()"
                                                            aria-label="Texto mostrado"
                                                        >
                                                        <input
                                                            x-show="child.type === 'external'"
                                                            type="url"
                                                            maxlength="2048"
                                                            x-model="child.url"
                                                            x-on:input="markDirty()"
                                                            aria-label="URL externa"
                                                            placeholder="https://..."
                                                        >
                                                    </div>
                                                </div>

                                                <div class="public-nav-builder__child-actions">
                                                    <button type="button" title="Primera posición" x-on:click="moveChildExtreme(menuIndex, childIndex, false)">⇤</button>
                                                    <button type="button" title="Última posición" x-on:click="moveChildExtreme(menuIndex, childIndex, true)">⇥</button>
                                                    <button type="button" title="Sacar al menú principal" x-on:click="promoteChild(menuIndex, childIndex)">↑</button>
                                                    <button type="button" class="is-muted" title="Quitar esta colocación" x-on:click="removeChild(menuIndex, childIndex)">×</button>
                                                </div>
                                            </div>

                                            <div class="public-nav-builder__visibility public-nav-builder__visibility--child">
                                                <div class="public-nav-builder__visibility-head">
                                                    <span>Visible dentro del desplegable para</span>
                                                    <button type="button" x-on:click="selectAllAudiences(child)">Todos</button>
                                                </div>
                                                <div class="public-nav-builder__checks">
                                                    <template x-for="(audienceLabel, audienceKey) in audiences" :key="`${child.key}-${audienceKey}`">
                                                        <label>
                                                            <input
                                                                type="checkbox"
                                                                x-bind:checked="isAudienceSelected(child, audienceKey)"
                                                                x-on:change="toggleAudience(child, audienceKey, $event.target.checked)"
                                                            >
                                                            <span x-text="audienceLabel"></span>
                                                        </label>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <div class="public-nav-builder__drop-zone">Arrastra una página aquí</div>
                                </div>

                                <div class="public-nav-builder__dropdown-add">
                                    <select x-model="dropdownSelections[item.key]" aria-label="Página para añadir al desplegable">
                                        <option value="">Añadir página…</option>
                                        <template x-for="page in catalog" :key="`option-${item.key}-${page.key}`">
                                            <option x-bind:value="page.destination" x-text="pageName(page)"></option>
                                        </template>
                                    </select>
                                    <button type="button" x-on:click="addCatalogToDropdown(menuIndex)">Añadir</button>
                                </div>
                            </div>
                        </template>
                    </article>
                </template>

                <div class="public-nav-builder__menu-drop-end">Soltar al final del menú</div>
            </div>
        </section>

        <section class="public-nav-builder__section">
            <header class="public-nav-builder__section-head">
                <div>
                    <span>Catálogo permanente</span>
                    <h2>Páginas</h2>
                    <p>
                        Incluye las páginas fijas y las creadas desde Filament > Páginas.
                        Arrástralas o pulsa “Añadir al menú”; puedes repetirlas en varios desplegables.
                    </p>
                </div>
                <strong x-text="catalog.length"></strong>
            </header>

            <div
                class="public-nav-builder__catalog"
                x-on:dragover.prevent
                x-on:drop.prevent="dropRemove()"
            >
                <template x-for="(page, catalogIndex) in catalog" :key="page.key">
                    <article
                        class="public-nav-builder__catalog-card"
                        draggable="true"
                        x-on:dragstart.stop="dragStart({ zone: 'catalog', index: catalogIndex })"
                        x-on:dragend="dragEnd()"
                    >
                        <div>
                            <span class="public-nav-builder__drag" aria-hidden="true">⋮⋮</span>
                            <div>
                                <small
                                    x-text="page.source === 'dynamic'
                                        ? (page.published ? 'Página dinámica' : 'Página dinámica · No publicada')
                                        : 'Página fija'"
                                ></small>
                                <strong x-text="pageName(page)"></strong>
                            </div>
                        </div>

                        <button type="button" x-on:click="addCatalogAtEnd(catalogIndex)">Añadir al menú</button>
                    </article>
                </template>
            </div>

            <p class="public-nav-builder__remove-hint">
                También puedes arrastrar aquí una colocación existente para quitarla del header.
                La página seguirá disponible en este catálogo.
            </p>
        </section>
    </div>

    <style>
        [x-cloak] { display: none !important; }

        .public-nav-builder { display: grid; gap: 1rem; }

        .public-nav-builder__intro,
        .public-nav-builder__section,
        .public-nav-builder__audience-summary {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: .9rem;
            background: rgba(24, 24, 27, .74);
        }

        .public-nav-builder__intro {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
        }

        .public-nav-builder__external-create {
            display: grid;
            grid-template-columns: minmax(220px, 1.1fr) minmax(180px, .7fr) minmax(280px, 1.3fr) auto;
            gap: .8rem;
            align-items: end;
            padding: 1rem;
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: .9rem;
            background: rgba(24, 24, 27, .74);
        }

        .public-nav-builder__external-create > div > span,
        .public-nav-builder__external-create label > span {
            display: block;
            margin-bottom: .3rem;
            color: rgb(161, 161, 170);
            font-size: .72rem;
            font-weight: 700;
        }

        .public-nav-builder__external-create strong { display: block; }
        .public-nav-builder__external-create p {
            margin: .25rem 0 0;
            color: rgb(161, 161, 170);
            font-size: .78rem;
            line-height: 1.4;
        }

        .public-nav-builder__external-create input {
            width: 100%;
            min-height: 2.55rem;
            padding: .55rem .7rem;
            border: 1px solid rgba(148, 163, 184, .28);
            border-radius: .55rem;
            background: rgba(9, 9, 11, .7);
            color: rgb(244, 244, 245);
        }

        .public-nav-builder__intro strong { display: block; font-size: 1rem; }

        .public-nav-builder__intro p,
        .public-nav-builder__section-head p {
            margin-top: .3rem;
            max-width: 56rem;
            color: rgb(161, 161, 170);
            font-size: .85rem;
            line-height: 1.45;
        }

        .public-nav-builder__main-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            justify-content: flex-end;
        }

        .public-nav-builder__status {
            padding: .75rem .9rem;
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: .7rem;
            background: rgba(63, 63, 70, .45);
            color: rgb(228, 228, 231);
        }

        .public-nav-builder__status.is-success {
            border-color: rgba(34, 197, 94, .45);
            background: rgba(34, 197, 94, .1);
        }

        .public-nav-builder__status.is-error {
            border-color: rgba(239, 68, 68, .55);
            background: rgba(239, 68, 68, .1);
        }

        .public-nav-builder__audience-summary { padding: .9rem 1rem; }

        .public-nav-builder__audience-summary-title {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem 1rem;
            align-items: baseline;
            margin-bottom: .65rem;
        }

        .public-nav-builder__audience-summary-title span {
            color: rgb(161, 161, 170);
            font-size: .78rem;
        }

        .public-nav-builder__audience-counts {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .public-nav-builder__audience-count {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .34rem .55rem;
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 999px;
            background: rgba(39, 39, 42, .72);
            color: rgb(212, 212, 216);
            font-size: .72rem;
        }

        .public-nav-builder__audience-count.is-over {
            border-color: rgba(239, 68, 68, .7);
            background: rgba(239, 68, 68, .13);
            color: rgb(254, 202, 202);
        }

        .public-nav-builder__section { overflow: hidden; }

        .public-nav-builder__section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .public-nav-builder__section-head > strong {
            color: rgb(250, 204, 21);
            white-space: nowrap;
        }

        .public-nav-builder__section-head > div > span,
        .public-nav-builder__card-head small,
        .public-nav-builder__dropdown-title small,
        .public-nav-builder__catalog-card small {
            color: rgb(161, 161, 170);
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .public-nav-builder__section-head h2 { margin-top: .05rem; font-size: 1rem; font-weight: 700; }

        .public-nav-builder__menu {
            display: flex;
            flex-wrap: nowrap;
            align-items: flex-start;
            gap: .85rem;
            padding: 1rem 1rem .8rem;
            overflow-x: auto;
            overflow-y: hidden;
            overscroll-behavior-inline: contain;
            scrollbar-gutter: stable;
            scrollbar-width: thin;
            scrollbar-color: rgba(234, 179, 8, .7) rgba(39, 39, 42, .65);
        }

        .public-nav-builder__menu::-webkit-scrollbar { height: .72rem; }
        .public-nav-builder__menu::-webkit-scrollbar-track {
            border-radius: 999px;
            background: rgba(39, 39, 42, .65);
        }
        .public-nav-builder__menu::-webkit-scrollbar-thumb {
            border: 2px solid rgba(39, 39, 42, .65);
            border-radius: 999px;
            background: rgba(234, 179, 8, .7);
        }

        .public-nav-builder__menu-card {
            flex: 0 0 330px;
            width: 330px;
            min-width: 330px;
            border: 1px solid rgba(148, 163, 184, .2);
            border-radius: .8rem;
            background: rgba(39, 39, 42, .78);
        }

        .public-nav-builder__menu-card:active,
        .public-nav-builder__child:active,
        .public-nav-builder__catalog-card:active { cursor: grabbing; }

        .public-nav-builder__page-card { display: grid; gap: .75rem; padding: .85rem; }

        .public-nav-builder__card-head,
        .public-nav-builder__dropdown-head,
        .public-nav-builder__child-row,
        .public-nav-builder__catalog-card,
        .public-nav-builder__catalog-card > div {
            display: flex;
            align-items: center;
            gap: .65rem;
        }

        .public-nav-builder__card-head > div,
        .public-nav-builder__catalog-card > div > div { min-width: 0; }

        .public-nav-builder__card-head strong,
        .public-nav-builder__catalog-card strong {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .public-nav-builder__drag {
            flex: 0 0 auto;
            color: rgb(113, 113, 122);
            cursor: grab;
            font-size: 1rem;
            user-select: none;
        }

        .public-nav-builder__label-field { display: grid; gap: .3rem; }
        .public-nav-builder__label-field > span,
        .public-nav-builder__visibility-head > span {
            color: rgb(212, 212, 216);
            font-size: .72rem;
            font-weight: 600;
        }

        .public-nav-builder input[type="text"],
        .public-nav-builder select {
            width: 100%;
            min-height: 2.25rem;
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: .55rem;
            background: rgb(39, 39, 42);
            color: rgb(244, 244, 245);
            padding: .45rem .6rem;
            font-size: .82rem;
        }

        .public-nav-builder__card-actions,
        .public-nav-builder__dropdown-actions,
        .public-nav-builder__child-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .public-nav-builder__card-actions button,
        .public-nav-builder__dropdown-actions button,
        .public-nav-builder__child-actions button,
        .public-nav-builder__dropdown-add button,
        .public-nav-builder__catalog-card > button,
        .public-nav-builder__visibility-head button {
            min-height: 2rem;
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: .45rem;
            background: rgba(63, 63, 70, .72);
            color: rgb(228, 228, 231);
            padding: .32rem .55rem;
            font-size: .72rem;
            cursor: pointer;
        }

        .public-nav-builder__dropdown-actions .is-danger,
        .public-nav-builder button.is-danger { color: rgb(248, 113, 113); }
        .public-nav-builder button.is-muted { color: rgb(161, 161, 170); }

        .public-nav-builder__visibility {
            display: grid;
            gap: .45rem;
            padding: .65rem;
            border: 1px solid rgba(148, 163, 184, .14);
            border-radius: .65rem;
            background: rgba(24, 24, 27, .48);
        }

        .public-nav-builder__visibility-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .public-nav-builder__visibility-head button { min-height: auto; padding: .2rem .45rem; }

        .public-nav-builder__checks {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .55rem;
        }

        .public-nav-builder__checks label {
            display: inline-flex;
            align-items: center;
            gap: .32rem;
            color: rgb(212, 212, 216);
            font-size: .68rem;
            cursor: pointer;
        }

        .public-nav-builder__checks input { width: 1rem; height: 1rem; accent-color: rgb(234, 179, 8); }

        .public-nav-builder__dropdown-card { display: grid; gap: .7rem; padding: .75rem; }
        .public-nav-builder__dropdown-head { align-items: flex-start; }
        .public-nav-builder__dropdown-title { flex: 1 1 auto; min-width: 0; }
        .public-nav-builder__dropdown-title input { margin-top: .2rem; }
        .public-nav-builder__dropdown-actions { justify-content: flex-end; }

        .public-nav-builder__children { display: grid; gap: .5rem; }

        .public-nav-builder__child {
            display: grid;
            gap: .55rem;
            padding: .6rem;
            border: 1px solid rgba(148, 163, 184, .16);
            border-radius: .65rem;
            background: rgba(63, 63, 70, .42);
        }

        .public-nav-builder__child-row { justify-content: space-between; align-items: flex-start; }
        .public-nav-builder__child-main { display: flex; min-width: 0; flex: 1 1 auto; gap: .5rem; align-items: flex-start; }
        .public-nav-builder__child-main > div { min-width: 0; flex: 1 1 auto; }
        .public-nav-builder__child-main strong { display: block; margin-bottom: .25rem; font-size: .78rem; }
        .public-nav-builder__child-actions { flex: 0 0 auto; justify-content: flex-end; }
        .public-nav-builder__visibility--child { padding: .5rem; }

        .public-nav-builder__drop-zone,
        .public-nav-builder__menu-drop-end {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 2.6rem;
            border: 1px dashed rgba(148, 163, 184, .3);
            border-radius: .6rem;
            color: rgb(113, 113, 122);
            font-size: .72rem;
        }

        .public-nav-builder__menu-drop-end {
            flex: 0 0 180px;
            min-width: 180px;
            align-self: stretch;
        }

        .public-nav-builder__dropdown-add {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .5rem;
        }

        .public-nav-builder__catalog {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: .65rem;
            padding: 1rem;
        }

        .public-nav-builder__catalog-card {
            justify-content: space-between;
            min-width: 0;
            padding: .7rem;
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: .7rem;
            background: rgba(39, 39, 42, .58);
        }

        .public-nav-builder__catalog-card > div { min-width: 0; }
        .public-nav-builder__catalog-card > button { flex: 0 0 auto; }

        .public-nav-builder__remove-hint {
            margin: 0 1rem 1rem;
            color: rgb(113, 113, 122);
            font-size: .72rem;
        }

        @media (max-width: 780px) {
            .public-nav-builder__external-create {
                grid-template-columns: 1fr;
                align-items: stretch;
            }
            .public-nav-builder__intro,
            .public-nav-builder__section-head { align-items: stretch; flex-direction: column; }
            .public-nav-builder__main-actions { justify-content: flex-start; }
            .public-nav-builder__menu { padding-inline: .75rem; }
            .public-nav-builder__menu-card {
                flex-basis: min(310px, calc(100vw - 4rem));
                width: min(310px, calc(100vw - 4rem));
                min-width: min(310px, calc(100vw - 4rem));
            }
            .public-nav-builder__catalog { grid-template-columns: 1fr; }
            .public-nav-builder__child-row { flex-direction: column; }
            .public-nav-builder__child-actions { justify-content: flex-start; }
        }
    </style>
</x-filament-panels::page>
