<x-filament-panels::page>
    <div class="sqa-server-panel" x-data="sqaServersDemo()" x-cloak>
        <div class="sqa-server-panel__intro">
            <div>
                <div class="sqa-server-panel__eyebrow">SQUAD ALPHA · CONTROL</div>
                <h1>Servidores de juego</h1>
                <p>SQA-BRAIN · Panel de control de Arma 3</p>
            </div>
            <div class="sqa-server-panel__preview">
                <span class="sqa-server-panel__preview-dot"></span>
                Vista previa · Datos de ejemplo
            </div>
        </div>

        <div class="sqa-server-panel__layout">
            {{-- Columna de academias --}}
            <section class="sqa-server-panel__academies" aria-labelledby="sqa-academies-title">
                <div class="sqa-server-panel__section-title">
                    <h2 id="sqa-academies-title">Academias</h2>
                    <span>El servidor entero se reinicia los lunes a las 07:00</span>
                </div>
                <div class="sqa-server-panel__academy-list">
                    <template x-for="academy in academies" :key="academy.id">
                        <article class="sqa-server-panel__card sqa-server-panel__academy-card">
                            <div class="sqa-server-panel__card-head">
                                <div>
                                    <h3 x-text="academy.name"></h3>
                                    <p><span x-text="academy.mission"></span> · puerto <span x-text="academy.port"></span></p>
                                </div>
                                <span class="sqa-server-panel__status" :class="statusClass(academy.state)">
                                    <i></i><span x-text="statusLabel(academy.state)"></span>
                                </span>
                            </div>

                            <div class="sqa-server-panel__mini-grid">
                                <div class="sqa-server-panel__mini-stat"><span>En marcha</span><strong x-text="academy.state === 'en_marcha' ? academy.uptime : '—'"></strong></div>
                                <div class="sqa-server-panel__mini-stat"><span>Jugadores</span><strong><span x-text="academy.state === 'en_marcha' ? academy.players : 0"></span> <small>/ <span x-text="academy.max"></span></small></strong></div>
                            </div>

                            <div class="sqa-server-panel__button-grid">
                                <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--start" :disabled="academy.state === 'en_marcha'" @click="command('academia', academy.id, 'arrancar')">Arrancar</button>
                                <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--stop" :disabled="academy.state !== 'en_marcha'" @click="command('academia', academy.id, 'parar')">Parar</button>
                                <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" :disabled="academy.state !== 'en_marcha'" @click="command('academia', academy.id, 'reiniciar')">Reiniciar</button>
                            </div>

                            <div class="sqa-server-panel__restart">
                                <label class="sqa-server-panel__check-label"><input type="checkbox" x-model="academy.auto"/><span>Reinicio automático diario</span></label>
                                <label class="sqa-server-panel__time-label">a las <input type="time" x-model="academy.time" :disabled="!academy.auto" /></label>
                            </div>
                            <p class="sqa-server-panel__next" x-text="academy.auto ? 'Próximo reinicio: mañana a las ' + academy.time : 'Sin reinicio automático'"></p>
                        </article>
                    </template>
                </div>
            </section>

            {{-- Columna principal de operativos --}}
            <section class="sqa-server-panel__operations" aria-labelledby="sqa-operations-title">
                <div class="sqa-server-panel__section-title">
                    <h2 id="sqa-operations-title">Operativos</h2>
                    <span>Una sola actividad a la vez · Arma 3 Operativos · puerto 2302</span>
                </div>
                <article class="sqa-server-panel__card sqa-server-panel__operations-card">
                    <div class="sqa-server-panel__operations-status">
                        <div class="sqa-server-panel__card-head">
                            <span class="sqa-server-panel__status" :class="statusClass(operation.state)"><i></i><span x-text="statusLabel(operation.state)"></span></span>
                            <span class="sqa-server-panel__subtle" x-text="operation.state === 'en_marcha' ? 'Actividad en curso' : 'Sin actividad en curso'"></span>
                        </div>
                        <div class="sqa-server-panel__uptime">
                            <span>Tiempo en marcha</span>
                            <strong x-text="operation.state === 'en_marcha' ? operation.uptime : '00:00:00'"></strong>
                        </div>
                        <div class="sqa-server-panel__mini-grid">
                            <div class="sqa-server-panel__mini-stat"><span>Jugadores</span><strong><span x-text="operation.state === 'en_marcha' ? operation.players : 0"></span> <small>/ 128</small></strong></div>
                            <div class="sqa-server-panel__mini-stat"><span>Headless clients</span><strong><span x-text="operation.state === 'en_marcha' ? 3 : 0"></span> <small>/ 3</small></strong></div>
                        </div>
                        <div class="sqa-server-panel__current-mission">
                            <span>Misión cargada</span>
                            <strong x-text="operation.state === 'en_marcha' ? activeActivityName : 'Ninguna'"></strong>
                            <small x-text="operation.state === 'en_marcha' ? activePbo : 'Elige una actividad y pulsa Lanzar'"></small>
                        </div>
                        <div class="sqa-server-panel__operations-buttons">
                            <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--stop" :disabled="operation.state !== 'en_marcha'" @click="command('operativos', 'operativos', 'parar')">Parar</button>
                            <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" :disabled="operation.state !== 'en_marcha'" @click="command('operativos', 'operativos', 'reiniciar')">Reiniciar</button>
                        </div>
                    </div>

                    <div class="sqa-server-panel__launch">
                        <div class="sqa-server-panel__field-label" id="sqa-demo-activity-label">
                            <span>Misión de la web</span>
                            <button type="button" class="sqa-server-panel__reload" @click="reloadActivities()">Recargar</button>
                        </div>
                        <div class="sqa-server-panel__picker" @click.outside="closeActivityPicker()" @keydown.escape.window="closeActivityPicker()">
                            <button
                                type="button"
                                class="sqa-server-panel__picker-trigger"
                                id="sqa-demo-activity"
                                aria-haspopup="listbox"
                                aria-controls="sqa-demo-activity-options"
                                aria-labelledby="sqa-demo-activity-label"
                                :aria-expanded="activityPickerOpen ? 'true' : 'false'"
                                :disabled="operation.state === 'en_marcha'"
                                @click="toggleActivityPicker()"
                            >
                                <span class="sqa-server-panel__picker-current" x-text="selectedActivity ? selectedActivity.whenLabel + ' · ' + selectedActivity.name : '— Elige una misión —'"></span>
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
                                <div class="sqa-server-panel__picker-list" id="sqa-demo-activity-options" role="listbox" aria-label="Misiones disponibles">
                                    <div class="sqa-server-panel__picker-empty" x-show="filteredActivities.length === 0">No se han encontrado coincidencias.</div>
                                    <template x-for="group in filteredActivityGroups" :key="group.id">
                                        <div class="sqa-server-panel__picker-group" role="group" :aria-label="group.title">
                                            <div class="sqa-server-panel__picker-group-heading" x-text="group.title" aria-hidden="true"></div>
                                            <template x-for="activity in group.items" :key="activity.id">
                                                <button
                                                    type="button"
                                                    role="option"
                                                    tabindex="-1"
                                                    class="sqa-server-panel__picker-option"
                                                    :class="{ 'sqa-server-panel__picker-option--active': activityIndex(activity.id) === activeActivityIndex, 'sqa-server-panel__picker-option--selected': selectedId === activity.id }"
                                                    :aria-selected="selectedId === activity.id ? 'true' : 'false'"
                                                    @mouseenter="activeActivityIndex = activityIndex(activity.id)"
                                                    @click="chooseActivity(activity)"
                                                >
                                                    <span class="sqa-server-panel__picker-option-content">
                                                        <span class="sqa-server-panel__picker-option-name" x-text="activity.whenLabel && activity.group !== 'folder' ? activity.whenLabel + ' · ' + activity.name : activity.fileLabel"></span>
                                                        <small x-show="activity.group === 'folder'">Sin actividad enlazada</small>
                                                    </span>
                                                    <span class="sqa-server-panel__picker-option-status" :class="'sqa-server-panel__picker-option-status--' + syncTone(activity.sync)" :title="syncLabel(activity.sync)">
                                                        <i></i><span x-text="activity.sync === 'ok' ? 'PBO + HTML' : (activity.sync === 'partial' ? 'Incompleto' : 'Sin archivos')"></span>
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
                        <p class="sqa-server-panel__locked" x-show="operation.state === 'en_marcha'">La actividad no se puede cambiar mientras el servidor está en marcha.</p>
                        <div class="sqa-server-panel__activity-details">
                            <div class="sqa-server-panel__activity-head">
                                <h3 x-text="selectedActivity.name"></h3>
                                <span x-text="selectedActivity.event"></span>
                            </div>
                            <dl class="sqa-server-panel__details-grid">
                                <dt>Mapa</dt><dd x-text="selectedActivity.map"></dd>
                                <dt>Slots</dt><dd x-text="selectedActivity.slots"></dd>
                                <dt>Perfil</dt><dd x-text="selectedActivity.profile"></dd>
                            </dl>
                            <div class="sqa-server-panel__activity-checks">
                                <div><span class="sqa-server-panel__signal" :class="'sqa-server-panel__signal--' + (selectedActivity.pbo ? 'ok' : 'danger')" x-text="selectedActivity.pbo ? '✓' : '×'"></span><div><strong x-text="selectedActivity.pbo ? 'PBO disponible (demo)' : 'PBO no disponible'"></strong><small x-text="selectedActivity.pbo || 'Falta el archivo .pbo'"></small></div></div>
                                <div><span class="sqa-server-panel__signal" :class="'sqa-server-panel__signal--' + (selectedActivity.html ? 'ok' : 'warning')" x-text="selectedActivity.html ? '✓' : '!'" ></span><div><strong x-text="selectedActivity.html ? selectedActivity.addons + ' addons en el HTML' : 'HTML de addons pendiente'"></strong><small x-text="selectedActivity.html || 'Falta el archivo .html'"></small></div></div>
                            </div>
                        </div>
                        <label class="sqa-server-panel__check-label sqa-server-panel__stop-academies"><input type="checkbox" x-model="stopAcademies"/><span>Parar las academias durante el operativo y volver a arrancarlas al terminar</span></label>
                        <button type="button" class="sqa-server-panel__launch-button" :disabled="operation.state === 'en_marcha'" @click="command('operativos', 'operativos', 'lanzar')">
                            <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true"><path fill="currentColor" d="M8 5v14l11-7z"/></svg>
                            <span x-text="operation.state === 'en_marcha' ? 'Actividad en curso' : 'Lanzar ' + selectedActivity.name"></span>
                        </button>
                    </div>
                </article>
            </section>
        </div>

        <section class="sqa-server-panel__register" aria-labelledby="sqa-log-title">
            <div class="sqa-server-panel__section-title"><h2 id="sqa-log-title">Registro</h2><span>Acciones simuladas de esta vista previa</span></div>
            <div class="sqa-server-panel__card sqa-server-panel__log">
                <template x-for="(entry, index) in log" :key="index">
                    <div class="sqa-server-panel__log-row"><span x-text="entry.at"></span><span x-text="entry.text"></span></div>
                </template>
            </div>
        </section>

        <div class="sqa-server-panel__note">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 11v6M12 7h.01"/></svg>
            <span>Los controles son una demostración visual. No están conectados a los servidores ni guardan cambios.</span>
        </div>

        <div class="sqa-server-panel__modal-overlay" x-show="confirmation !== null" x-transition.opacity @keydown.escape.window="closeConfirm()" @click.self="closeConfirm()" style="display:none">
            <div class="sqa-server-panel__modal" role="dialog" aria-modal="true" aria-labelledby="sqa-demo-confirm-title">
                <h3 id="sqa-demo-confirm-title" x-text="confirmation?.title || ''"></h3>
                <p x-text="confirmation?.message || ''"></p>
                <div class="sqa-server-panel__modal-buttons">
                    <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" @click="closeConfirm()">Cancelar</button>
                    <button type="button" class="sqa-server-panel__btn" :class="confirmation?.danger ? 'sqa-server-panel__btn--stop' : 'sqa-server-panel__btn--amber'" @click="acceptConfirm()" x-text="confirmation?.accept || 'Confirmar'"></button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
