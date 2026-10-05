<x-filament-panels::page>
    @php
        $dashboards = $this->dashboards();
        $activeDashboard = $this->activeDashboard();
        $widgets = $this->dashboardWidgets();
        $availableWidgets = $this->availableWidgetDefinitions();
    @endphp

    <div class="ns-pd-shell">
        <section class="ns-pd-topbar">
            <div class="ns-pd-profile-picker">
                <label for="ns-dashboard-selector">Dashboard personal</label>
                <select
                    id="ns-dashboard-selector"
                    wire:change="switchDashboard($event.target.value)"
                    @disabled($editing)
                >
                    @foreach ($dashboards as $dashboard)
                        <option value="{{ $dashboard->id }}" @selected((int) $dashboard->id === (int) $activeDashboard->id)>
                            {{ $dashboard->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="ns-pd-topbar-actions">
                <x-filament::button
                    type="button"
                    :color="$editing ? 'success' : 'gray'"
                    :icon="$editing ? 'heroicon-o-check' : 'heroicon-o-pencil-square'"
                    wire:click="toggleEditing"
                >
                    {{ $editing ? 'Terminar edición' : 'Editar dashboard' }}
                </x-filament::button>
            </div>
        </section>

        @if ($editing)
            <section class="ns-pd-editor-panel">
                <div class="ns-pd-editor-block">
                    <h3>Dashboard actual</h3>
                    <p>Cambia el nombre, crea una copia o elimina este diseño. Cada dashboard pertenece únicamente a tu usuario.</p>

                    <div class="ns-pd-inline-form">
                        <input
                            type="text"
                            maxlength="80"
                            wire:model.defer="renameDashboardName"
                            placeholder="Nombre del dashboard"
                        >
                        <x-filament::button type="button" size="sm" wire:click="renameDashboard">
                            Guardar nombre
                        </x-filament::button>
                        <x-filament::button type="button" size="sm" color="gray" wire:click="duplicateDashboard">
                            Duplicar
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            size="sm"
                            color="danger"
                            wire:click="deleteDashboard"
                            wire:confirm="¿Eliminar este dashboard personal? Los demás diseños no se modificarán."
                        >
                            Eliminar
                        </x-filament::button>
                    </div>
                </div>

                <div class="ns-pd-editor-block">
                    <h3>Nuevo dashboard</h3>
                    <p>Crea otro diseño independiente y cambia entre ellos cuando quieras.</p>
                    <div class="ns-pd-inline-form">
                        <input
                            type="text"
                            maxlength="80"
                            wire:model.defer="newDashboardName"
                            placeholder="Ej. Reclutamiento"
                            wire:keydown.enter="createDashboard"
                        >
                        <x-filament::button type="button" size="sm" icon="heroicon-o-plus" wire:click="createDashboard">
                            Crear dashboard
                        </x-filament::button>
                    </div>
                </div>
            </section>

            <section class="ns-pd-catalog">
                <div class="ns-pd-section-heading">
                    <div>
                        <h3>Añadir widgets</h3>
                        <p>Solo aparecen widgets que todavía no están en este dashboard y que puedes utilizar.</p>
                    </div>
                </div>

                @if ($availableWidgets === [])
                    <div class="ns-pd-empty-small">Ya tienes añadidos todos los widgets disponibles para tu cuenta.</div>
                @else
                    <div class="ns-pd-catalog-grid">
                        @foreach ($availableWidgets as $type => $definition)
                            <article class="ns-pd-catalog-item">
                                <div>
                                    <strong>{{ $definition['label'] }}</strong>
                                    <p>{{ $definition['description'] }}</p>
                                </div>
                                <x-filament::button type="button" size="sm" color="gray" wire:click="addWidget('{{ $type }}')">
                                    Añadir
                                </x-filament::button>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if ($configuringWidgetId)
            <section class="ns-pd-config-panel">
                <div class="ns-pd-section-heading">
                    <div>
                        <h3>Configurar accesos rápidos</h3>
                        <p>Puedes usar rutas internas como <code>/admin/users</code> o URLs completas http/https.</p>
                    </div>
                </div>

                <div class="ns-pd-links-editor">
                    @foreach ($quickLinksEditor as $index => $link)
                        <div class="ns-pd-link-row" wire:key="quick-link-row-{{ $index }}">
                            <input
                                type="text"
                                maxlength="60"
                                wire:model.defer="quickLinksEditor.{{ $index }}.label"
                                placeholder="Nombre"
                            >
                            <input
                                type="text"
                                maxlength="2048"
                                wire:model.defer="quickLinksEditor.{{ $index }}.url"
                                placeholder="/admin/... o https://..."
                            >
                            <button type="button" class="ns-pd-icon-button is-danger" wire:click="removeQuickLinkRow({{ $index }})" title="Eliminar enlace">
                                ×
                            </button>
                        </div>
                    @endforeach
                </div>

                <div class="ns-pd-config-actions">
                    <x-filament::button type="button" size="sm" color="gray" wire:click="addQuickLinkRow">
                        Añadir enlace
                    </x-filament::button>
                    <x-filament::button type="button" size="sm" wire:click="saveQuickLinks">
                        Guardar accesos
                    </x-filament::button>
                    <x-filament::button type="button" size="sm" color="gray" wire:click="cancelWidgetConfiguration">
                        Cancelar
                    </x-filament::button>
                </div>
            </section>
        @endif

        @if ($widgets->isEmpty())
            <section class="ns-pd-empty">
                <strong>Este dashboard está vacío.</strong>
                <p>Entra en “Editar dashboard” para añadir los widgets que quieras.</p>
            </section>
        @else
            <div
                class="ns-pd-grid"
                x-data="{ dragged: null }"
                x-ref="dashboardGrid"
                x-on:dragend.window="
                    if (! dragged) return;
                    const order = Array.from($refs.dashboardGrid.querySelectorAll('[data-dashboard-widget-id]'))
                        .map((item) => Number(item.dataset.dashboardWidgetId));
                    dragged.classList.remove('is-dragging');
                    dragged = null;
                    $wire.saveWidgetOrder(order);
                "
            >
                @foreach ($widgets as $widget)
                    @php
                        $definition = $this->widgetDefinition($widget->type);
                    @endphp
                    @continue(!$definition)

                    @php
                        [$widgetWidth, $widgetHeight] = $this->widgetDimensions($widget);
                    @endphp
                    <section
                        class="ns-pd-widget"
                        style="--ns-pd-w: {{ $widgetWidth }}; --ns-pd-h: {{ $widgetHeight }};"
                        data-dashboard-widget-id="{{ $widget->id }}"
                        data-widget-width="{{ $widgetWidth }}"
                        data-widget-height="{{ $widgetHeight }}"
                        wire:key="dashboard-wrapper-{{ $widget->id }}"
                        x-on:dragover.prevent="
                            if (! dragged || dragged === $el) return;
                            const rect = $el.getBoundingClientRect();
                            const after = ($event.clientY > rect.top + rect.height / 2)
                                || (Math.abs($event.clientY - (rect.top + rect.height / 2)) < rect.height / 4
                                    && $event.clientX > rect.left + rect.width / 2);
                            $el.parentNode.insertBefore(dragged, after ? $el.nextSibling : $el);
                        "
                    >
                        @if ($editing)
                            <div class="ns-pd-widget-controls">
                                <button
                                    type="button"
                                    class="ns-pd-grab"
                                    draggable="true"
                                    title="Arrastrar para reordenar"
                                    x-on:dragstart="
                                        dragged = $el.closest('[data-dashboard-widget-id]');
                                        dragged.classList.add('is-dragging');
                                        $event.dataTransfer.effectAllowed = 'move';
                                    "
                                >
                                    ⋮⋮
                                </button>
                                <span class="ns-pd-widget-name">{{ $definition['label'] }}</span>
                                <div class="ns-pd-widget-control-actions">
                                    @if ($definition['configurable'] ?? false)
                                        <button type="button" class="ns-pd-icon-button" wire:click="configureWidget({{ $widget->id }})">
                                            Configurar
                                        </button>
                                    @endif
                                    <label class="ns-pd-size-control" title="Tamaño del widget: ancho × alto">
                                        <span>Tamaño</span>
                                        <select wire:change="setWidgetSize({{ $widget->id }}, $event.target.value)">
                                            @foreach ($this->widgetSizeOptions($widget->type) as $sizeValue => $sizeLabel)
                                                <option value="{{ $sizeValue }}" @selected($sizeValue === \App\Support\PersonalDashboardWidgetRegistry::normalizeSize($widget->type, $widget->size))>
                                                    {{ $sizeLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button
                                        type="button"
                                        class="ns-pd-icon-button is-danger"
                                        wire:click="removeWidget({{ $widget->id }})"
                                        wire:confirm="¿Quitar este widget de tu dashboard?"
                                    >
                                        Quitar
                                    </button>
                                </div>
                            </div>
                        @endif

                        <div class="ns-pd-widget-body">
                            @if (! empty($definition['component']))
                                @livewire($definition['component'], [], key('personal-dashboard-core-' . $widget->id))
                            @elseif ($widget->type === \App\Support\PersonalDashboardWidgetRegistry::MINI_CALENDAR)
                                @php
                                    $calendar = $this->calendarData();
                                @endphp
                                <a
                                    href="{{ $this->eventCalendarUrl() }}"
                                    class="ns-pd-calendar-link"
                                    title="Abrir Calendario de eventos"
                                >
                                    <div class="ns-pd-custom-card ns-pd-calendar-card">
                                        <div class="ns-pd-custom-header">
                                            <div>
                                                <span class="ns-pd-kicker">Eventos</span>
                                                <h3>Calendario</h3>
                                            </div>
                                            <div class="ns-pd-calendar-heading">
                                                <strong>{{ $calendar['label'] }}</strong>
                                                <span>Abrir calendario completo ↗</span>
                                            </div>
                                        </div>

                                        <div class="ns-pd-calendar-weekdays">
                                            @foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $day)
                                                <span>{{ $day }}</span>
                                            @endforeach
                                        </div>
                                        <div class="ns-pd-calendar-grid">
                                            @foreach ($calendar['weeks'] as $week)
                                                @foreach ($week as $day)
                                                    @php
                                                        $dayEvents = $day['events'];
                                                        $reservation = $day['reservation'];
                                                    @endphp
                                                    <div class="ns-pd-calendar-day {{ $day['is_current_month'] ? '' : 'is-outside' }} {{ $day['is_today'] ? 'is-today' : '' }}">
                                                        <span class="ns-pd-calendar-number">{{ $day['date']->day }}</span>

                                                        @if ($day['is_current_month'])
                                                            @foreach ($dayEvents->take(2) as $event)
                                                                <div
                                                                    class="ns-pd-calendar-event"
                                                                    style="--event-color: {{ $event->activity?->activityType?->color ?: '#f59e0b' }}"
                                                                    title="{{ $event->eventStatus?->name }} · {{ $event->name ?: $event->activity?->name }}"
                                                                >
                                                                    <small>{{ $event->eventStatus?->name }}</small>
                                                                    <span>{{ $event->name ?: $event->activity?->name }}</span>
                                                                </div>
                                                            @endforeach

                                                            @if ($dayEvents->count() > 2)
                                                                <small class="ns-pd-calendar-more">+{{ $dayEvents->count() - 2 }} eventos</small>
                                                            @endif

                                                            @if ($reservation)
                                                                <div class="ns-pd-calendar-reservation" title="{{ $reservation->comment }}">
                                                                    <strong>Reservado · {{ $reservation->user?->nick ?: $reservation->reserved_for_nick }}</strong>
                                                                    <span>{{ $reservation->comment }}</span>
                                                                </div>
                                                            @endif
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @endforeach
                                        </div>
                                    </div>
                                </a>
                            @elseif ($widget->type === \App\Support\PersonalDashboardWidgetRegistry::REMINDERS)
                                <div class="ns-pd-custom-card ns-pd-reminders-card">
                                    <div class="ns-pd-custom-header">
                                        <div>
                                            <span class="ns-pd-kicker">Personal</span>
                                            <h3>Recordatorios</h3>
                                        </div>
                                    </div>
                                    <textarea
                                        wire:model.defer="reminderText"
                                        maxlength="5000"
                                        placeholder="Escribe aquí tus recordatorios personales…"
                                    ></textarea>
                                    <div class="ns-pd-card-footer">
                                        <small>Este texto pertenece a tu dashboard personal.</small>
                                        <x-filament::button type="button" size="sm" wire:click="saveReminder">
                                            Guardar
                                        </x-filament::button>
                                    </div>
                                </div>
                            @elseif ($widget->type === \App\Support\PersonalDashboardWidgetRegistry::QUICK_LINKS)
                                @php
                                    $links = (array) (($widget->settings ?? [])['links'] ?? []);
                                @endphp
                                <div class="ns-pd-custom-card">
                                    <div class="ns-pd-custom-header">
                                        <div>
                                            <span class="ns-pd-kicker">Personal</span>
                                            <h3>Accesos rápidos</h3>
                                        </div>
                                    </div>
                                    @if ($links === [])
                                        <div class="ns-pd-card-empty">
                                            No hay enlaces configurados. Entra en modo edición y pulsa “Configurar”.
                                        </div>
                                    @else
                                        <div class="ns-pd-quick-links">
                                            @foreach ($links as $link)
                                                @php
                                                    $url = (string) ($link['url'] ?? '#');
                                                @endphp
                                                <a
                                                    href="{{ $url }}"
                                                    @if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) target="_blank" rel="noopener noreferrer" @endif
                                                >
                                                    <span>{{ $link['label'] ?? 'Enlace' }}</span>
                                                    <span aria-hidden="true">↗</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @elseif ($widget->type === \App\Support\PersonalDashboardWidgetRegistry::PROCEDURE_NOTIFICATIONS)
                                @php
                                    $procedureNotifications = $this->procedureNotifications();
                                @endphp
                                <div class="ns-pd-custom-card">
                                    <div class="ns-pd-custom-header">
                                        <div>
                                            <span class="ns-pd-kicker">Procedimientos</span>
                                            <h3>Avisos pendientes</h3>
                                        </div>
                                    </div>

                                    @forelse ($procedureNotifications as $procedureNotification)
                                        <div class="ns-pd-procedure-notice">
                                            <div>
                                                <strong>{{ $procedureNotification->title }}</strong>
                                                @if (filled($procedureNotification->body))
                                                    <p>{{ $procedureNotification->body }}</p>
                                                @endif
                                                <small>
                                                    {{ $procedureNotification->procedure?->user?->nick ?? 'Usuario' }}
                                                    · {{ $procedureNotification->created_at?->format('d/m/Y H:i') }}
                                                </small>
                                            </div>
                                            <button
                                                type="button"
                                                class="ns-pd-icon-button"
                                                wire:click="acknowledgeProcedureNotification({{ $procedureNotification->id }})"
                                            >
                                                Marcar revisado
                                            </button>
                                        </div>
                                    @empty
                                        <div class="ns-pd-card-empty">No tienes avisos de procedimientos pendientes.</div>
                                    @endforelse
                                </div>
                            @elseif ($widget->type === \App\Support\PersonalDashboardWidgetRegistry::QUICK_SEARCH)
                                @php
                                    $searchResults = $this->quickSearchResults();
                                @endphp
                                <div class="ns-pd-custom-card">
                                    <div class="ns-pd-custom-header">
                                        <div>
                                            <span class="ns-pd-kicker">Navegación</span>
                                            <h3>Búsqueda rápida</h3>
                                        </div>
                                    </div>
                                    <input
                                        class="ns-pd-search-input"
                                        type="search"
                                        wire:model.live.debounce.300ms="quickSearch"
                                        placeholder="Miembro, evento o hilo…"
                                        autocomplete="off"
                                    >

                                    @if (mb_strlen(trim($quickSearch)) >= 2)
                                        <div class="ns-pd-search-results">
                                            @forelse ($searchResults as $result)
                                                <a href="{{ $result['url'] }}">
                                                    <span class="ns-pd-search-type">{{ $result['type'] }}</span>
                                                    <span class="ns-pd-search-main">
                                                        <strong>{{ $result['label'] }}</strong>
                                                        @if ($result['meta'] !== '')
                                                            <small>{{ $result['meta'] }}</small>
                                                        @endif
                                                    </span>
                                                </a>
                                            @empty
                                                <div class="ns-pd-card-empty">No se encontraron resultados accesibles para ti.</div>
                                            @endforelse
                                        </div>
                                    @else
                                        <div class="ns-pd-card-empty">Escribe al menos 2 caracteres para buscar.</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
