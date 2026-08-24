@extends('layouts.metopas')

@php
    $dayOrNight = match ($operation->day_or_night) {
        'day' => 'Día',
        'night' => 'Noche',
        'both' => 'Día y noche',
        default => null,
    };

    $operationDays = $operation->days
        ->pluck('name')
        ->filter()
        ->join(', ');
@endphp

@section('title', $operation->name)

@section(
    'meta-description',
    'Información, briefing y ORBAT del operativo ' . $operation->name . '.'
)

@section(
    'body-class',
    'event-detail-body operations-body'
)

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/events.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/operations.css') }}"
    >
@endpush


@section('content')

    <article
        class="event-detail"
        @style([
            '--event-color: '
            . (
                $operation->operationType?->color
                ?? ''
            )
            => filled(
                $operation->operationType?->color
            ),
        ])
    >

        <div class="container event-detail__container">

            {{-- =====================================================
                MIGAS DE PAN
            ====================================================== --}}

            <nav
                class="event-detail__breadcrumb"
                aria-label="Migas de pan"
            >
                <a href="{{ route('operations.index') }}">
                    Operativos
                </a>

                <span aria-hidden="true">
                    /
                </span>

                <span>
                    {{ $operation->name }}
                </span>
            </nav>


            {{-- =====================================================
                HERO
            ====================================================== --}}

            <header class="event-detail__hero">

                <div class="event-detail__hero-copy">

                    <div class="event-detail__eyebrow">

                        <span>
                            {{ $operation->operationType?->name
                                ?? 'Operativo'
                            }}
                        </span>

                        @if($operation->operationStatus)

                            <span
                                @class([
                                    'is-active' =>
                                        $operation
                                            ->operationStatus
                                            ?->name === 'ACTIVO',
                                ])
                            >
                                {{ $operation
                                    ->operationStatus
                                    ->name
                                }}
                            </span>

                        @endif

                    </div>


                    <h1>
                        {{ $operation->name }}
                    </h1>


                    <nav
                        class="event-detail__section-nav"
                        aria-label="Secciones del operativo"
                    >

                        @if($descriptionSections->isNotEmpty())
                            <a href="#briefing">
                                Briefing
                            </a>
                        @endif

                        <a href="#orbat">
                            ORBAT
                        </a>

                        @if($radioNetworks->isNotEmpty())
                            <a href="#comunicaciones">
                                Comunicaciones
                            </a>
                        @endif

                        @if($addons->isNotEmpty())
                            <a href="#addons">
                                Addons
                            </a>
                        @endif

                        @if($operationEvents->isNotEmpty())
                            <a href="#eventos-operativo">
                                Eventos
                            </a>
                        @endif

                    </nav>

                </div>


                @if($operation->image)

                    <figure class="event-detail__cover">

                        <img
                            src="{{ asset(
                                'storage/' . $operation->image
                            ) }}"
                            alt="{{ $operation->name }}"
                        >

                    </figure>

                @endif

            </header>


            {{-- =====================================================
                DATOS DEL OPERATIVO
            ====================================================== --}}

            <section
                id="datos-operativo"
                class="event-detail__facts"
                aria-label="Datos del operativo"
            >

                @if($operation->platform)

                    <div>
                        <dt>Plataforma</dt>

                        <dd>
                            {{ $operation->platform->name }}
                        </dd>
                    </div>

                @endif


                @if($operation->period)

                    <div>
                        <dt>Periodo</dt>

                        <dd>
                            {{ $operation->period->name }}
                        </dd>
                    </div>

                @endif


                @if($operation->map)

                    <div>
                        <dt>Mapa</dt>

                        <dd>
                            <a
                                href="{{ route(
                                    'maps.show',
                                    $operation->map
                                ) }}"
                            >
                                {{ $operation->map->name }}
                            </a>
                        </dd>
                    </div>

                @endif


                @if(filled($dayOrNight))

                    <div>
                        <dt>Ambientación</dt>

                        <dd>
                            {{ $dayOrNight }}
                        </dd>
                    </div>

                @endif


                @if(filled($operationDays))

                    <div>
                        <dt>Días</dt>

                        <dd>
                            {{ $operationDays }}
                        </dd>
                    </div>

                @endif


                @if($operation->editor)

                    <div>
                        <dt>Editor</dt>

                        <dd>
                            <x-user-link
                                :user="$operation->editor"
                                style="color: {{ $operation->editor->getFrontendColor() }};"
                            />
                        </dd>
                    </div>

                @endif


                @if($operation->campaign)

                    <div>
                        <dt>Campaña</dt>

                        <dd>
                            <a
                                href="{{ route(
                                    'campaigns.show',
                                    $operation->campaign
                                ) }}"
                            >
                                {{ $operation->campaign->name }}
                            </a>
                        </dd>
                    </div>

                @endif

            </section>


            {{-- =====================================================
                OPCIONES
            ====================================================== --}}

            <section
                class="event-detail__options"
                aria-label="Opciones del operativo"
            >

                <span
                    @class([
                        'is-enabled' => $operation->ocap,
                    ])
                >
                    OCAP
                </span>

                <span
                    @class([
                        'is-enabled' => $operation->respawn,
                    ])
                >
                    Respawn
                </span>

                <span
                    @class([
                        'is-enabled' => $operation->jip,
                    ])
                >
                    JIP
                </span>

            </section>


            {{-- =====================================================
                BRIEFING
            ====================================================== --}}

            @if($descriptionSections->isNotEmpty())

                <section
                    id="briefing"
                    class="event-detail__section"
                >

                    <header>
                        <span>
                            Briefing
                        </span>
                    </header>


                    <div class="event-detail__descriptions">

                        @foreach($descriptionSections as $section)

                            @php
                                $image =
                                    $section['image']
                                    ?? null;

                                $imagePosition =
                                    $section[
                                        'image_position'
                                    ]
                                    ?? 'left';

                                $imageAlignment =
                                    $section[
                                        'image_alignment'
                                    ]
                                    ?? 'left';

                                $imageWidth =
                                    $section[
                                        'image_width'
                                    ]
                                    ?? '40';

                                $imageCaption =
                                    $section[
                                        'image_caption'
                                    ]
                                    ?? null;
                            @endphp


                            <section>

                                <h3>
                                    {{ $section['title'] }}
                                </h3>


                                <div
                                    class="
                                        briefing-section

                                        @if($image)
                                            briefing-section--with-image
                                            briefing-section--{{ $imagePosition }}

                                            @if(
                                                in_array(
                                                    $imagePosition,
                                                    ['top', 'bottom'],
                                                    true
                                                )
                                            )
                                                briefing-section--align-{{ $imageAlignment }}
                                            @endif
                                        @endif
                                    "
                                    style="
                                        --briefing-image-width:
                                        {{ $imageWidth }}%;
                                    "
                                >

                                    @if($image)

                                        <figure
                                            class="briefing-section__image"
                                        >

                                            <img
                                                src="{{ $image }}"
                                                alt="{{ $imageCaption ?? '' }}"
                                                loading="lazy"
                                            >

                                            @if(filled($imageCaption))

                                                <figcaption>
                                                    {{ $imageCaption }}
                                                </figcaption>

                                            @endif

                                        </figure>

                                    @endif


                                    <div
                                        class="
                                            briefing-section__content
                                            event-rich-content
                                        "
                                    >

                                        <section>

                                            <div class="event-rich-content">
                                                {{ $section['content'] }}
                                            </div>

                                        </section>

                                    </div>

                                </div>

                            </section>

                        @endforeach

                    </div>

                </section>

            @endif


            {{-- =====================================================
                ORBAT
            ====================================================== --}}

            <section
                id="orbat"
                class="
                    event-detail__section
                    event-detail__orbat
                "
                aria-labelledby="operation-orbat-title"
            >

                <header>
                    <span id="operation-orbat-title">
                        ORBAT
                    </span>
                </header>


                @if($visibleOrbatGroups->isEmpty())

                    <div class="events-empty">

                        <strong>
                            ORBAT no disponible
                        </strong>

                        <p>
                            Este operativo todavía no tiene
                            grupos visibles en su ORBAT.
                        </p>

                    </div>

                @else

                    <div class="event-orbat">

                        @foreach($visibleOrbatGroups as $group)

                            <section class="event-orbat__group">

                                <header>

                                    <div>

                                        <span>
                                            Grupo
                                        </span>

                                        <h3>
                                            {{ $group['name']
                                                ?? 'Grupo sin nombre'
                                            }}
                                        </h3>

                                    </div>


                                    @if($group['faction'])

                                        <div class="event-orbat__faction">

                                            @if(
                                                $group[
                                                    'faction'
                                                ]
                                                ->army
                                                ?->image
                                            )

                                                <img
                                                    src="{{ asset(
                                                        'storage/'
                                                        . $group[
                                                            'faction'
                                                        ]
                                                        ->army
                                                        ->image
                                                    ) }}"
                                                    alt="{{ $group[
                                                        'faction'
                                                    ]
                                                        ->army
                                                        ->name
                                                    }}"
                                                    class="event-orbat__army-logo"
                                                >

                                            @endif


                                            <div
                                                class="
                                                    event-orbat__faction-copy
                                                "
                                            >

                                                <strong>
                                                    {{ $group[
                                                        'faction'
                                                    ]
                                                        ->name
                                                    }}
                                                </strong>

                                                @if(
                                                    $group[
                                                        'faction'
                                                    ]
                                                    ->army
                                                )

                                                    <span>
                                                        {{ $group[
                                                            'faction'
                                                        ]
                                                            ->army
                                                            ->name
                                                        }}
                                                    </span>

                                                @endif

                                            </div>

                                        </div>

                                    @endif

                                </header>


                                @if($group['slots']->isEmpty())

                                    <p class="event-orbat__empty">
                                        Este grupo no tiene slots visibles.
                                    </p>

                                @else

                                    <div class="event-orbat__slots">

                                        @foreach($group['slots'] as $slot)

                                            <div class="event-orbat__slot">

                                                <div
                                                    class="
                                                        event-orbat__slot-info
                                                    "
                                                >

                                                    <strong>
                                                        {{ $slot['name']
                                                            ?? 'Slot sin nombre'
                                                        }}
                                                    </strong>

                                                    <span>
                                                        {{ $slot[
                                                            'slot_type'
                                                        ]?->name
                                                            ?? 'Sin tipo'
                                                        }}
                                                    </span>

                                                </div>

                                            </div>

                                        @endforeach

                                    </div>

                                @endif

                            </section>

                        @endforeach

                    </div>

                @endif

            </section>


            {{-- =====================================================
                COMUNICACIONES
            ====================================================== --}}

            @if($radioNetworks->isNotEmpty())

                <details
                    id="comunicaciones"
                    class="
                        event-detail__section
                        event-detail__collapsible
                    "
                >

                    <summary
                        class="
                            event-detail__collapsible-summary
                        "
                    >

                        <div>

                            <span>
                                Comunicaciones
                            </span>

                            <small>
                                Radios y frecuencias del operativo
                            </small>

                        </div>


                        <strong>
                            {{ $radioNetworks->count() }}

                            {{ $radioNetworks->count() === 1
                                ? 'red'
                                : 'redes'
                            }}
                        </strong>

                    </summary>


                    <div
                        class="
                            event-detail__collapsible-content
                        "
                    >

                        <div class="event-detail__table-wrap">

                            <table class="event-detail__table">

                                <thead>

                                    <tr>
                                        <th>Red</th>
                                        <th>Radio</th>
                                        <th>Configuración</th>
                                        <th>Notas</th>
                                    </tr>

                                </thead>


                                <tbody>

                                    @foreach($radioNetworks as $network)

                                        <tr>

                                            <td>
                                                <strong>
                                                    {{ $network[
                                                        'name'
                                                    ]
                                                        ?? 'Sin nombre'
                                                    }}
                                                </strong>
                                            </td>


                                            <td>
                                                {{ $network[
                                                    'radio_model_name'
                                                ]
                                                    ?? '—'
                                                }}
                                            </td>


                                            <td>

                                                @foreach(
                                                    (
                                                        $network[
                                                            'configuration'
                                                        ]
                                                        ?? []
                                                    )
                                                    as $key => $value
                                                )

                                                    @if(filled($value))

                                                        <span>

                                                            {{
                                                                match ($key) {
                                                                    'channel' =>
                                                                        'Canal',

                                                                    'block' =>
                                                                        'Bloque',

                                                                    'frequency' =>
                                                                        'Frecuencia',

                                                                    default =>
                                                                        ucfirst(
                                                                            $key
                                                                        ),
                                                                }
                                                            }}:

                                                            {{ $value }}

                                                            {{
                                                                $key ===
                                                                'frequency'
                                                                    ? ' MHz'
                                                                    : ''
                                                            }}

                                                        </span>

                                                    @endif

                                                @endforeach

                                            </td>


                                            <td>
                                                {{ $network[
                                                    'notes'
                                                ]
                                                    ?? '—'
                                                }}
                                            </td>

                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    </div>

                </details>

            @endif


            {{-- =====================================================
                ADDONS
            ====================================================== --}}

            @if($addons->isNotEmpty())

                <details
                    id="addons"
                    class="
                        event-detail__section
                        event-detail__collapsible
                    "
                >

                    <summary
                        class="
                            event-detail__collapsible-summary
                        "
                    >

                        <div>

                            <span>
                                Addons
                            </span>

                            <small>
                                Mods utilizados por el operativo
                            </small>

                        </div>


                        <strong>
                            {{ $addons->count() }}

                            {{ $addons->count() === 1
                                ? 'addon'
                                : 'addons'
                            }}
                        </strong>

                    </summary>


                    <div
                        class="
                            event-detail__collapsible-content
                        "
                    >

                        <div class="event-detail__table-wrap">

                            <table
                                class="
                                    event-detail__table
                                    event-detail__addons-table
                                "
                            >

                                <thead>

                                    <tr>
                                        <th>Addon</th>
                                    </tr>

                                </thead>


                                <tbody>

                                    @foreach($addons as $addon)

                                        <tr>
                                            <td>
                                                {{ $addon->name }}
                                            </td>
                                        </tr>

                                    @endforeach

                                </tbody>

                            </table>

                        </div>

                    </div>

                </details>

            @endif


            {{-- =====================================================
                EVENTOS DEL OPERATIVO
            ====================================================== --}}

            <section
                id="eventos-operativo"
                class="event-detail__section"
                aria-labelledby="operation-events-title"
            >

                <header>

                    <span>
                        Historial
                    </span>

                    <h2 id="operation-events-title">
                        Eventos de este operativo
                    </h2>

                </header>


                @if($operationEvents->isEmpty())

                    <div class="events-empty">

                        <strong>
                            Este operativo todavía no se ha jugado
                        </strong>

                        <p>
                            Cuando se cree un evento asociado,
                            aparecerá aquí.
                        </p>

                    </div>

                @else


                    {{-- ===============================
                        PRÓXIMOS EVENTOS
                    ================================ --}}

                    @if($upcomingEvents->isNotEmpty())

                        <details
                            class="
                                event-detail__collapsible
                                event-detail__section
                            "
                            open
                        >

                            <summary
                                class="
                                    event-detail__collapsible-summary
                                "
                            >

                                <div>

                                    <span>
                                        Próximos eventos
                                    </span>

                                    <small>
                                        Eventos programados
                                    </small>

                                </div>


                                <strong>
                                    {{ $upcomingEvents->count() }}
                                </strong>

                            </summary>


                            <div
                                class="
                                    event-detail__collapsible-content
                                "
                            >

                                <div class="events-list">

                                    @foreach(
                                        $upcomingEvents
                                        as $event
                                    )

                                        @php
                                            $weekdayNames = [
                                                'Domingo',
                                                'Lunes',
                                                'Martes',
                                                'Miércoles',
                                                'Jueves',
                                                'Viernes',
                                                'Sábado',
                                            ];

                                            $formattedDate =
                                                $weekdayNames[
                                                    $event
                                                        ->date
                                                        ->dayOfWeek
                                                ]
                                                . ' '
                                                . $event
                                                    ->date
                                                    ->format(
                                                        'd/m/Y H:i'
                                                    )
                                                . 'H';
                                        @endphp


                                        <article
                                            class="event-card"
                                            @style([
                                                '--event-color: '
                                                . (
                                                    $operation
                                                        ->operationType
                                                        ?->color
                                                    ?? ''
                                                )
                                                => filled(
                                                    $operation
                                                        ->operationType
                                                        ?->color
                                                ),
                                            ])
                                        >

                                            <a
                                                href="{{ route(
                                                    'events.show',
                                                    $event
                                                ) }}"
                                                class="
                                                    event-card__detail-link
                                                "
                                                aria-label="Ver evento {{ $event->name ?: $operation->name }}"
                                            ></a>


                                            <div
                                                class="
                                                    event-card__period
                                                "
                                            >

                                                @if(
                                                    $operation
                                                        ->period
                                                        ?->ico
                                                )

                                                    <img
                                                        src="{{ asset(
                                                            'storage/'
                                                            . $operation
                                                                ->period
                                                                ->ico
                                                        ) }}"
                                                        alt="{{ $operation
                                                            ->period
                                                            ->name
                                                        }}"
                                                    >

                                                @endif


                                                @if(
                                                    $operation
                                                        ->platform
                                                        ?->image
                                                )

                                                    <img
                                                        src="{{ asset(
                                                            'storage/'
                                                            . $operation
                                                                ->platform
                                                                ->image
                                                        ) }}"
                                                        alt="{{ $operation
                                                            ->platform
                                                            ->name
                                                        }}"
                                                    >

                                                @endif

                                            </div>


                                            <div class="event-card__body">

                                                <div class="event-card__topline">

                                                    <span class="event-card__type">
                                                        {{ $operation->operationType?->name ?? 'Operativo' }}
                                                    </span>

                                                    @if($event->eventStatus)

                                                        <span
                                                            @class([
                                                                'event-card__status',
                                                                'is-active' =>
                                                                    $event->eventStatus?->name === 'ACTIVO',
                                                            ])
                                                        >
                                                            {{ $event->eventStatus->name }}
                                                        </span>

                                                    @endif

                                                    @if(
                                                        $event->eventStatus?->name === 'FINALIZADO'
                                                        && filled($event->ocap_url)
                                                    )

                                                        <a
                                                            href="{{ $event->ocap_url }}"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="event-card__ocap-link"
                                                            title="Abrir OCAP"
                                                            aria-label="Abrir OCAP de {{ $event->name ?: $operation->name }}"
                                                        >
                                                            OCAP ↗
                                                        </a>

                                                    @endif

                                                </div>


                                                <div
                                                    class="
                                                        event-card__title-row
                                                    "
                                                >

                                                    <h3>
                                                        <a
                                                            href="{{ route(
                                                                'events.show',
                                                                $event
                                                            ) }}"
                                                        >
                                                            {{ $event->name
                                                                ?: $operation->name
                                                            }}
                                                        </a>
                                                    </h3>


                                                    <time
                                                        datetime="{{ $event
                                                            ->date
                                                            ->toIso8601String()
                                                        }}"
                                                    >
                                                        {{ $formattedDate }}
                                                    </time>

                                                </div>


                                                <dl
                                                    class="
                                                        event-card__facts
                                                    "
                                                >

                                                    @if($event->duration)

                                                        <div>
                                                            <dt>
                                                                Duración
                                                            </dt>

                                                            <dd>
                                                                {{ $event
                                                                    ->duration
                                                                }}
                                                                min
                                                            </dd>
                                                        </div>

                                                    @endif


                                                    @if($event->eventResult)

                                                        <div>
                                                            <dt>
                                                                Resultado
                                                            </dt>

                                                            <dd>
                                                                {{ $event
                                                                    ->eventResult
                                                                    ->name
                                                                }}
                                                            </dd>
                                                        </div>

                                                    @endif

                                                </dl>

                                            </div>

                                        </article>

                                    @endforeach

                                </div>

                            </div>

                        </details>

                    @endif


                    {{-- ===============================
                        HISTÓRICO
                    ================================ --}}

                    @if($pastEvents->isNotEmpty())

                        <details
                            class="
                                event-detail__collapsible
                                event-detail__section
                            "
                            open
                        >

                            <summary
                                class="
                                    event-detail__collapsible-summary
                                "
                            >

                                <div>

                                    <span>
                                        Histórico
                                    </span>

                                    <small>
                                        Todas las veces que se ha
                                        jugado este operativo
                                    </small>

                                </div>


                                <strong>
                                    {{ $pastEvents->count() }}
                                </strong>

                            </summary>


                            <div
                                class="
                                    event-detail__collapsible-content
                                "
                            >

                                <div class="events-list">

                                    @foreach(
                                        $pastEvents
                                        as $event
                                    )

                                        @php
                                            $weekdayNames = [
                                                'Domingo',
                                                'Lunes',
                                                'Martes',
                                                'Miércoles',
                                                'Jueves',
                                                'Viernes',
                                                'Sábado',
                                            ];

                                            $formattedDate =
                                                $weekdayNames[
                                                    $event
                                                        ->date
                                                        ->dayOfWeek
                                                ]
                                                . ' '
                                                . $event
                                                    ->date
                                                    ->format(
                                                        'd/m/Y H:i'
                                                    )
                                                . 'H';
                                        @endphp


                                        <article
                                            class="event-card"
                                            @style([
                                                '--event-color: '
                                                . (
                                                    $operation
                                                        ->operationType
                                                        ?->color
                                                    ?? ''
                                                )
                                                => filled(
                                                    $operation
                                                        ->operationType
                                                        ?->color
                                                ),
                                            ])
                                        >

                                            <a
                                                href="{{ route(
                                                    'events.show',
                                                    $event
                                                ) }}"
                                                class="
                                                    event-card__detail-link
                                                "
                                                aria-label="Ver evento {{ $event->name ?: $operation->name }}"
                                            ></a>


                                            <div
                                                class="
                                                    event-card__period
                                                "
                                            >

                                                @if(
                                                    $operation
                                                        ->period
                                                        ?->ico
                                                )

                                                    <img
                                                        src="{{ asset(
                                                            'storage/'
                                                            . $operation
                                                                ->period
                                                                ->ico
                                                        ) }}"
                                                        alt="{{ $operation
                                                            ->period
                                                            ->name
                                                        }}"
                                                    >

                                                @endif


                                                @if(
                                                    $operation
                                                        ->platform
                                                        ?->image
                                                )

                                                    <img
                                                        src="{{ asset(
                                                            'storage/'
                                                            . $operation
                                                                ->platform
                                                                ->image
                                                        ) }}"
                                                        alt="{{ $operation
                                                            ->platform
                                                            ->name
                                                        }}"
                                                    >

                                                @endif

                                            </div>


                                            <div class="event-card__body">

                                                <div class="event-card__topline">

                                                    <span class="event-card__type">
                                                        {{ $operation->operationType?->name ?? 'Operativo' }}
                                                    </span>

                                                    @if($event->eventStatus)

                                                        <span
                                                            @class([
                                                                'event-card__status',
                                                                'is-active' =>
                                                                    $event->eventStatus?->name === 'ACTIVO',
                                                            ])
                                                        >
                                                            {{ $event->eventStatus->name }}
                                                        </span>

                                                    @endif

                                                    @if(
                                                        $event->eventStatus?->name === 'FINALIZADO'
                                                        && filled($event->ocap_url)
                                                    )

                                                        <a
                                                            href="{{ $event->ocap_url }}"
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            class="event-card__ocap-link"
                                                            title="Abrir OCAP"
                                                            aria-label="Abrir OCAP de {{ $event->name ?: $operation->name }}"
                                                        >
                                                            OCAP ↗
                                                        </a>

                                                    @endif

                                                </div>


                                                <div
                                                    class="
                                                        event-card__title-row
                                                    "
                                                >

                                                    <h3>
                                                        <a
                                                            href="{{ route(
                                                                'events.show',
                                                                $event
                                                            ) }}"
                                                        >
                                                            {{ $event->name
                                                                ?: $operation->name
                                                            }}
                                                        </a>
                                                    </h3>


                                                    <time
                                                        datetime="{{ $event
                                                            ->date
                                                            ->toIso8601String()
                                                        }}"
                                                    >
                                                        {{ $formattedDate }}
                                                    </time>

                                                </div>


                                                <dl
                                                    class="
                                                        event-card__facts
                                                    "
                                                >

                                                    @if($event->duration)

                                                        <div>
                                                            <dt>
                                                                Duración
                                                            </dt>

                                                            <dd>
                                                                {{ $event
                                                                    ->duration
                                                                }}
                                                                min
                                                            </dd>
                                                        </div>

                                                    @endif


                                                    @if($event->eventResult)

                                                        <div>
                                                            <dt>
                                                                Resultado
                                                            </dt>

                                                            <dd>
                                                                {{ $event
                                                                    ->eventResult
                                                                    ->name
                                                                }}
                                                            </dd>
                                                        </div>

                                                    @endif

                                                </dl>

                                            </div>

                                        </article>

                                    @endforeach

                                </div>

                            </div>

                        </details>

                    @endif

                @endif

            </section>

        </div>

    </article>

@endsection