<x-filament-panels::page>
    @include('filament.pages.partials.sqa-navigation-builder-styles')
    <script>
        window.sqaQuickAccessEditor = (initialItems, catalog, icons, audiences, initialPosition, enabled) => ({
            menu: initialItems, catalog, icons, audiences, position: initialPosition, enabled,
            dirty: false, saving: false, notice: '', failed: false,
            search: '', iconSearch: '', externalName: '', externalUrl: '', keyCounter: 0,
            pickedIcon: null,
            iconUrl(icon) { return @js(asset('images/quick-access-icons.svg')) + '#sqa-' + icon; },
            keys() { return Object.keys(this.audiences); },
            audienceEntries() { return Object.entries(this.audiences); },
            change() { this.dirty = true; this.notice = ''; this.failed = false; },
            nextKey() { return 'qa-' + Date.now() + '-' + (++this.keyCounter); },
            defaultIcon(item) {
                const icons = {users:'users',events:'calendar-days',wiki:'book-open',home:'home',
                    normativa:'document-text',faqs:'question-mark-circle',forum:'chat-bubble-left-right',
                    activities:'clipboard-document-check',metopas:'trophy',streams:'video-camera',
                    campaigns:'flag',roulette:'arrow-path',organization:'user-group'};
                return item.type === 'server_status' ? 'server-stack' : (item.type === 'scroll_top' ? 'arrow-up' : (icons[item.destination] || 'link'));
            },
            add(item) {
                if (this.menu.length >= 12) return this.error('Máximo 12 accesos.');
                if (item.type === 'scroll_top' && this.menu.some(x => x.type === 'scroll_top')) return this.error('Volver arriba ya existe.');
                if (item.type === 'server_status' && this.menu.some(x => x.type === 'server_status')) return this.error('Estado de servidores ya está colocado.');
                this.menu.push({ type:item.type, destination:item.destination || null, label:item.label,
                    key:this.nextKey(), icon:this.defaultIcon(item), visible_to:item.type === 'server_status' ? ['ACTIVO','RECLUTA'] : this.keys() });
                this.change();
            },
            addExternal() {
                const label = this.externalName.trim(), url = this.externalUrl.trim();
                if (!label || !/^https?:\/\//i.test(url)) return this.error('Indica un nombre y una URL http(s).');
                if (this.menu.length >= 12) return this.error('Máximo 12 accesos.');
                this.menu.push({type:'external',key:this.nextKey(),label,url,icon:'link',visible_to:this.keys()});
                this.externalName = ''; this.externalUrl = ''; this.change();
            },
            error(message) { this.notice = message; this.failed = true; },
            move(index,delta) {
                const next = index+delta;
                if (next<0||next>=this.menu.length) return;
                [this.menu[index],this.menu[next]]=[this.menu[next],this.menu[index]]; this.change();
            },
            remove(index) { this.menu.splice(index,1); this.pickedIcon = null; this.change(); },
            audienceChange(item,key,checked) {
                item.visible_to = Array.isArray(item.visible_to) ? item.visible_to : [];
                if (checked && !item.visible_to.includes(key)) item.visible_to.push(key);
                if (!checked) item.visible_to=item.visible_to.filter(v=>v!==key);
                this.change();
            },
            matchingCatalog() { const q=this.search.trim().toLowerCase(); return this.catalog.filter(x=>!q||x.label.toLowerCase().includes(q)); },
            matchingIcons() {const q=this.iconSearch.trim().toLowerCase(); return this.icons.filter(x=>!q||x.includes(q));},
            pickIcon(index,name) {this.menu[index].icon=name;this.pickedIcon=null;this.change();},
            async save() {
                if (this.saving) return;
                this.saving=true;this.failed=false;this.notice='';
                try {
                    const response=await this.$wire.saveLayout(JSON.parse(JSON.stringify(this.menu)),this.position,this.enabled);
                    this.failed=!response.ok;this.notice=response.message;
                    if(response.ok)this.dirty=false;
                } catch(e) {this.error('No se pudo guardar. Comprueba la conexión.');}
                finally {this.saving=false;}
            }
        });
    </script>
    <div class="sqa-builder" x-data="sqaQuickAccessEditor(@js($items),@js($catalog),@js($icons),@js($audiences),@js($position),@js($isEnabled))">
        <div class="sqa-builder__split" style="margin-bottom:18px">
            <div>
                <h2 class="sqa-builder__title">Barra lateral de accesos rápidos</h2>
                <p class="sqa-builder__help">Independiente del header y el footer. Solo iconos visibles, con nombre al pasar el ratón y para lectores de pantalla.</p>
            </div>
            <button class="sqa-builder__button sqa-builder__button--accent" type="button" @click="save()" :disabled="saving||!dirty" x-text="saving?'Guardando…':'Guardar accesos'"></button>
        </div>
        <p class="sqa-builder__message" x-show="notice" x-text="notice" :class="failed?'sqa-builder__message--error':'sqa-builder__message--ok'" role="status" style="margin-bottom:14px"></p>
        <div class="sqa-builder__layout">
            <aside class="sqa-builder__panel" aria-label="Catálogo de accesos">
                <h3 class="sqa-builder__title">Objetos disponibles</h3>
                <p class="sqa-builder__help">Añade páginas, destinos o URLs externas. Los iconos se eligen después para cada botón. Estado de servidores es el único desplegable permitido y requiere acceso a infraestructura.</p>
                <input x-model="search" type="search" placeholder="Buscar páginas…" aria-label="Buscar destinos">
                <div class="sqa-builder__catalog">
                    <template x-for="item in matchingCatalog()" :key="item.type+(item.destination||'')">
                        <div class="sqa-builder__entry">
                            <div class="sqa-builder__entry-text"><strong x-text="item.label"></strong><small x-text="item.type==='server_status'?'Desplegable especial · acceso restringido':(item.type==='scroll_top'?'Acción especial':(item.published?'Destino':'Sin publicar'))"></small></div>
                            <button type="button" class="sqa-builder__button" @click="add(item)">Añadir</button>
                        </div>
                    </template>
                </div>
                <div class="sqa-builder__divider sqa-builder__stack">
                    <h3 class="sqa-builder__title">Enlace externo</h3>
                    <input x-model="externalName" maxlength="80" aria-label="Nombre del enlace" placeholder="Nombre del acceso">
                    <input x-model="externalUrl" maxlength="2048" aria-label="URL externa" placeholder="https://...">
                    <button type="button" class="sqa-builder__button" @click="addExternal()">Añadir enlace</button>
                </div>
            </aside>
            <section class="sqa-builder__panel">
                <div class="sqa-builder__split">
                    <h3 class="sqa-builder__title">Botones configurados</h3>
                    <span class="sqa-builder__help" x-text="menu.length+' / 12 iconos'"></span>
                </div>
                <div class="sqa-builder__options">
                    <label><input type="checkbox" x-model="enabled" @change="change()"> Activar barra</label>
                    <label>Posición
                        <select x-model="position" @change="change()" style="width:auto;min-width:140px">
                            <option value="right">Derecha</option><option value="left">Izquierda</option>
                        </select>
                    </label>
                </div>
                <div class="sqa-builder__selection">
                    <template x-for="(item,index) in menu" :key="item.key">
                        <article class="sqa-builder__card">
                            <div class="sqa-builder__card-head">
                                <div class="sqa-builder__card-title">
                                    <span class="sqa-builder__kind" x-text="item.type==='server_status'?'Widget desplegable':(item.type==='scroll_top'?'Acción':(item.type==='external'?'URL externa':'Página'))"></span>
                                    <div class="sqa-builder__preview"><svg class="sqa-builder__icon" aria-hidden="true" viewBox="0 0 24 24"><use :href="iconUrl(item.icon)"></use></svg><span x-text="item.label"></span></div>
                                </div>
                                <div class="sqa-builder__actions">
                                    <button class="sqa-builder__button" type="button" @click="move(index,-1)" :disabled="index===0" aria-label="Subir">↑</button>
                                    <button class="sqa-builder__button" type="button" @click="move(index,1)" :disabled="index===menu.length-1" aria-label="Bajar">↓</button>
                                    <button class="sqa-builder__button sqa-builder__button--danger" type="button" @click="remove(index)">Quitar</button>
                                </div>
                            </div>
                            <div class="sqa-builder__stack" style="margin-top:10px">
                                <label class="sqa-builder__label">Nombre (solo tooltip y accesibilidad)
                                    <input x-model="item.label" @input="change()" maxlength="80" :aria-label="'Nombre de acceso '+(index+1)">
                                </label>
                                <label class="sqa-builder__label" x-show="item.type==='external'">URL del acceso
                                    <input x-model="item.url" @input="change()" maxlength="2048" aria-label="URL del acceso">
                                </label>
                                <button class="sqa-builder__button" style="justify-self:start" @click="pickedIcon=pickedIcon===item.key?null:item.key;iconSearch=''" type="button">Elegir icono <span x-text="'('+item.icon+')'"></span></button>
                                <div x-show="pickedIcon===item.key" x-cloak class="sqa-builder__stack">
                                    <input x-model="iconSearch" type="search" :aria-label="'Buscar icono para '+item.label" placeholder="Buscar entre los 324 iconos…">
                                    <div class="sqa-builder__icon-grid" role="group" :aria-label="'Iconos para '+item.label">
                                        <template x-for="iconName in matchingIcons()" :key="iconName">
                                            <button class="sqa-builder__icon-choice" type="button" :title="iconName" :aria-label="iconName" :aria-pressed="item.icon===iconName" @click="pickIcon(index,iconName)">
                                                <svg class="sqa-builder__icon" viewBox="0 0 24 24" aria-hidden="true"><use :href="iconUrl(iconName)"></use></svg>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <details class="sqa-builder__details">
                                <summary>Visibilidad por estado</summary>
                                <div class="sqa-builder__checks">
                                    <template x-for="entry in audienceEntries()" :key="entry[0]">
                                        <label><input type="checkbox" :checked="item.visible_to.includes(entry[0])" @change="audienceChange(item,entry[0],$event.target.checked)"><span x-text="entry[1]"></span></label>
                                    </template>
                                </div>
                            </details>
                        </article>
                    </template>
                    <p class="sqa-builder__help" x-show="menu.length===0">Añade un destino desde el catálogo.</p>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
