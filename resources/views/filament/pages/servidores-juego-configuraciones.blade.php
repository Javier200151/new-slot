<div class="sqa-server-panel sqa-server-panel--settings">
    <div class="sqa-server-panel__intro">
        <div>
            <h1>Configuraciones</h1>
            <p>Gestiona el arranque automático y los avisos del grupo SERVER [=ALPHA=].</p>
        </div>
        <a href="{{ url('/admin/servidores-juego') }}" class="sqa-server-panel__config-link">Volver a servidores</a>
    </div>

    <div class="sqa-server-panel__settings-stack">
        <section class="sqa-server-panel__card sqa-server-panel__settings-card">
            <div class="sqa-server-panel__settings-head">
                <div>
                    <h2>Arranque automático</h2>
                    <p>Lo ejecuta Mission Control en Windows. Los cambios se aplican al servidor, no al programador de Laravel.</p>
                </div>
                <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" wire:click="reloadAutoSettings" wire:loading.attr="disabled">Actualizar</button>
            </div>

            @if ($autoSettingsError)
                <div class="sqa-server-panel__settings-alert" role="status">{{ $autoSettingsError }}</div>
            @endif
            <form wire:submit.prevent="saveAutoLaunchSettings" class="sqa-server-panel__settings-form">
                <label class="sqa-server-panel__settings-check">
                    <input type="checkbox" wire:model="autoLaunchEnabled" @disabled(! $autoSettingsLoaded || ! $this->canConfigureServers())>
                    <span>Levantar automáticamente los eventos de tipo OPERACIÓN</span>
                </label>
                <label class="sqa-server-panel__settings-field sqa-server-panel__settings-field--compact">
                    <span>Minutos antes del evento</span>
                    <input type="number" min="5" max="60" step="1" wire:model="autoLaunchMinutes" @disabled(! $autoSettingsLoaded || ! $this->canConfigureServers())>
                    @error('autoLaunchMinutes') <small class="sqa-server-panel__settings-error">{{ $message }}</small> @enderror
                </label>
                @if ($this->canConfigureServers())
                    <button class="sqa-server-panel__btn sqa-server-panel__btn--amber" type="submit" @disabled(! $autoSettingsLoaded) wire:loading.attr="disabled" wire:target="saveAutoLaunchSettings">Guardar en Mission Control</button>
                @endif
            </form>
            <p class="sqa-server-panel__settings-help">Mission Control solo arranca eventos ACTIVO de tipo OPERACIÓN, no sustituye los arranques manuales y respeta los servidores que ya están en uso.</p>

            <div class="sqa-server-panel__settings-events">
                <h3>Eventos del calendario</h3>
                @forelse ($autoLaunchEvents as $event)
                    @php
                        $eventId = (int) ($event['evento_id'] ?? 0);
                        $included = (bool) ($event['automatico'] ?? true);
                    @endphp
                    <div class="sqa-server-panel__settings-event" wire:key="sqa-mc-event-{{ $eventId }}">
                        <div>
                            <strong>{{ $event['nombre'] ?? 'Evento' }}</strong>
                            <small>{{ isset($event['fecha']) ? \Illuminate\Support\Carbon::parse($event['fecha'])->setTimezone('Europe/Madrid')->format('d/m/Y H:i') : '' }} · {{ $event['estado'] ?? 'pendiente' }}</small>
                            @if (! empty($event['problema']))
                                <small class="sqa-server-panel__settings-error">{{ $event['problema'] }}</small>
                            @endif
                        </div>
                        @if ($this->canConfigureServers() && $autoSettingsLoaded && $eventId > 0)
                            <label class="sqa-server-panel__settings-check sqa-server-panel__settings-check--event">
                                <input type="checkbox" @checked($included) wire:click="setAutomaticEvent({{ $eventId }}, {{ $included ? 'false' : 'true' }})" wire:loading.attr="disabled" wire:target="setAutomaticEvent">
                                <span>Automático</span>
                            </label>
                        @else
                            <span>{{ $included ? 'Incluido' : 'Excluido' }}</span>
                        @endif
                    </div>
                @empty
                    <p class="sqa-server-panel__settings-help">{{ $autoSettingsLoaded ? 'No hay eventos programados para los próximos días.' : 'Sin datos de calendario.' }}</p>
                @endforelse
            </div>
        </section>

        <section class="sqa-server-panel__card sqa-server-panel__settings-card">
            <div class="sqa-server-panel__settings-head">
                <div>
                    <h2>Telegram · servidores</h2>
                    <p>Los mensajes se envían al grupo privado SERVER [=ALPHA=] mediante el bot existente.</p>
                </div>
            </div>
            <form wire:submit.prevent="saveServerTelegramSettings" class="sqa-server-panel__settings-form">
                <label class="sqa-server-panel__settings-check">
                    <input type="checkbox" wire:model="serverNotificationsEnabled" @disabled(! $this->canConfigureServers())>
                    <span>Enviar avisos de servidores</span>
                </label>
                <div class="sqa-server-panel__settings-grid">
                    <label class="sqa-server-panel__settings-field">
                        <span>ID del grupo SERVER [=ALPHA=]</span>
                        <input type="text" wire:model="serverChatId" placeholder="-100..." maxlength="80" @disabled(! $this->canConfigureServers())>
                        @error('serverChatId') <small class="sqa-server-panel__settings-error">{{ $message }}</small> @enderror
                    </label>
                    <label class="sqa-server-panel__settings-field">
                        <span>Primera comprobación (minutos antes)</span>
                        <input type="number" wire:model="serverNoticeMinutes" min="30" max="1440" step="30" @disabled(! $this->canConfigureServers())>
                        @error('serverNoticeMinutes') <small class="sqa-server-panel__settings-error">{{ $message }}</small> @enderror
                    </label>
                </div>
                @if ($this->canConfigureServers())
                    <div class="sqa-server-panel__settings-actions">
                        <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" wire:click="discoverServerChats" wire:loading.attr="disabled">Detectar chats Telegram</button>
                        <button type="submit" class="sqa-server-panel__btn sqa-server-panel__btn--amber" wire:loading.attr="disabled" wire:target="saveServerTelegramSettings">Guardar Telegram</button>
                        <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" wire:click="sendServerTelegramTest" wire:loading.attr="disabled">Enviar prueba al grupo</button>
                    </div>
                    @if ($chatChoices !== [])
                        <label class="sqa-server-panel__settings-field">
                            <span>Chats detectados</span>
                            <select wire:model="serverChatId">
                                <option value="">Selecciona el grupo...</option>
                                @foreach ($chatChoices as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                @endif
                <p class="sqa-server-panel__settings-help">El grupo NETWORK no recibirá avisos de servidores. La comprobación de PBO/HTML se repite cada 30 minutos si falta algún archivo; se realiza hasta T−30. El bot comprueba el arranque a T−15, incluso si se ha realizado manualmente.</p>
            </form>

            <div class="sqa-server-panel__settings-preview">
                <h3>Mensajes de Telegram</h3>
                <p>Personaliza los textos. La vista previa usa datos de ejemplo y no envía mensajes.</p>
                <label class="sqa-server-panel__settings-field">
                    <span>Tipo de mensaje</span>
                    <select wire:model.live="selectedTemplate">
                        @foreach (\App\Services\MissionControl\MissionControlTelegramTemplates::labels() as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="sqa-server-panel__settings-field">
                    <span>Plantilla</span>
                    <textarea wire:key="sqa-mc-message-{{ $selectedTemplate }}" wire:model.blur="messageTemplates.{{ $selectedTemplate }}" rows="9" maxlength="3000" @disabled(! $this->canConfigureServers())></textarea>
                </label>
                <div class="sqa-server-panel__settings-actions">
                    <span class="sqa-server-panel__settings-help">Variables: {evento}, {fecha}, {antelacion}, {estado}, {detalles}, {automatico}, {proxima}, {resultado}, {tipo}, {aviso}, {url}.</span>
                    @if ($this->canConfigureServers())
                        <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" wire:click="restoreSelectedTemplate">Restaurar original</button>
                        <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" wire:click="$refresh">Actualizar vista previa</button>
                    @endif
                </div>
                <div class="sqa-server-panel__settings-message" role="region" aria-label="Vista previa del mensaje">
                    <strong>Vista previa</strong>
                    <pre>{{ $this->previewSelectedMessage() }}</pre>
                </div>
                <p class="sqa-server-panel__settings-help">Pulsa «Guardar Telegram» para conservar los cambios en las plantillas.</p>
            </div>
        </section>
    </div>
</div>
