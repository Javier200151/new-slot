@extends('layouts.metopas')

@php
    $weekdayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $formattedEventDate = $weekdayNames[$event->date->dayOfWeek]
        . ' '
        . $event->date->format('d/m/y H:i')
        . 'H';
    $dayOrNight = match ($operation->day_or_night) {
        'day' => 'Día',
        'night' => 'Noche',
        'both' => 'Día y noche',
        default => null,
    };
@endphp

@section('title', $event->name ?: $operation->name)

@section('meta-description', 'Información y ORBAT del evento ' . ($event->name ?: $operation->name) . '.')

@section('body-class', 'event-detail-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/events.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/events.js') }}" defer></script>
@endpush

@section('content')
    <article
        class="event-detail"
        @style([
            '--event-color: ' . ($operation->operationType?->color ?? '') => filled($operation->operationType?->color),
        ])
    >
        <div class="container event-detail__container">
            <nav class="event-detail__breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('events.index', ['month' => $event->date->month, 'year' => $event->date->year]) }}">Eventos</a>
                <span aria-hidden="true">/</span>
                <span>{{ $event->name ?: $operation->name }}</span>
            </nav>

            @if(session('status'))
                <div class="event-detail__notice is-success" role="status">{{ session('status') }}</div>
            @endif

            @error('slot')
                <div class="event-detail__notice is-error" role="alert">{{ $message }}</div>
            @enderror

            <header class="event-detail__hero">
                <div class="event-detail__hero-copy">
                    <div class="event-detail__eyebrow">
                        <span>{{ $operation->operationType?->name ?? 'Evento' }}</span>
                        <span @class(['is-active' => $event->eventStatus?->name === 'ACTIVO'])>
                            {{ $event->eventStatus?->name }}
                        </span>
                    </div>

                    <h1>{{ $event->name ?: $operation->name }}</h1>
                    <time datetime="{{ $event->date->toIso8601String() }}">{{ $formattedEventDate }}</time>

                    <nav class="event-detail__section-nav" aria-label="Secciones del evento">
                        {{-- <a href="#datos-evento">Datos</a> --}}
                        @if($descriptionSections->isNotEmpty())
                            <a href="#briefing">Briefing</a>
                        @endif
                        <a href="#orbat">ORBAT</a>
                        {{-- <a href="#movimientos">Movimientos</a> --}}
                        @if($radioNetworks->isNotEmpty())
                            <a href="#comunicaciones">Comunicaciones</a>
                        @endif
                        @if($addons->isNotEmpty())
                            <a href="#addons">Addons</a>
                        @endif
                        <a href="#comentarios">Comentarios</a>
                    </nav>

                    {{-- @if($event->name && $event->name !== $operation->name)
                        <p class="event-detail__operation-name">{{ $operation->name }}</p>
                    @endif --}}


                </div>

                @if($operation->image)
                    <figure class="event-detail__cover">
                        <img src="{{ asset('storage/' . $operation->image) }}" alt="{{ $operation->name }}">
                    </figure>
                @endif
            </header>

            <section id="datos-evento" class="event-detail__facts" aria-label="Datos del evento y del operativo">
                @foreach([
                    // ['Tipo', $operation->operationType?->name],
                    // ['Estado del evento', $event->eventStatus?->name],
                    ['Plataforma', $operation->platform?->name],
                    ['Periodo', $operation->period?->name],
                    ['Mapa', $operation->map?->name],
                    ['Ambientación', $dayOrNight],
                    ['Duración', $event->duration ? $event->duration . ' min' : null],
                    ['Resultado', $event->eventResult?->name],
                    ['Editor', $operation->editor?->nick],
                ] as [$label, $value])
                    @if(filled($value))
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>
                                @if($label === 'Mapa' && $operation->map)
                                    <a href="{{ route('maps.show', $operation->map) }}">{{ $value }}</a>
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endif

                @endforeach

                @if($operation->campaign)
                    <div>
                        <dt>Campaña</dt>
                        <dd><a href="{{ route('campaigns.show', $operation->campaign) }}">{{ $operation->campaign->name }}</a></dd>
                    </div>
                @endif
            </section>

            <section class="event-detail__options" aria-label="Opciones del operativo">
                <span @class(['is-enabled' => $operation->ocap])>OCAP</span>
                <span @class(['is-enabled' => $operation->respawn])>Respawn</span>
                <span @class(['is-enabled' => $operation->jip])>JIP</span>
            </section>

            {{-- @if($operation->enemyFactions->isNotEmpty())
                <section class="event-detail__section">
                    <header><span>Facciones enemigas</span></header>
                    <div class="event-detail__tags">
                        @foreach($operation->enemyFactions as $faction)
                            <span>
                                {{ $faction->name }}
                                @if($faction->army)
                                    · {{ $faction->army->name }}
                                @endif
                                @if($faction->side)
                                    · {{ $faction->side->name }}
                                @endif
                            </span>
                        @endforeach
                    </div>
                </section>
            @endif --}}

            @if($descriptionSections->isNotEmpty())
                <section id="briefing" class="event-detail__section">
                    <header><span>Briefing</span></header>
                    <div class="event-detail__descriptions">
                        @foreach($descriptionSections as $section)
                            <section>
                                <h3>{{ $section['title'] }}</h3>
                                <div class="event-rich-content">{{ $section['content'] }}</div>
                            </section>
                        @endforeach
                    </div>
                </section>
            @endif

            <section
                id="orbat"
                class="event-detail__section event-detail__orbat"
                aria-labelledby="event-orbat-title"
                data-orbat
                data-csrf-token="{{ csrf_token() }}"
            >
                <header><span id="event-orbat-title">ORBAT</span></header>

                @if($visibleOrbatGroups->isEmpty())
                    <div class="events-empty"><strong>ORBAT no disponible</strong><p>No hay grupos visibles para este evento.</p></div>
                @else
                    <div class="event-orbat">
                        @foreach($visibleOrbatGroups as $group)
                            <section class="event-orbat__group">
                                <header>
                                    <div><span>Grupo</span><h3>{{ $group['name'] ?? 'Grupo sin nombre' }}</h3></div>
                                    @if($group['faction'])
                                        <div class="event-orbat__faction">
                                            <strong>{{ $group['faction']->name }}</strong>
                                            @if($group['faction']->army)<span>{{ $group['faction']->army->name }}</span>@endif
                                        </div>
                                    @endif
                                </header>

                                @if($group['slots']->isEmpty())
                                    <p class="event-orbat__empty">Este grupo no tiene slots visibles.</p>
                                @else
                                    <div class="event-orbat__slots">
                                        @foreach($group['slots'] as $slot)
                                            @php
                                                $assignment = $slot['assignment'];

                                                $occupantName =
                                                    $assignment?->user?->nick
                                                    ?? $assignment?->ally?->name;

                                                $isOrbatManager =
                                                    $canManageOrbat
                                                    && $event->eventStatus?->name === 'ACTIVO';
                                            @endphp

                                            <div
                                                @class([
                                                    'event-orbat__slot',
                                                    'is-occupied' => $slot['is_occupied'],
                                                    'is-owned' => $slot['is_owned_by_user'],
                                                ])

                                                @if($isOrbatManager)
                                                    data-orbat-slot
                                                    data-slot-key="{{ $slot['slot_key'] }}"
                                                    data-manage-url="{{ route(
                                                        'events.slots.manage',
                                                        [
                                                            $event,
                                                            $slot['slot_key'],
                                                        ]
                                                    ) }}"
                                                    data-occupant-user-id="{{ $assignment?->user_id }}"
                                                    data-occupant-name="{{ $occupantName }}"
                                                @endif
                                            >
                                                <div class="event-orbat__slot-info">
                                                    <strong>
                                                        {{ $slot['name'] ?? 'Slot sin nombre' }}
                                                    </strong>

                                                    <span>
                                                        {{ $slot['slot_type']?->name ?? 'Sin tipo' }}
                                                    </span>
                                                </div>

                                                <div class="event-orbat__slot-action">

                                                    {{-- ======================================================
                                                        USUARIO SQA OCUPANDO EL SLOT
                                                    ======================================================= --}}
                                                    @if($assignment?->user)

                                                        @if($isOrbatManager)

                                                            <div
                                                                class="event-orbat__managed-player"
                                                                draggable="true"
                                                                data-orbat-player
                                                                data-user-id="{{ $assignment->user->id }}"
                                                                data-user-name="{{ $assignment->user->nick }}"
                                                                data-source-slot-key="{{ $slot['slot_key'] }}"
                                                            >
                                                                <span
                                                                    class="event-orbat__drag-handle"
                                                                    aria-hidden="true"
                                                                    title="Arrastrar jugador"
                                                                >
                                                                    ⠿
                                                                </span>

                                                                <strong
                                                                    class="event-orbat__occupant-user"
                                                                    @style([
                                                                        '--member-group-color: '
                                                                        . ($assignment->user->mainSqaGroup?->color ?? '')
                                                                        => filled(
                                                                            $assignment->user->mainSqaGroup?->color
                                                                        ),
                                                                    ])
                                                                >
                                                                    {{ $assignment->user->nick }}
                                                                </strong>

                                                                <button
                                                                    type="button"
                                                                    class="event-orbat__remove-player"
                                                                    data-orbat-remove
                                                                    data-user-name="{{ $assignment->user->nick }}"
                                                                    draggable="false"
                                                                    title="Eliminar del ORBAT"
                                                                    aria-label="Eliminar a {{ $assignment->user->nick }} del ORBAT"
                                                                >
                                                                    ×
                                                                </button>
                                                            </div>

                                                        @else

                                                            <strong
                                                                class="event-orbat__occupant-user"
                                                                @style([
                                                                    '--member-group-color: '
                                                                    . ($assignment->user->mainSqaGroup?->color ?? '')
                                                                    => filled(
                                                                        $assignment->user->mainSqaGroup?->color
                                                                    ),
                                                                ])
                                                            >
                                                                {{ $assignment->user->nick }}
                                                            </strong>

                                                        @endif

                                                    {{-- ======================================================
                                                        ALIADO EXTERNO
                                                    ======================================================= --}}
                                                    @elseif($assignment?->ally)

                                                        <span class="event-orbat__occupant">
                                                            {{ $assignment->ally->name }}
                                                        </span>

                                                        @if($isOrbatManager)
                                                            <button
                                                                type="button"
                                                                class="event-orbat__remove-player"
                                                                data-orbat-remove
                                                                data-user-name="{{ $assignment->ally->name }}"
                                                                draggable="false"
                                                                title="Eliminar del ORBAT"
                                                                aria-label="Eliminar a {{ $assignment->ally->name }} del ORBAT"
                                                            >
                                                                ×
                                                            </button>
                                                        @endif

                                                    {{-- ======================================================
                                                        SLOT LIBRE
                                                    ======================================================= --}}
                                                    @else

                                                        <span class="event-orbat__occupant">
                                                            Libre
                                                        </span>

                                                    @endif


                                                    {{-- ======================================================
                                                        CONTROLES NORMALES DE INSCRIPCIÓN

                                                        Los dejamos para slots libres.

                                                        En un slot ocupado, si eres gestor ORBAT,
                                                        ya tienes arrastrar + X.
                                                    ======================================================= --}}

                                                    @if(! $isOrbatManager || ! $slot['is_occupied'])

                                                        @if($slot['is_owned_by_user'])

                                                            @if($event->eventStatus?->name === 'ACTIVO')
                                                                <form
                                                                    method="POST"
                                                                    action="{{ route(
                                                                        'events.slots.unregister',
                                                                        [
                                                                            $event,
                                                                            $slot['slot_key'],
                                                                        ]
                                                                    ) }}"
                                                                >
                                                                    @csrf
                                                                    @method('DELETE')

                                                                    <button
                                                                        type="submit"
                                                                        class="event-orbat__unregister-button"
                                                                    >
                                                                        Desapuntarme
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="event-orbat__own-slot">
                                                                    Tu slot
                                                                </span>
                                                            @endif

                                                        @elseif($slot['can_register'])

                                                            <form
                                                                method="POST"
                                                                action="{{ route(
                                                                    'events.slots.register',
                                                                    [
                                                                        $event,
                                                                        $slot['slot_key'],
                                                                    ]
                                                                ) }}"
                                                            >
                                                                @csrf

                                                                <button
                                                                    type="submit"
                                                                    class="event-orbat__register-button"
                                                                >
                                                                    {{ $slot['will_move_user']
                                                                        ? 'Cambiarme aquí'
                                                                        : 'Apuntarme'
                                                                    }}
                                                                </button>
                                                            </form>

                                                        @elseif(
                                                            ! $slot['is_occupied']
                                                            && $event->eventStatus?->name === 'ACTIVO'
                                                        )

                                                            @guest
                                                                <a
                                                                    href="{{ route('login') }}"
                                                                    class="event-orbat__login-link"
                                                                >
                                                                    Inicia sesión para apuntarte
                                                                </a>
                                                            @else
                                                                <span class="event-orbat__unavailable">
                                                                    No disponible para tu estado
                                                                </span>
                                                            @endguest

                                                        @endif

                                                    @endif
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

            <details id="movimientos" class="event-slot-history">
                <summary>
                    <span>Movimientos de slots</span>
                    <strong>{{ $slotHistory->count() }}</strong>
                </summary>

                <div class="event-slot-history__content">
                    @if($slotHistory->isEmpty())
                        <p>Todavía no se ha registrado ningún movimiento.</p>
                    @else
                        <ol>
                            @foreach($slotHistory as $movement)
                                @php($memberName = $movement->user?->nick ?? $movement->ally?->name ?? 'Usuario eliminado')
                                <li>
                                    <div class="event-slot-history__movement">
                                        <strong>{{ $memberName }}</strong>

                                        @if($movement->action === 'moved')
                                            <span>
                                                se movió de
                                                <b>{{ $movement->from_slot_group }} · {{ $movement->from_slot_name }}</b>
                                                a
                                                <b>{{ $movement->to_slot_group }} · {{ $movement->to_slot_name }}</b>
                                            </span>
                                        @elseif($movement->action === 'unassigned')
                                            <span>
                                                se desapuntó de
                                                <b>{{ $movement->from_slot_group }} · {{ $movement->from_slot_name }}</b>
                                            </span>
                                        @else
                                            <span>
                                                se apuntó a
                                                <b>{{ $movement->to_slot_group }} · {{ $movement->to_slot_name }}</b>
                                            </span>
                                        @endif
                                    </div>

                                    <div class="event-slot-history__meta">
                                        @if($movement->changedBy)
                                            <span>Gestionado por {{ $movement->changedBy->nick }}</span>
                                        @endif
                                        <time datetime="{{ $movement->created_at?->toIso8601String() }}">
                                            {{ $movement->created_at?->format('d/m/Y H:i') }}
                                        </time>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </details>

            @if($radioNetworks->isNotEmpty())
                <section id="comunicaciones" class="event-detail__section">
                    <header><span>Comunicaciones</span></header>
                    <div class="event-detail__table-wrap">
                        <table class="event-detail__table">
                            <thead><tr><th>Red</th><th>Radio</th><th>Configuración</th><th>Notas</th></tr></thead>
                            <tbody>
                                @foreach($radioNetworks as $network)
                                    <tr>
                                        <td><strong>{{ $network['name'] ?? 'Sin nombre' }}</strong></td>
                                        <td>{{ $network['radio_model_name'] ?? '—' }}</td>
                                        <td>
                                            @foreach(($network['configuration'] ?? []) as $key => $value)
                                                @if(filled($value))
                                                    <span>{{ match ($key) { 'channel' => 'Canal', 'block' => 'Bloque', 'frequency' => 'Frecuencia', default => ucfirst($key) } }}: {{ $value }}{{ $key === 'frequency' ? ' MHz' : '' }}</span>
                                                @endif
                                            @endforeach
                                        </td>
                                        <td>{{ $network['notes'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if($addons->isNotEmpty())
                <section id="addons" class="event-detail__section">
                    <header><span>Addons</span></header>
                    <div class="event-detail__table-wrap">
                        <table class="event-detail__table event-detail__addons-table">
                            {{-- <thead><tr><th>Addon</th><th>Uso</th><th>Descripción</th></tr></thead> --}}
                            <thead><tr><th>Addon</th></tr></thead>
                            <tbody>
                                @foreach($addons as $addon)
                                    <tr>
                                        <td>{{ $addon->name }}</td>
                                        {{-- <td>{{ $addon->mandatory ? 'Obligatorio' : 'Opcional' }}</td>
                                        <td>{{ $addon->description ?: '—' }}</td> --}}
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <section id="comentarios" class="event-detail__section event-comments" aria-labelledby="event-comments-title">
                <header class="event-comments__header">
                    <span id="event-comments-title">Comentarios</span>
                    <strong>{{ $eventComments->count() }}</strong>
                </header>

                @if(session('comment_status'))
                    <div class="event-comments__notice" role="status">{{ session('comment_status') }}</div>
                @endif

                @auth
                    <form method="POST" action="{{ route('events.comments.store', $event) }}" class="event-comment-form">
                        @csrf
                        <label for="event-comment-new">Añadir un comentario</label>
                        <textarea
                            id="event-comment-new"
                            name="comment"
                            rows="4"
                            maxlength="5000"
                            required
                            placeholder="Escribe tu comentario sobre el evento..."
                        >{{ old('comment') }}</textarea>
                        @error('comment')
                            <span class="event-comment-form__error">{{ $message }}</span>
                        @enderror
                        <div><button type="submit">Publicar comentario</button></div>
                    </form>
                @else
                    <p class="event-comments__login">
                        <a href="{{ route('login') }}">Inicia sesión</a> para publicar un comentario.
                    </p>
                @endauth

                @if($eventComments->isEmpty())
                    <div class="events-empty">
                        <strong>Todavía no hay comentarios</strong>
                        <p>Los comentarios publicados sobre este evento aparecerán aquí.</p>
                    </div>
                @else
                    <div class="event-comments__list">
                        @foreach($commentsByParent->get('root', collect()) as $comment)
                            @include('events.partials.comment', [
                                'comment' => $comment,
                                'commentsByParent' => $commentsByParent,
                                'depth' => 0,
                            ])
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </article>
@endsection
