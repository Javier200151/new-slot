<x-filament-panels::page>
    @include('filament.pages.partials.sqa-navigation-builder-styles')
    <script>
        window.footerNavigationEditor = (initialItems, initialCatalog, audienceOptions) => ({
            menu: initialItems, catalog: initialCatalog, audiences: audienceOptions,
            dragging: null, dirty: false, saving: false, notice: '', failed: false,
            search: '', externalLabel: '', externalUrl: '', counter: 0,
            makeKey() { this.counter++; return `footer-${Date.now()}-${this.counter}`; },
            audienceKeys() { return Object.keys(this.audiences); },
            audienceEntries() { return Object.entries(this.audiences); },
            changed() { this.dirty = true; this.notice = ''; this.failed = false; },
            error(message) { this.notice = message; this.failed = true; },
            add(template, target = this.menu.length) {
                if (this.menu.length >= 24) return this.error('El footer admite hasta 24 botones.');
                if (template.type === 'server_status' && this.menu.some(i => i.type === 'server_status'))
                    return this.error('Estado de servidores ya está colocado.');
                this.menu.splice(target, 0, {
                    type: template.type, destination: template.destination || null,
                    label: template.label, key: this.makeKey(),
                    visible_to: template.type === 'server_status' ? ['ACTIVO', 'RECLUTA'] : this.audienceKeys(),
                });
                this.changed();
            },
            addExternal() {
                const label = this.externalLabel.trim(), url = this.externalUrl.trim();
                if (!label || !/^https?:\/\//i.test(url)) return this.error('Indica un nombre y una URL http(s) válida.');
                if (this.menu.length >= 24) return this.error('El footer admite hasta 24 botones.');
                this.menu.push({type:'external', key:this.makeKey(), label, url, visible_to:this.audienceKeys()});
                this.externalLabel = ''; this.externalUrl = ''; this.changed();
            },
            remove(index) { this.menu.splice(index, 1); this.changed(); },
            move(index, delta) {
                const next = index + delta;
                if (next < 0 || next >= this.menu.length) return;
                [this.menu[index], this.menu[next]] = [this.menu[next], this.menu[index]];
                this.changed();
            },
            toggleAudience(item, key, checked) {
                item.visible_to = Array.isArray(item.visible_to) ? item.visible_to : [];
                if (checked && !item.visible_to.includes(entry[0])) item.visible_to.push(key);
                if (!checked) item.visible_to = item.visible_to.filter(v => v !== key);
                this.changed();
            },
            dragFromCatalog(index) { this.dragging = {zone:'catalog',index}; },
            dragFromMenu(index) { this.dragging = {zone:'menu',index}; },
            dropAt(index) {
                if (!this.dragging) return;
                const drag = this.dragging;
                this.dragging = null;
                if (drag.zone === 'catalog') { this.add(this.catalog[drag.index],index); return; }
                const [item] = this.menu.splice(drag.index,1);
                if (drag.index < index) index--;
                this.menu.splice(index,0,item);this.changed();
            },
            filteredCatalog() {
                const search = this.search.trim().toLocaleLowerCase();
                return this.catalog.map((item,index)=>({...item,originalIndex:index}))
                    .filter(item => !search || item.label.toLocaleLowerCase().includes(search));
            },
            async save() {
                if (this.saving) return;
                this.saving = true; this.notice = ''; this.failed = false;
                try {
                    const response = await this.$wire.saveLayout(JSON.parse(JSON.stringify(this.menu)));
                    this.notice = response.message; this.failed = !response.ok;
                    if (response.ok) this.dirty = false;
                } catch (e) { this.error('No se ha podido guardar. Revisa tu conexión.'); }
                finally { this.saving = false; }
            }
        });
    </script>
    <div x-data="footerNavigationEditor(@js($items),@js($catalog),@js($audiences))" class="sqa-builder">
        <div class="sqa-builder__split" style="margin-bottom:18px">
            <div>
                <h2 class="sqa-builder__title">Botones del footer público</h2>
                <p class="sqa-builder__help">Diseño independiente del header. Botones individuales; Estado conserva el desplegable de servidores.</p>
            </div>
            <button type="button" @click="save()" :disabled="saving || !dirty" class="sqa-builder__button sqa-builder__button--accent" x-text="saving?'Guardando…':'Guardar footer'"></button>
        </div>
        <p x-show="notice" x-text="notice" :class="failed?'sqa-builder__message--error':'sqa-builder__message--ok'" class="sqa-builder__message" role="status" style="margin-bottom:14px"></p>
        <div class="sqa-builder__layout">
            <aside class="sqa-builder__panel" aria-label="Catálogo de botones del footer">
                <h3 class="sqa-builder__title">Objetos disponibles</h3>
                <p class="sqa-builder__help">Arrastra o pulsa Añadir. Puedes repetir páginas que ya estén en el header.</p>
                <input type="search" x-model="search" placeholder="Buscar páginas…" aria-label="Buscar elementos">
                <div class="sqa-builder__catalog">
                    <template x-for="item in filteredCatalog()" :key="item.originalIndex">
                        <div draggable="true" @dragstart="dragFromCatalog(item.originalIndex)" @dragend="dragging=null" class="sqa-builder__entry">
                            <div class="sqa-builder__entry-text">
                                <strong x-text="item.label"></strong>
                                <small x-text="item.type==='server_status'?'Widget Estado':(item.published?'Página / destino':'Página sin publicar')"></small>
                            </div>
                            <button type="button" @click="add(item)" class="sqa-builder__button">Añadir</button>
                        </div>
                    </template>
                </div>
                <div class="sqa-builder__divider sqa-builder__stack">
                    <h3 class="sqa-builder__title">Enlace externo</h3>
                    <input x-model="externalLabel" maxlength="80" placeholder="Nombre del botón" aria-label="Nombre externo">
                    <input x-model="externalUrl" maxlength="2048" placeholder="https://..." aria-label="URL externa">
                    <button type="button" @click="addExternal()" class="sqa-builder__button">Añadir enlace</button>
                </div>
            </aside>
            <section class="sqa-builder__panel">
                <div class="sqa-builder__split"><h3 class="sqa-builder__title">Botones colocados</h3><span class="sqa-builder__help" x-text="menu.length+' / 24 botones'"></span></div>
                <div class="sqa-builder__selection" @dragover.prevent @drop.prevent="dropAt(menu.length)">
                    <template x-for="(item,index) in menu" :key="item.key">
                        <article class="sqa-builder__card" draggable="true" @dragstart="dragFromMenu(index)" @dragend="dragging=null" @dragover.prevent.stop @drop.prevent.stop="dropAt(index)">
                            <div class="sqa-builder__card-head">
                                <div class="sqa-builder__card-title">
                                    <span class="sqa-builder__kind" x-text="item.type==='server_status'?'Widget Estado':(item.type==='external'?'Enlace externo':'Botón')"></span>
                                    <input x-model="item.label" @input="changed()" maxlength="80" aria-label="Nombre del botón">
                                    <input x-show="item.type==='external'" x-model="item.url" @input="changed()" aria-label="URL externa" placeholder="https://...">
                                </div>
                                <div class="sqa-builder__actions">
                                    <button type="button" class="sqa-builder__button" @click="move(index,-1)" :disabled="index===0" aria-label="Subir">↑</button>
                                    <button type="button" class="sqa-builder__button" @click="move(index,1)" :disabled="index===menu.length-1" aria-label="Bajar">↓</button>
                                    <button type="button" class="sqa-builder__button sqa-builder__button--danger" @click="remove(index)">Quitar</button>
                                </div>
                            </div>
                            <details class="sqa-builder__details">
                                <summary>Visibilidad por estado</summary>
                                <div class="sqa-builder__checks">
                                    <template x-for="entry in audienceEntries()" :key="entry[0]">
                                        <label><input type="checkbox" :checked="item.visible_to.includes(entry[0])" @change="toggleAudience(item,entry[0],$event.target.checked)"><span x-text="entry[1]"></span></label>
                                    </template>
                                </div>
                                <p x-show="item.type==='server_status'" class="sqa-builder__help" style="margin-top:10px">Estado conserva también su restricción de acceso a infraestructura.</p>
                            </details>
                        </article>
                    </template>
                    <p x-show="menu.length===0" class="sqa-builder__help">Arrastra aquí un elemento o pulsa Añadir.</p>
                </div>
                <p class="sqa-builder__help">Las redes sociales y el Foro antiguo permanecen fuera del constructor de botones.</p>
            </section>
        </div>
    </div>
</x-filament-panels::page>
