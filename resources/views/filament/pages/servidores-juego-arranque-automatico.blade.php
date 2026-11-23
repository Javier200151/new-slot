        <section class="sqa-server-panel__card sqa-server-panel__settings-card sqa-server-panel__auto-card" wire:init="reloadAutoSettings">            <div class="sqa-server-panel__settings-head">
                <div>
                    <h2>Arranque automático</h2>
                    <p>Lo ejecuta Mission Control en Windows. Los cambios se aplican al servidor, no al programador de Laravel.</p>
                </div>
                <button type="button" class="sqa-server-panel__btn sqa-server-panel__btn--neutral" wire:click="reloadAutoSettings" wire:loading.attr="disabled">Actualizar</button>
            </div>

            @if (! $autoSettingsLoaded && ! $autoSettingsError)
                <p class="sqa-server-panel__settings-help" role="status">Consultando el arranque automático de Mission Control...</p>
            @endif
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
