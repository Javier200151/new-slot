<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/filament-event-calendar.css') }}?v={{ filemtime(public_path('css/filament-event-calendar.css')) }}">

    <div class="admin-calendar-head">
        <div>
            <span>Calendario compartido</span>
            <h2>{{ $monthName }} {{ $year }}</h2>
            <p>Los eventos se muestran igual que en el calendario público. Las reservas sirven para bloquear una fecha antes de crear el evento.</p>
        </div>
        <div class="admin-calendar-nav">
            <a href="{{ $previousMonthUrl }}" aria-label="Mes anterior">←</a>
            <a href="{{ $nextMonthUrl }}" aria-label="Mes siguiente">→</a>
        </div>
    </div>

    @if($selectedDate)
        <form wire:submit="saveReservation" class="admin-calendar-form">
            <div>
                <span>{{ $editingReservationId ? 'Editar reserva' : 'Nueva reserva' }}</span>
                <strong>{{ \Carbon\CarbonImmutable::parse($selectedDate)->format('d/m/Y') }}</strong>
            </div>

            @if($canManage)
                <label>
                    <span>Reservar para</span>
                    <select wire:model="assigneeUserId" required>
                        <option value="">Selecciona un jugador…</option>
                        @foreach($assignableUsers as $assignableUser)
                            <option value="{{ $assignableUser->id }}">{{ $assignableUser->nick }}</option>
                        @endforeach
                    </select>
                    @error('assigneeUserId')<small>{{ $message }}</small>@enderror
                </label>
            @endif

            <label class="admin-calendar-form__comment">
                <span>Comentario corto</span>
                <input wire:model="comment" maxlength="120" placeholder="Ej.: logística, instrucción, campaña…" required>
                @error('comment')<small>{{ $message }}</small>@enderror
                @error('selectedDate')<small>{{ $message }}</small>@enderror
            </label>

            <div class="admin-calendar-form__actions">
                <button type="submit">Guardar reserva</button>
                <button type="button" wire:click="cancelReservation">Cancelar</button>
            </div>
        </form>
    @endif

    <div class="admin-calendar-scroll">
        <div class="admin-calendar">
            @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)
                <div class="admin-calendar__weekday">{{ $weekday }}</div>
            @endforeach

            @foreach($calendarDays as $calendarDay)
                @php
                    $date = $calendarDay['date']->toDateString();
                    $reservation = $calendarDay['reservation'];
                @endphp
                <section @class([
                    'admin-calendar__day',
                    'is-outside' => ! $calendarDay['is_current_month'],
                    'is-today' => $calendarDay['is_today'],
                ])>
                    <header>
                        <time datetime="{{ $date }}">{{ $calendarDay['date']->day }}</time>
                        @if($calendarDay['is_current_month'] && !$reservation && ($canReserve || $canManage))
                            <button type="button" wire:click="openReservation('{{ $date }}')">Reservar</button>
                        @endif
                    </header>

                    @if($calendarDay['is_current_month'])
                        <div class="admin-calendar__items">
                            @foreach($calendarDay['events'] as $event)
                                @php
                                    $status = $event->eventStatus?->name;
                                    $canOpenPublic = in_array($status, ['ACTIVO', 'FINALIZADO', 'BORRADOR'], true);
                                @endphp
                                @if($canOpenPublic)
                                    <a
                                        class="admin-calendar__event"
                                        href="{{ route('events.show', $event) }}"
                                        style="--event-color: {{ $event->activity?->operationType?->color ?: '#f59e0b' }}"
                                        target="_blank"
                                    >
                                        <small>{{ $status }}</small>
                                        <span>{{ $event->name ?: $event->activity?->name }}</span>
                                    </a>
                                @else
                                    <div class="admin-calendar__event is-disabled" style="--event-color: #64748b">
                                        <small>{{ $status }}</small>
                                        <span>{{ $event->name ?: $event->activity?->name }}</span>
                                    </div>
                                @endif
                            @endforeach

                            @if($reservation)
                                <div class="admin-calendar__reservation">
                                    <strong>Reservado por {{ $reservation->user?->nick ?: $reservation->reserved_for_nick }}</strong>
                                    <span>— {{ $reservation->comment }}</span>
                                    @if($canManage)
                                        <div>
                                            <button type="button" wire:click="openReservation('{{ $date }}', {{ $reservation->id }})">Editar</button>
                                            <button
                                                type="button"
                                                wire:click="deleteReservation({{ $reservation->id }})"
                                                wire:confirm="¿Eliminar esta reserva?"
                                            >Eliminar</button>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
