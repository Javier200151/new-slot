@php
    $activity = $event->activity;

    $totalSlots =
        $event->getOrbatSlotsCount();

    /*
     * El controlador nos proporcionará el número exacto
     * de slots VISIBLES ocupados.
     *
     * Dejamos occupied_slots_count como fallback temporal
     * para que la tarjeta siga funcionando en cualquier
     * contexto donde todavía no venga el nuevo contador.
     */
    $occupiedSlots = min(
        $totalSlots,
        (int) (
            $event->visible_occupied_slots_count
            ?? $event->occupied_slots_count
            ?? 0
        )
    );

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

    $isCancelled = $event->isCancelled();
    $isDraft = $event->eventStatus?->name === 'BORRADOR';

    $participatingAllies = $event->slots
        ->pluck('ally')
        ->filter()
        ->unique('id')
        ->values();
@endphp

<article
    id="evento-{{ $event->id }}"
    @class([
        'event-card',
        'is-cancelled' => $isCancelled,
        'is-draft' => $isDraft,
    ])
    @style([
        '--event-color: ' . ($activity?->activityType?->color ?? '') => filled($activity?->activityType?->color),
        '--slot-occupancy: ' . $occupancyPercentage . '%',
        'opacity: .48; filter: grayscale(1); pointer-events: none; cursor: default;' => $isCancelled,
    ])
>
    @unless($isCancelled)
        <a
            href="{{ route('events.show', $event) }}"
            class="event-card__detail-link"
            aria-label="Ver evento {{ $event->name ?: $activity?->name }}"
        ></a>
    @endunless

    <div class="event-card__period">
        @if($activity?->period?->ico)
            <img
                src="{{ asset('storage/' . $activity->period->ico) }}"
                alt="Periodo {{ $activity->period->name }}"
                loading="lazy"
                width="56"
                height="56"
                style="width:56px;height:56px;max-width:56px;max-height:56px;object-fit:contain;"
            >
        @endif

        @if($activity?->platform?->image)
            <img
                src="{{ asset('storage/' . $activity->platform->image) }}"
                alt="Plataforma {{ $activity->platform->name }}"
                loading="lazy"
                width="56"
                height="56"
                style="width:56px;height:56px;max-width:56px;max-height:56px;object-fit:contain;"
            >
        @endif

        @if(! $activity?->period?->ico && ! $activity?->platform?->image)
            <span aria-hidden="true">{{ strtoupper(substr($activity?->period?->name ?? 'SQA', 0, 3)) }}</span>
        @endif
    </div>

    <div class="event-card__body">
        <div class="event-card__topline">

            <span class="event-card__type">
                {{ $activity?->activityType?->name ?? 'Sin tipo' }}
            </span>

            <span @class([
                'event-card__status',
                'is-active' => $event->eventStatus?->name === 'ACTIVO',
                'is-cancelled' => $isCancelled,
                'is-draft' => $isDraft,
            ])>
                {{ $event->eventStatus?->name }}
            </span>

            @if($event->multiclans)
                <span class="event-card__multiclans">
                    Multiclán
                </span>
            @endif

            @if(
                $event->eventStatus?->name === 'FINALIZADO'
                && ($activity?->activityType?->supportsOcap() ?? false)
                && filled($event->ocap_url)
            )
                <a
                    href="{{ $event->ocap_url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="event-card__ocap-link"
                    title="Abrir OCAP"
                    aria-label="Abrir OCAP de {{ $event->name ?: $activity?->name }}"
                >
                    OCAP ↗
                </a>
            @endif

        </div>

        <div class="event-card__title-row">
            <h3>
                @if($isCancelled)
                    <span>{{ $event->name ?: $activity?->name }}</span>
                @else
                    <a href="{{ route('events.show', $event) }}">
                        {{ $event->name ?: $activity?->name }}
                    </a>
                @endif
            </h3>

            <time datetime="{{ $event->date->toIso8601String() }}">
                {{ $formattedEventDate }}
            </time>
        </div>

        <dl class="event-card__facts">
            @if($activity?->period)
                <div>
                    <dt>Periodo</dt>
                    <dd>{{ $activity->period->name }}</dd>
                </div>
            @endif

            @if($activity?->map)
                <div>
                    <dt>Mapa</dt>
                    <dd>
                        <a href="{{ route('maps.show', $activity->map) }}" class="event-card__fact-link">
                            {{ $activity->map->name }}
                        </a>
                    </dd>
                </div>
            @endif

            @if($activity?->platform)
                <div>
                    <dt>Plataforma</dt>
                    <dd class="event-card__fact-with-icon">
                        @if($activity->platform->image)
                            <img
                                src="{{ asset('storage/' . $activity->platform->image) }}"
                                alt=""
                                loading="lazy"
                                width="20"
                                height="20"
                                style="width:20px;height:20px;max-width:20px;max-height:20px;object-fit:contain;"
                            >
                        @endif
                        <span>{{ $activity->platform->name }}</span>
                    </dd>
                </div>
            @endif

            @if($event->duration)
                <div>
                    <dt>Duración</dt>
                    <dd>{{ $event->duration }} min</dd>
                </div>
            @endif

            @if(($activity?->activityType?->usesEventResult() ?? true) && $event->eventResult)
                <div>
                    <dt>Resultado</dt>
                    <dd>{{ $event->eventResult->name }}</dd>
                </div>
            @endif

            @if($activity?->campaign)
                <div>
                    <dt>Campaña</dt>
                    <dd>
                        <a href="{{ route('campaigns.show', $activity->campaign) }}" class="event-card__campaign-link">
                            {{ $activity->campaign->name }}
                        </a>
                    </dd>
                </div>
            @endif
        </dl>

        @if($participatingAllies->isNotEmpty())
            <div class="event-card__allies" aria-label="Aliados participantes">
                <span>Aliados</span>

                <div>
                    @foreach($participatingAllies as $ally)
                        <span class="event-card__ally" title="{{ $ally->name }}">
                            @if($ally->image)
                                <img
                                    src="{{ asset('storage/' . $ally->image) }}"
                                    alt="{{ $ally->name }}"
                                    loading="lazy"
                                    width="44"
                                    height="44"
                                    style="width:44px;height:44px;max-width:44px;max-height:44px;object-fit:contain;"
                                >
                            @else
                                <strong>{{ strtoupper(substr($ally->name, 0, 2)) }}</strong>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

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
