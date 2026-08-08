@php
    $operation = $event->operation;
    $occupiedSlots = (int) $event->occupied_slots_count;
    $totalSlots = $event->getOrbatSlotsCount();
    $occupancyPercentage = $totalSlots > 0
        ? min(100, (int) round(($occupiedSlots / $totalSlots) * 100))
        : 0;
    $weekdayNames = [
        'Domingo',
        'Lunes',
        'Martes',
        'Miércoles',
        'Jueves',
        'Viernes',
        'Sábado',
    ];
    $formattedEventDate = $weekdayNames[$event->date->dayOfWeek]
        . ' '
        . $event->date->format('d/m/y H:i')
        . 'H';
@endphp

<article
    id="evento-{{ $event->id }}"
    class="event-card"
    @style([
        '--event-color: ' . ($operation?->operationType?->color ?? '') => filled($operation?->operationType?->color),
        '--slot-occupancy: ' . $occupancyPercentage . '%',
    ])
>
    <a
        href="{{ route('events.show', $event) }}"
        class="event-card__detail-link"
        aria-label="Ver evento {{ $event->name ?: $operation?->name }}"
    ></a>

    <div class="event-card__period">
        @if($operation?->period?->ico)
            <img
                src="{{ asset('storage/' . $operation->period->ico) }}"
                alt="Periodo {{ $operation->period->name }}"
                loading="lazy"
            >
        @endif

        @if($operation?->platform?->image)
            <img
                src="{{ asset('storage/' . $operation->platform->image) }}"
                alt="Plataforma {{ $operation->platform->name }}"
                loading="lazy"
            >
        @endif

        @if(! $operation?->period?->ico && ! $operation?->platform?->image)
            <span aria-hidden="true">{{ strtoupper(substr($operation?->period?->name ?? 'SQA', 0, 3)) }}</span>
        @endif
    </div>

    <div class="event-card__body">
        <div class="event-card__topline">
            <span class="event-card__type">{{ $operation?->operationType?->name ?? 'Sin tipo' }}</span>
            <span @class([
                'event-card__status',
                'is-active' => $event->eventStatus?->name === 'ACTIVO',
            ])>
                {{ $event->eventStatus?->name }}
            </span>
        </div>

        <div class="event-card__title-row">
            <h3>
                <a href="{{ route('events.show', $event) }}">
                    {{ $event->name ?: $operation?->name }}
                </a>
            </h3>

            <time datetime="{{ $event->date->toIso8601String() }}">
                {{ $formattedEventDate }}
            </time>
        </div>

        <dl class="event-card__facts">
            @if($operation?->period)
                <div>
                    <dt>Periodo</dt>
                    <dd>{{ $operation->period->name }}</dd>
                </div>
            @endif

            @if($operation?->map)
                <div>
                    <dt>Mapa</dt>
                    <dd>{{ $operation->map->name }}</dd>
                </div>
            @endif

            @if($operation?->platform)
                <div>
                    <dt>Plataforma</dt>
                    <dd>{{ $operation->platform->name }}</dd>
                </div>
            @endif

            @if($event->duration)
                <div>
                    <dt>Duración</dt>
                    <dd>{{ $event->duration }} min</dd>
                </div>
            @endif

            @if($event->eventResult)
                <div>
                    <dt>Resultado</dt>
                    <dd>{{ $event->eventResult->name }}</dd>
                </div>
            @endif

            @if($operation?->campaign)
                <div>
                    <dt>Campaña</dt>
                    <dd>
                        <a href="{{ route('campaigns.show', $operation->campaign) }}" class="event-card__campaign-link">
                            {{ $operation->campaign->name }}
                        </a>
                    </dd>
                </div>
            @endif
        </dl>

        <div class="event-card__slots">
            <div>
                <span>Ocupación de slots</span>
                <strong>{{ $occupiedSlots }} / {{ $totalSlots }}</strong>
            </div>

            <div
                class="event-card__progress"
                role="progressbar"
                aria-label="Slots ocupados"
                aria-valuemin="0"
                aria-valuemax="{{ $totalSlots }}"
                aria-valuenow="{{ $occupiedSlots }}"
            >
                <span></span>
            </div>
        </div>
    </div>
</article>
