<x-filament-panels::page>
    <div class="sqa-server-panel" x-data="sqaMissionControl()" x-cloak wire:ignore>
        <div class="sqa-server-panel__intro">
            <div>
                <h1>Servidores de juego</h1>
                <p>Estado en directo · se actualiza cada 5 s <span x-show="checkedAt">· última lectura <span x-text="checkedAt"></span></span></p>
            </div>
            <div class="sqa-server-panel__intro-indicators">
                <span class="sqa-server-panel__mode" x-show="mode" x-text="'Modo: ' + modeLabel()"></span>
                <span class="sqa-server-panel__preview" :class="connected ? 'sqa-server-panel__preview--on' : 'sqa-server-panel__preview--off'">
                    <span class="sqa-server-panel__preview-dot"></span>
                    <span x-text="!ready ? 'Conectando…' : (connected ? (readOnly ? 'Solo lectura' : 'Conectado') : 'Sin conexión')"></span>
                </span>
            </div>
        </div>

        <div class="sqa-server-panel__banners" aria-live="polite">
            <div class="sqa-server-panel__banner sqa-server-panel__banner--error" x-show="ready && !connected">
                <strong>Sin conexión con Mission Control</strong>
                <span x-text="(error || 'No se ha podido obtener el estado.') + ' Se reintentará cada 5 segundos. Los datos pueden estar desactualizados. ' + lastReading() + '.'"></span>
            </div>
            <div class="sqa-server-panel__banner sqa-server-panel__banner--warning" x-show="connected && readOnly">
                <strong>Mission Control está en modo solo lectura</strong><span>Los controles están desactivados.</span>
            </div>
            <div class="sqa-server-panel__banner sqa-server-panel__banner--warning" x-show="connected && catalogError">
                <strong>No se pudo actualizar la lista de eventos</strong><span x-text="catalogError"></span>
            </div>
        </div>
        <div class="sqa-server-panel__layout" :class="{'sqa-server-panel__layout--stale':stale}">
            {{-- Columna de academias --}}
            <section class="sqa-server-panel__academies" aria-labelledby="sqa-academies-title">
                <div class="sqa-server-panel__section-title">
                    <h2 id="sqa-academies-title">Academias</h2>
                    <span>Servidores de entrenamiento</span>
                </div>
                <div class="sqa-server-panel__academy-list">
                    <template x-for="academy in academies" :key="academy.id">
                        <article class="sqa-server-panel__card sqa-server-panel__academy-card">
                            <div class="sqa-server-panel__card-head">
                                <div>
                                    <h3 x-text="academy.name"></h3>
                                    <p><span x-show="academy.mission" x-text="academy.mission + ' · '"></span>puerto <span x-text="academy.port"></span></p>
                                </div>
                                <span class="sqa-server-panel__status" :class="statusClass(academy.state)">
                                    <i></i><span x-text="statusLabel(academy.state)"></span>
                                </span>
                            </div>

                            <div class="sqa-server-panel__mini-grid">
                                <div class="sqa-server-panel__mini-stat"><span>En marcha</span><strong x-text="onlineUptime(academy)"></strong></div>
                                <div class="sqa-server-panel__mini-stat"><span>Jugadores</span><strong x-text="playerLine(academy)"></strong></div>
                            </div>

                            <p class="sqa-server-panel__server-error" x-show="academy.lastError" x-text="'Último error: ' + academy.lastError"></p>
                            <div x-show="!academy.controlable" class="sqa-server-panel__external-note">Este servidor no se controla desde Mission Control.</div>
                            <div class="sqa-server-panel__button-grid" x-show="academy.controlable">
                                <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--start" :disabled="!allowAction(academy,'arrancar')" @click="command('academia', academy.id, 'arrancar')" x-text="isBusy(academy.id) ? 'Enviando…' : 'Arrancar'"></button>
                                <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--stop" :disabled="!allowAction(academy,'parar')" @click="command('academia', academy.id, 'parar')" x-text="isBusy(academy.id) ? 'Enviando…' : 'Parar'"></button>
                                <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" :disabled="!allowAction(academy,'reiniciar')" @click="command('academia', academy.id, 'reiniciar')" x-text="isBusy(academy.id) ? 'Enviando…' : 'Reiniciar'"></button>
                            </div>

                            <template x-if="academy.hasDaily">
                                <div>
                                    <div class="sqa-server-panel__restart">
                                        <label class="sqa-server-panel__check-label"><input type="checkbox" x-model="academy.auto" :disabled="!allowRestartConfig(academy)" @change="saveRestart(academy)"/><span>Reinicio automático diario</span></label>
                                        <label class="sqa-server-panel__time-label">a las <input type="time" x-model="academy.time" :disabled="!allowRestartConfig(academy)" @change="saveRestart(academy)" /></label>
                                    </div>
                                    <p class="sqa-server-panel__next" x-text="restartLine(academy)"></p>
                                </div>
                            </template>
                        </article>
                    </template>
                </div>
            </section>

            {{-- Columna principal de operativos --}}
            <section class="sqa-server-panel__operations" aria-labelledby="sqa-operations-title">
                <div class="sqa-server-panel__section-title">
                    <h2 id="sqa-operations-title">Operativos</h2>
                    <span>Un evento a la vez · Arma 3 Operativos · puerto 2302</span>
                </div>
                <article class="sqa-server-panel__card sqa-server-panel__operations-card">
                    <div class="sqa-server-panel__operations-status">
                        <div class="sqa-server-panel__card-head">
                            <span class="sqa-server-panel__status" :class="statusClass(operation.state)"><i></i><span x-text="statusLabel(operation.state)"></span></span>
                            <span class="sqa-server-panel__subtle" x-text="sinceLabel(operation)"></span>
                        </div>
                        <div class="sqa-server-panel__uptime">
                            <span>Tiempo en marcha</span>
                            <strong x-text="onlineUptime(operation)"></strong>
                        </div>
                        <div class="sqa-server-panel__mini-grid">
                            <div class="sqa-server-panel__mini-stat"><span>Jugadores</span><strong x-text="playerLine(operation)"></strong></div>
                            <div class="sqa-server-panel__mini-stat"><span>Headless clients</span><strong x-text="hcLine(operation)"></strong></div>
                        </div>
                        <div class="sqa-server-panel__current-mission">
                            <span>Misión cargada</span>
                            <strong x-text="operation.mission || 'Ninguna'"></strong>
                            <small x-text="operation.pbo || (operation.state === 'detenido' ? 'Elige un evento para lanzarlo' : '')"></small>
                            <small x-show="operation.launchedBy && operation.state !== 'detenido'" x-text="'Lanzado por ' + operation.launchedBy"></small>
                        </div>
                        <p class="sqa-server-panel__server-error" x-show="operation.lastError" x-text="'Último error: ' + operation.lastError"></p>
                        <div class="sqa-server-panel__external-note" x-show="!operation.controlable || operation.state === 'externo'">Servidor iniciado fuera de Mission Control. No se puede controlar desde aquí.</div>
                        <div class="sqa-server-panel__operations-buttons" x-show="operation.controlable && operation.state !== 'externo'">
                            <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--stop" :disabled="!allowAction(operation,'parar')" @click="command('operativos', 'operativos', 'parar')" x-text="isBusy('operativos') ? 'Enviando…' : 'Parar'"></button>
                            <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" :disabled="!allowAction(operation,'reiniciar')" @click="command('operativos', 'operativos', 'reiniciar')" x-text="isBusy('operativos') ? 'Enviando…' : 'Reiniciar'"></button>
                        </div>
                    </div>

                    <div class="sqa-server-panel__launch">
                        <div class="sqa-server-panel__field-label" id="sqa-demo-activity-label">
                            <span>Misión de la web</span>
                            <button type="button" class="sqa-server-panel__reload" :disabled="loading" @click="reloadActivities()">Recargar</button>
                        </div>
                        <div class="sqa-server-panel__picker" @click.outside="closeActivityPicker()" @keydown.escape.window="closeActivityPicker(true)">
                            <button
                                type="button"
                                class="sqa-server-panel__picker-trigger"
                                id="sqa-demo-activity"
                                x-ref="activityPickerTrigger"
                                aria-haspopup="listbox"
                                aria-controls="sqa-demo-activity-options"
                                aria-labelledby="sqa-demo-activity-label"
                                :aria-expanded="activityPickerOpen ? 'true' : 'false'"
                                :disabled="!canPick()"
                                @click="toggleActivityPicker()"
                            >
                                <span class="sqa-server-panel__picker-current" x-text="selectedId ? ((displayActivity.whenLabel ? displayActivity.whenLabel + ' · ' : '') + displayActivity.name) : '— Elige una misión —'"></span>
                                <svg aria-hidden="true" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            </button>

                            <div class="sqa-server-panel__picker-popup" x-show="activityPickerOpen" x-transition.opacity.duration.120ms style="display:none">
                                <div class="sqa-server-panel__picker-search">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/></svg>
                                    <input
                                        x-ref="activitySearchInput"
                                        type="search"
                                        x-model="activitySearch"
                                        autocomplete="off"
                                        spellcheck="false"
                                        placeholder="Buscar por evento, actividad o PBO..."
                                        aria-label="Buscar misiones"
                                        @keydown.arrow-down.prevent="moveActivitySelection(1)"
                                        @keydown.arrow-up.prevent="moveActivitySelection(-1)"
                                        @keydown.enter.prevent="chooseActiveActivity()"
                                    />
                                    <button type="button" x-show="activitySearch.length > 0" @click="activitySearch = ''; activeActivityIndex = 0; $refs.activitySearchInput.focus()" aria-label="Limpiar búsqueda" class="sqa-server-panel__picker-clear">×</button>
                                </div>
                                <div class="sqa-server-panel__picker-summary" x-text="activityCountLabel" aria-live="polite"></div>
                                <div class="sqa-server-panel__picker-list" id="sqa-demo-activity-options" role="listbox" aria-label="Misiones disponibles">
                                    <div class="sqa-server-panel__picker-empty" x-show="filteredActivities.length === 0" x-text="activitySearch ? 'No hay eventos que coincidan con la búsqueda.' : 'No hay eventos disponibles.'"></div>
                                    <template x-for="group in filteredActivityGroups" :key="group.id">
                                        <div class="sqa-server-panel__picker-group" role="group" :aria-label="group.title">
                                            <div class="sqa-server-panel__picker-group-heading" x-text="group.title"></div>
                                            <template x-for="activity in group.items" :key="activity.id">
                                                <button
                                                    type="button"
                                                    role="option"
                                                    :id="'sqa-mission-option-' + activity.id.replace(/[^a-z0-9_-]/gi, '-')"
                                                    tabindex="-1"
                                                    class="sqa-server-panel__picker-option"
                                                    :class="{ 'sqa-server-panel__picker-option--active': activityIndex(activity.id) === activeActivityIndex, 'sqa-server-panel__picker-option--selected': selectedId === activity.id }"
                                                    :aria-selected="selectedId === activity.id ? 'true' : 'false'"
                                                    @mouseenter="activeActivityIndex = activityIndex(activity.id)"
                                                    @click="chooseActivity(activity)"
                                                >
                                                    <span class="sqa-server-panel__picker-option-content">
                                                        <span class="sqa-server-panel__picker-option-name" x-text="activity.whenLabel && !['folder','campaign'].includes(activity.group) ? activity.whenLabel + ' · ' + activity.name : activity.name"></span>
                                                        <small x-show="activity.group === 'folder'" x-text="activity.reason || 'Sin evento asociado'"></small><small x-show="activity.group === 'campaign'">Campaña · progreso guardado en Windows</small>
                                                    </span>
                                                    <span class="sqa-server-panel__picker-option-status" :class="'sqa-server-panel__picker-option-status--' + syncTone(activity.sync)" :title="syncLabel(activity.sync)">
                                                        <i></i><span x-text="activity.mode === 'Campaña' ? (activity.sync === 'ok' ? 'Campaña lista' : 'Campaña pendiente') : (activity.sync === 'ok' ? 'PBO + HTML' : (activity.sync === 'partial' ? 'Incompleto' : 'Sin archivos'))"></span>
                                                    </span>
                                                </button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                <div class="sqa-server-panel__picker-legend">
                                    <span><i class="sqa-server-panel__legend-dot sqa-server-panel__legend-dot--ok"></i> Ambos archivos</span>
                                    <span><i class="sqa-server-panel__legend-dot sqa-server-panel__legend-dot--warning"></i> Solo uno</span>
                                    <span><i class="sqa-server-panel__legend-dot sqa-server-panel__legend-dot--danger"></i> Ninguno</span>
                                </div>
                            </div>
                        </div>
                        <p class="sqa-server-panel__locked" x-show="!!lockReason()" x-text="lockReason()"></p>
                        <div class="sqa-server-panel__activity-details" x-show="hasSelected" x-cloak>
                            <div class="sqa-server-panel__activity-head">
                                <h3 x-text="displayActivity.name"></h3>
                            </div>
                            <div class="sqa-server-panel__activity-meta">
                                <span x-text="displayActivity.event"></span>
                                <span x-show="displayActivity.mode" x-text="displayActivity.mode"></span>
                                <a x-show="displayActivity.webLink" :href="displayActivity.webLink" target="_blank" rel="noopener noreferrer">Ver en la web</a>
                            </div>

                            <p class="sqa-server-panel__campaign-progress" x-show="displayActivity.mode === 'Campaña' && displayActivity.saveInfo" x-text="displayActivity.saveInfo"></p>

                            <div class="sqa-server-panel__activity-checks">
                                <div class="sqa-server-panel__check">
                                    <span class="sqa-server-panel__signal" :class="'sqa-server-panel__signal--' + (displayActivity.pboReady ? 'ok' : 'danger')" x-text="displayActivity.pboReady ? '✓' : '×'"></span>
                                    <div>
                                        <strong x-text="displayActivity.mode === 'Campaña' ? (displayActivity.pboReady ? 'Misión de campaña en el servidor' : 'Misión de campaña no disponible') : (displayActivity.pboReady ? 'Misión en el servidor' : 'PBO no disponible')"></strong>
                                        <small x-text="displayActivity.pbo || 'Falta el archivo PBO'"></small>
                                    </div>
                                </div>
                                <div class="sqa-server-panel__check" :class="{'sqa-server-panel__check--missing':missingAddons.length > 0}">
                                    <span class="sqa-server-panel__signal" :class="'sqa-server-panel__signal--' + (missingAddons.length ? 'danger' : (addonsAvailable ? 'ok' : 'neutral'))" x-text="missingAddons.length ? '×' : (addonsAvailable ? '✓' : '·')"></span>
                                    <div>
                                        <strong x-text="addonStatusText"></strong>
                                        <small x-text="addonStatusDescription"></small>
                                    </div>
                                </div>
                                <div class="sqa-server-panel__check" x-show="serverMods.length > 0">
                                    <span class="sqa-server-panel__signal sqa-server-panel__signal--neutral">·</span>
                                    <div>
                                        <strong x-text="serverMods.length + ' server mods'"></strong>
                                        <small x-text="serverMods.join(', ')"></small>
                                    </div>
                                </div>
                            </div>

                            <div class="sqa-server-panel__addons" x-show="addonNames.length > 0">
                                <button type="button" class="sqa-server-panel__addons-toggle" :aria-expanded="showAddons ? 'true' : 'false'" @click="showAddons = !showAddons">
                                    <span x-text="showAddons ? '▾' : '▸'"></span>
                                    <span x-text="'Ver los ' + addonNames.length + ' addons'"></span>
                                </button>
                                <div class="sqa-server-panel__addons-list" x-show="showAddons" x-transition.opacity.duration.120ms>
                                    <template x-for="name in addonNames" :key="name">
                                        <div class="sqa-server-panel__addon-row" :class="{'sqa-server-panel__addon-row--missing':missingAddons.includes(name)}">
                                            <span x-text="name"></span>
                                            <span x-text="missingAddons.includes(name) ? 'Falta' : 'Instalado'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <p class="sqa-server-panel__hint-box" x-show="displayActivity.reason" x-text="displayActivity.reason"></p>
                        </div>
                        <p class="sqa-server-panel__locked" x-show="!hasSelected">Selecciona una misión para ver sus datos.</p>
                        <label class="sqa-server-panel__check-label sqa-server-panel__stop-academies"><input type="checkbox" x-model="stopAcademies" :disabled="!canLaunchSelected"/><span>Parar las academias durante el evento y volver a arrancarlas al terminar</span></label>
                        <button type="button" class="sqa-server-panel__launch-button" :disabled="!canLaunchSelected" @click="command('operativos', 'operativos', 'lanzar')">
                            <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>
                            <span x-text="isBusy('operativos') ? 'Enviando…' : launchLabel()"></span>
                        </button>
                    </div>
                </article>
            </section>
        </div>

        <section class="sqa-server-panel__register" aria-labelledby="sqa-log-title">
            <div class="sqa-server-panel__section-title"><h2 id="sqa-log-title">Registro</h2><span>Acciones enviadas desde la web</span></div>
            <div class="sqa-server-panel__card sqa-server-panel__log">
                <template x-for="(entry, index) in log" :key="index">
                    <div class="sqa-server-panel__log-row"><span x-text="entry.at"></span><span x-text="entry.text"></span></div>
                </template>
            </div>
        </section>

        <div class="sqa-server-panel__footer-meta" x-show="connected">
            SQA Mission Control <span x-show="version" x-text="'· versión ' + version"></span>
            <span x-show="mode" x-text="'· modo ' + mode"></span>
            <span x-show="readOnly">· solo lectura</span>
        </div>

        <div class="sqa-server-panel__toasts" aria-live="polite">
            <template x-for="toast in toasts" :key="toast.id">
                <button type="button" class="sqa-server-panel__toast" :class="'sqa-server-panel__toast--' + toast.type" :role="toast.type === 'error' ? 'alert' : 'status'" @click="dismissNotice(toast.id)" :title="'Clic para cerrar'">
                    <span x-text="toast.message"></span><span aria-hidden="true">×</span>
                </button>
            </template>
        </div>

        <dialog class="sqa-server-panel__modal" x-ref="serverConfirm" aria-labelledby="sqa-confirm-title" aria-describedby="sqa-confirm-description" @cancel.prevent="closeConfirm()">
            <div class="sqa-server-panel__modal-body">
                <h3 id="sqa-confirm-title" x-text="confirmation?.title || ''"></h3>
                <p id="sqa-confirm-description" x-text="confirmation?.message || ''"></p>
                <dl class="sqa-server-panel__modal-rows" x-show="confirmation?.rows?.length">
                    <template x-for="(row,index) in (confirmation?.rows || [])" :key="index">
                        <div class="sqa-server-panel__modal-row"><dt x-text="row[0]"></dt><dd :class="{'sqa-server-panel__modal-highlight':row[2] === 'highlight'}" x-text="row[1]"></dd></div>
                    </template>
                </dl>
                <p class="sqa-server-panel__modal-warning" x-show="confirmation?.warning" x-text="confirmation?.warning || ''"></p>
                <p class="sqa-server-panel__modal-warning" role="status" x-show="confirmationProblem" x-text="confirmationProblem"></p>
                <div class="sqa-server-panel__modal-buttons">
                    <button type="button" x-ref="cancelServerConfirm" autofocus class="sqa-server-panel__btn sqa-server-panel__btn--neutral" @click="closeConfirm()">Cancelar</button>
                    <button type="button" class="sqa-server-panel__btn" :class="confirmation?.danger ? 'sqa-server-panel__btn--danger' : 'sqa-server-panel__btn--amber'" @click="acceptConfirm()" x-text="confirmation?.accept || 'Confirmar'" :disabled="!!confirmationProblem"></button>
                </div>
            </div>
        </dialog>
    </div>
</x-filament-panels::page>
