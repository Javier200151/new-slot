@extends('layouts.metopas')

@php
    $weekdayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $formattedEventDate = $weekdayNames[$event->date->dayOfWeek]
        . ' '
        . $event->date->format('d/m/y H:i')
        . 'H';
    $dayOrNight = match ($activity->day_or_night) {
        'day' => 'Día',
        'night' => 'Noche',
        'both' => 'Día y noche',
        default => null,
    };
@endphp

@section('title', $event->name ?: $activity->name)

@section('meta-description', 'Información y ORBAT del evento ' . ($event->name ?: $activity->name) . '.')

@section('body-class', 'event-detail-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ filemtime(public_path('css/events.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/events.js') }}" defer></script>
@endpush

@section('content')
    <article
        class="event-detail"
        @if(! $isReadOnly && $event->eventStatus?->name === 'ACTIVO')
            data-event-roulette-watch
            data-roulette-lock-state-url="{{ route('events.roulette-lock-state', $event) }}"
        @endif
        @style([
            '--event-color: ' . ($activity->activityType?->color ?? '') => filled($activity->activityType?->color),
        ])
    >
        <div class="container event-detail__container">
            <nav class="event-detail__breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('events.index', ['month' => $event->date->month, 'year' => $event->date->year]) }}">Eventos</a>
                <span aria-hidden="true">/</span>
                <span>{{ $event->name ?: $activity->name }}</span>
            </nav>
            @if($isReadOnly)
                <div class="event-detail__readonly" role="status">
                    <strong>Vista previa en solo lectura</strong>
                    <span>Este evento está en borrador. Puede consultarse, pero no admite inscripciones, comentarios, multimedia ni cambios desde la web pública.</span>
                </div>
            @endif
            @if($canUseEditorMode)
                <div class="event-editor-mode">
                    <button
                        type="button"
                        class="event-editor-mode__toggle"
                        data-event-editor-toggle
                        aria-pressed="false"
                    >
                        <span>✎</span>
                        Modo edición
                    </button>

                    <div
                        class="event-editor-mode__tools
                            event-editor-only"
                    >
                        @if($canEditActivity)
                            <a
                                href="{{
                                    \App\Filament\Resources\Activities\ActivityResource::getUrl(
                                        'edit',
                                        ['record' => $activity]
                                    )
                                }}"
                                class="btn btn-outline"
                            >
                                Editar actividad
                            </a>
                        @endif

                        @if($canEditEvent)
                            <a
                                href="{{
                                    \App\Filament\Resources\Events\EventResource::getUrl(
                                        'edit',
                                        ['record' => $event]
                                    )
                                }}"
                                class="btn btn-outline"
                            >
                                Editar evento
                            </a>
                        @endif

                    </div>
                </div>
            @endif

            @if(session('media_status'))
                <div class="event-media__notice event-media__notice--success" role="status">
                    {{ session('media_status') }}
                </div>
            @endif

            @if(session('media_error'))
                <div class="event-media__notice event-media__notice--error" role="alert">
                    {{ session('media_error') }}
                </div>
            @endif

            @error('slot')
                <div class="event-detail__notice is-error" role="alert">{{ $message }}</div>
            @enderror

            @error('reservation')
                <div class="event-detail__notice is-error" role="alert">{{ $message }}</div>
            @enderror

            @if($hasPendingReserveRetutoring)
                <div class="event-roulette-lock event-retutoring-lock" data-event-retutoring-lock>
                    <div class="event-roulette-lock__backdrop" data-event-retutoring-lock-close></div>
                    <section class="event-roulette-lock__dialog" role="dialog" aria-modal="true" aria-labelledby="retutoring-lock-title">
                        <span class="event-roulette-lock__eyebrow">REINCORPORACIÓN PENDIENTE</span>
                        <h2 id="retutoring-lock-title">Todavía no puedes apuntarte a eventos</h2>
                        <p>
                            Has vuelto desde RESERVA y tu reincorporación todavía debe ser aprobada por el Área de Tutores.
                            Hasta entonces no puedes apuntarte a slots ni entrar en reservas de ningún evento.
                        </p>
                        <div class="event-roulette-lock__meta">
                            <span>La revisión puede resolverse con una <b>retutoría aprobada</b> o confirmando que <b>no es necesaria</b>.</span>
                            <span>Tu estado seguirá siendo <b>ACTIVO</b> mientras se completa este proceso.</span>
                        </div>
                        <div class="event-roulette-lock__actions">
                            <button type="button" class="btn btn-outline" data-event-retutoring-lock-close>Entendido</button>
                        </div>
                    </section>
                </div>
            @endif

            @if($rouletteLockRoom)
                <div class="event-roulette-lock" data-event-roulette-lock>
                    <div class="event-roulette-lock__backdrop" data-event-roulette-lock-close></div>
                    <section class="event-roulette-lock__dialog" role="dialog" aria-modal="true" aria-labelledby="roulette-lock-title">
                        <span class="event-roulette-lock__eyebrow">🎯 RULETA EN JUEGO</span>
                        <h2 id="roulette-lock-title">El ORBAT está temporalmente congelado</h2>
                        <p>
                            Hay una sala de ruleta activa para este evento. Mientras siga en juego no se puede apuntar,
                            desapuntar ni mover jugadores. Volverá a habilitarse cuando haya ganador, se cierre la sala o caduque.
                        </p>
                        <div class="event-roulette-lock__meta">
                            <span>Sorteo: <b>{{ $rouletteLockRoom->target_slot_group }} · {{ $rouletteLockRoom->target_slot_name }}</b></span>
                            @if($rouletteLockRoom->creator)
                                <span>Creada por <b>{{ $rouletteLockRoom->creator->nick }}</b></span>
                            @endif
                        </div>
                        <div class="event-roulette-lock__actions">
                            @auth
                                @if(\App\Support\CommunityArea::can(auth()->user(), \App\Support\CommunityArea::ROULETTE))
                                    <a class="btn" href="{{ route('community.roulette.show', $rouletteLockRoom) }}">Ver la ruleta</a>
                                @endif
                            @endauth
                            <button type="button" class="btn btn-outline" data-event-roulette-lock-close>Entendido</button>
                        </div>
                    </section>
                </div>
            @endif

            <header class="event-detail__hero">
                <div class="event-detail__hero-copy">
                    <div class="event-detail__eyebrow">

                        <span>
                            {{ $activity->activityType?->name ?? 'Evento' }}
                        </span>

                        <span
                            @class([
                                'is-active' =>
                                    $event->eventStatus?->name === 'ACTIVO',
                            ])
                        >
                            {{ $event->eventStatus?->name }}
                        </span>


                        {{-- ======================================================
                            EVENTO EN DIRECTO
                        ======================================================= --}}

                        @unless($isReadOnly)
                            <a
                                href="{{ route('streams.index') }}"
                                class="event-detail__live"
                                title="Ver retransmisiones en directo"

                                data-event-live
                                data-event-id="{{ $event->id }}"
                                data-stream-status-url="{{ route('streams.status') }}"

                                @if($activeEventStreams->isEmpty())
                                    hidden
                                @endif
                            >
                                <span
                                    class="event-detail__live-dot"
                                    aria-hidden="true"
                                ></span>

                                <span>
                                    EN DIRECTO
                                </span>
                            </a>
                        @endunless

                    </div>

                    <h1>{{ $event->name ?: $activity->name }}</h1>
                    <time datetime="{{ $event->date->toIso8601String() }}">{{ $formattedEventDate }}</time>

                    <nav class="event-detail__section-nav" aria-label="Secciones del evento">
                        {{-- <a href="#datos-evento">Datos</a> --}}
                        @if(filled($eventBriefingExtra) || $descriptionSections->isNotEmpty())
                            <a href="#briefing">Briefing</a>
                        @endif
                        @if(($activity->activityType?->usesReservations() ?? true) && ($event->reservations_enabled || $eventReservations->isNotEmpty()))
                            <a href="#reservas">Reservas</a>
                        @endif
                        <a href="#orbat">ORBAT</a>
                        {{-- <a href="#movimientos">Movimientos</a> --}}
                        @if(
                            ($activity->activityType?->usesEnemyFactions() ?? true)
                            && $activity->enemyFactions->isNotEmpty()
                        )
                            <a href="#facciones-enemigas">Facciones</a>
                        @endif
                        @if($radioNetworks->isNotEmpty())
                            <a href="#comunicaciones">Comunicaciones</a>
                        @endif
                        @if($addons->isNotEmpty() || filled($addonPackageUrl))
                            <a href="#addons">Addons</a>
                        @endif

                        @if(
                            $event->eventStatus?->name === 'FINALIZADO'
                            && (
                                $eventClips->isNotEmpty()
                                || $eventVods->isNotEmpty()
                                || $eventPhotos->isNotEmpty()
                                || $canAddEventMedia
                            )
                        )
                            <a href="#multimedia">
                                Multimedia
                            </a>
                        @endif

                        <a href="#comentarios">Comentarios</a>
                    </nav>

                    {{-- @if($event->name && $event->name !== $activity->name)
                        <p class="event-detail__activity-name">{{ $activity->name }}</p>
                    @endif --}}


                </div>

                @if(($activity->activityType?->usesImage() ?? true) && $activity->image)
                    <figure class="event-detail__cover">
                        <img src="{{ asset('storage/' . $activity->image) }}" alt="{{ $activity->name }}">
                    </figure>
                @endif
            </header>

            <section id="datos-evento" class="event-detail__facts" aria-label="Datos del evento y de la actividad">
                @if($activity->platform)
                    <div>
                        <dt>Plataforma</dt>
                        <dd class="event-detail__fact-with-icon">
                            @if($activity->platform->image)
                                <img
                                    src="{{ asset('storage/' . $activity->platform->image) }}"
                                    alt=""
                                    width="28"
                                    height="28"
                                    style="width:28px;height:28px;max-width:28px;max-height:28px;object-fit:contain;"
                                >
                            @endif
                            <span>{{ $activity->platform->name }}</span>
                        </dd>
                    </div>
                @endif

                @foreach([
                    // ['Tipo', $activity->activityType?->name],
                    // ['Estado del evento', $event->eventStatus?->name],
                    ['Periodo', ($activity->activityType?->usesPeriod() ?? true) ? $activity->period?->name : null],
                    ['Mapa', ($activity->activityType?->usesMap() ?? true) ? $activity->map?->name : null],
                    ['Ambientación', ($activity->activityType?->usesDayOrNight() ?? true) ? $dayOrNight : null],
                    ['Duración', ($activity->activityType?->usesEventEndDate() ?? true) && $event->duration ? $event->duration . ' min' : null],
                    ['Resultado', ($activity->activityType?->usesEventResult() ?? true) ? $event->eventResult?->name : null],
                    ['Editor', ($activity->activityType?->usesEditor() ?? true) ? $activity->editor_display_name : null],
                ] as [$label, $value])
                    @if(filled($value))
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd>
                                @if($label === 'Mapa' && $activity->map)
                                    <a href="{{ route('maps.show', $activity->map) }}">{{ $value }}</a>
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endif

                @endforeach


                @if(($activity->activityType?->usesCampaign() ?? true) && $activity->campaign)
                    <div>
                        <dt>Campaña</dt>
                        <dd><a href="{{ route('campaigns.show', $activity->campaign) }}">{{ $activity->campaign->name }}</a></dd>
                    </div>
                @endif
            </section>

            @if(
                (($activity->activityType?->usesMulticlans() ?? true) && $event->multiclans)
                || ($activity->activityType?->supportsOcap() ?? false)
                || ($activity->activityType?->supportsRespawn() ?? false)
                || ($activity->activityType?->supportsJip() ?? false)
            )
                <section class="event-detail__options" aria-label="Opciones de la actividad">

                    @if(($activity->activityType?->usesMulticlans() ?? true) && $event->multiclans)
                        <span class="is-enabled event-detail__option--multiclans">
                            Multiclán
                        </span>
                    @endif

                    @if($activity->activityType?->supportsOcap())
                        @if(
                            $event->eventStatus?->name === 'FINALIZADO'
                            && filled($event->ocap_url)
                        )
                            <a
                                href="{{ $event->ocap_url }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="event-detail__ocap-link"
                                title="Abrir OCAP"
                            >
                                OCAP ↗
                            </a>
                        @elseif($activity->ocap)
                            <span class="is-enabled">OCAP</span>
                        @else
                            <span>OCAP</span>
                        @endif
                    @endif

                    @if($activity->activityType?->supportsRespawn())
                        <span @class(['is-enabled' => $activity->respawn])>Respawn</span>
                    @endif

                    @if($activity->activityType?->supportsJip())
                        <span @class(['is-enabled' => $activity->jip])>JIP</span>
                    @endif
                </section>
            @endif

            @if(
                ($activity->activityType?->awardsMetopa() ?? false)
                && $activity->metopa
            )
                <section class="event-detail__course-metopa" aria-label="Metopa del curso">
                    <span>Metopa del curso</span>
                    <a href="{{ route('metopas.show', $activity->metopa) }}">
                        @if($activity->metopa->image)
                            <img
                                src="{{ asset('storage/' . $activity->metopa->image) }}"
                                alt=""
                            >
                        @endif
                        <strong>{{ $activity->metopa->name }}</strong>
                    </a>

                    @if($canAwardCourseMetopa && $courseMetopaAwardUrl)
                        <a
                            href="{{ $courseMetopaAwardUrl }}"
                            class="btn btn-outline event-course-metopa-action"
                        >
                            🏅 Entregar a los alumnos
                        </a>
                    @endif
                </section>
            @endif

            {{-- =========================================================
                MULTIMEDIA
            ========================================================= --}}

            @if(
                $event->eventStatus?->name === 'FINALIZADO'
                && (
                    $eventClips->isNotEmpty()
                    || $eventVods->isNotEmpty()
                    || $eventPhotos->isNotEmpty()
                    || $canAddEventMedia
                )
            )

                <section
                    id="multimedia"
                    class="
                        event-detail__section
                        event-media
                    "
                    aria-labelledby="event-media-title"
                >

                    {{-- =================================================
                        CABECERA
                    ================================================== --}}

                    <header class="event-media__header">

                        <div>
                            <span id="event-media-title">
                                Multimedia
                            </span>

                            <small>
                                Clips, fotos y retransmisiones de la partida
                            </small>
                        </div>


                        @if($canAddEventMedia)

                            <button
                                type="button"
                                class="event-media__add-button"
                                data-event-media-form-toggle
                                aria-expanded="{{
                                    $errors->has('type')
                                    || $errors->has('title')
                                    || $errors->has('url')
                                    || $errors->has('photo')
                                    || $errors->has('media')
                                        ? 'true'
                                        : 'false'
                                }}"
                            >
                                <span aria-hidden="true">
                                    +
                                </span>

                                Añadir contenido
                            </button>

                        @endif

                    </header>


                    {{-- =================================================
                        MENSAJES
                    ================================================== --}}

                    @if(session('media_status'))

                        <div
                            class="
                                event-detail__notice
                                is-success
                                event-media__notice
                            "
                            role="status"
                        >
                            {{ session('media_status') }}
                        </div>

                    @endif


                    @error('media')

                        <div
                            class="
                                event-detail__notice
                                is-error
                                event-media__notice
                            "
                            role="alert"
                        >
                            {{ $message }}
                        </div>

                    @enderror


                    {{-- =================================================
                        FORMULARIO
                    ================================================== --}}

                    @if($canAddEventMedia)

                        @php
                            $mediaFormHasErrors =
                                $errors->has('type')
                                || $errors->has('title')
                                || $errors->has('url')
                                || $errors->has('photo')
                                || $errors->has('media');
                        @endphp

                        <div
                            class="event-media-form"
                            data-event-media-form
                            {!! $mediaFormHasErrors ? '' : 'hidden' !!}
                        >

                            <form
                                method="POST"
                                action="{{
                                    route(
                                        'events.media.store',
                                        $event
                                    )
                                }}"
                                class="event-media-form__form"
                                enctype="multipart/form-data"
                            >
                                @csrf


                                {{-- Tipo --}}

                                <div class="event-media-form__field">

                                    <label for="event-media-type">
                                        Tipo
                                    </label>

                                    <select
                                        id="event-media-type"
                                        name="type"
                                        required
                                    >
                                        <option
                                            value="clip"
                                            {{ old('type', 'clip') === 'clip' ? 'selected' : '' }}
                                        >
                                            Clip
                                        </option>

                                        <option
                                            value="vod"
                                            {{ old('type') === 'vod' ? 'selected' : '' }}
                                        >
                                            VOD / Partida completa
                                        </option>

                                        <option
                                            value="photo"
                                            {{ old('type') === 'photo' ? 'selected' : '' }}
                                        >
                                            Foto
                                        </option>
                                    </select>

                                    @error('type')
                                        <small class="is-error">
                                            {{ $message }}
                                        </small>
                                    @enderror

                                </div>


                                {{-- Título --}}

                                <div
                                    class="
                                        event-media-form__field
                                        event-media-form__field--grow
                                    "
                                >

                                    <label for="event-media-title-input">
                                        Título
                                    </label>

                                    <input
                                        id="event-media-title-input"
                                        type="text"
                                        name="title"
                                        value="{{ old('title') }}"
                                        maxlength="160"
                                        placeholder="Ej. Asalto final al complejo"
                                    >

                                    @error('title')
                                        <small class="is-error">
                                            {{ $message }}
                                        </small>
                                    @enderror

                                </div>


                                {{-- URL --}}

                                <div
                                    class="
                                        event-media-form__field
                                        event-media-form__field--url
                                    "
                                    data-event-media-url-field
                                >

                                    <label for="event-media-url">
                                        Enlace de YouTube o Twitch
                                    </label>

                                    <input
                                        id="event-media-url"
                                        type="url"
                                        name="url"
                                        value="{{ old('url') }}"
                                        placeholder="https://..."
                                    >

                                    @error('url')
                                        <small class="is-error">
                                            {{ $message }}
                                        </small>
                                    @enderror

                                </div>

                                <div
                                    class="event-media-form__field event-media-form__field--url"
                                    data-event-media-photo-field
                                    hidden
                                >
                                    <label for="event-media-photo">
                                        Foto
                                    </label>

                                    <input
                                        id="event-media-photo"
                                        type="file"
                                        name="photo"
                                        accept="image/jpeg,image/png,image/webp,image/gif"
                                    >

                                    <small>JPG, PNG, WEBP o GIF. Máximo 10 MB.</small>

                                    @error('photo')
                                        <small class="is-error">
                                            {{ $message }}
                                        </small>
                                    @enderror
                                </div>


                                {{-- Acciones --}}

                                <div class="event-media-form__actions">

                                    <button
                                        type="button"
                                        class="btn btn-outline"
                                        data-event-media-form-cancel
                                    >
                                        Cancelar
                                    </button>

                                    <button
                                        type="submit"
                                        class="btn"
                                    >
                                        Publicar
                                    </button>

                                </div>

                            </form>

                        </div>

                    @endif


                    {{-- =================================================
                        CLIPS
                    ================================================== --}}

                    @if($eventClips->isNotEmpty())

                        <div class="event-media__clips">

                            <div class="event-media__subheading">

                                <div>
                                    <span>Clips</span>

                                    <small>
                                        Momentos destacados de la partida
                                    </small>
                                </div>
                             
                                    <div
                                        class="event-media-carousel__controls"
                                        aria-label="Controles del carrusel"
                                    >
                                        <button
                                            type="button"
                                            data-event-media-prev
                                            aria-label="Clip anterior"
                                        >
                                            ‹
                                        </button>

                                        <span data-event-media-counter>
                                            1 / {{ $eventClips->count() }}
                                        </span>

                                        <button
                                            type="button"
                                            data-event-media-next
                                            aria-label="Clip siguiente"
                                        >
                                            ›
                                        </button>
                                    </div>
                                

                            </div>


                            {{-- Carrusel --}}

                            <div
                                class="event-media-carousel"
                                data-event-media-carousel
                                data-total="{{ $eventClips->count() }}"
                            >

                                <div
                                    class="event-media-carousel__track"
                                    data-event-media-track
                                >

                                    @foreach($eventClips as $clip)

                                        @php
                                            $clipEmbedUrl =
                                                $clip->getEmbedUrl();

                                            $canDeleteClip =
                                                auth()->check()
                                                && (
                                                    (int) auth()->id()
                                                        === (int) $clip->user_id

                                                    || $canModerateEventMedia
                                                );
                                        @endphp

                                        <article
                                            class="
                                                event-media-card
                                                {{ $loop->first ? 'is-active' : '' }}
                                            "
                                            data-event-media-slide
                                            data-media-index="{{ $loop->index }}"
                                        >

                                            {{-- =========================================
                                                REPRODUCTOR
                                            ========================================== --}}

                                            <div class="event-media-card__player">

                                                @if($clipEmbedUrl)

                                                    <iframe
                                                        src="{{ $clipEmbedUrl }}"
                                                        title="{{ $clip->getDisplayTitle() }}"
                                                        loading="{{
                                                            $loop->first
                                                                ? 'eager'
                                                                : 'lazy'
                                                        }}"
                                                        allow="
                                                            accelerometer;
                                                            autoplay;
                                                            clipboard-write;
                                                            encrypted-media;
                                                            gyroscope;
                                                            picture-in-picture;
                                                            web-share
                                                        "
                                                        allowfullscreen
                                                    ></iframe>

                                                @else

                                                    <a
                                                        href="{{ $clip->url }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="
                                                            event-media-card__external
                                                        "
                                                    >
                                                        <strong>
                                                            Abrir clip
                                                        </strong>

                                                        <span>
                                                            {{ $clip->getProviderName() }}
                                                            ↗
                                                        </span>
                                                    </a>

                                                @endif

                                            </div>


                                            {{-- =========================================
                                                INFORMACIÓN
                                            ========================================== --}}

                                            <div class="event-media-card__info">

                                                <div class="event-media-card__copy">

                                                    <div class="event-media-card__provider">

                                                        <span
                                                            class="{{
                                                                $clip->isYoutube()
                                                                    ? 'is-youtube'
                                                                    : (
                                                                        $clip->isTwitch()
                                                                            ? 'is-twitch'
                                                                            : ''
                                                                    )
                                                            }}"
                                                        >
                                                            {{
                                                                $clip
                                                                    ->getProviderName()
                                                            }}
                                                        </span>

                                                    </div>

                                                    <h3>
                                                        {{
                                                            $clip
                                                                ->getDisplayTitle()
                                                        }}
                                                    </h3>

                                                    <p>
                                                        Añadido por

                                                        <strong>
                                                            {{
                                                                $clip
                                                                    ->getAddedByName()
                                                            }}
                                                        </strong>
                                                    </p>

                                                </div>


                                                <div class="event-media-card__actions">

                                                    <a
                                                        href="{{ $clip->url }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        title="Abrir en {{ $clip->getProviderName() }}"
                                                    >
                                                        ↗
                                                    </a>


                                                    @if($canDeleteClip)

                                                        <form
                                                            method="POST"
                                                            action="{{
                                                                route(
                                                                    'events.media.destroy',
                                                                    [
                                                                        $event,
                                                                        $clip,
                                                                    ]
                                                                )
                                                            }}"
                                                            onsubmit="
                                                                return confirm(
                                                                    '¿Eliminar este clip?'
                                                                );
                                                            "
                                                        >
                                                            @csrf
                                                            @method('DELETE')

                                                            <button
                                                                type="submit"
                                                                title="Eliminar clip"
                                                                aria-label="Eliminar clip"
                                                            >
                                                                ×
                                                            </button>

                                                        </form>

                                                    @endif

                                                </div>

                                            </div>

                                        </article>

                                    @endforeach

                                </div>

                            </div>

                        </div>

                    @endif


                    {{-- =================================================
                        FOTOS
                    ================================================== --}}

                    @if($eventPhotos->isNotEmpty())
                        <div class="event-media__photos">
                            <div class="event-media__subheading">
                                <div>
                                    <span>Fotos</span>
                                    <small>Imágenes compartidas por miembros ACTIVO</small>
                                </div>
                                <strong>{{ $eventPhotos->count() }}</strong>
                            </div>

                            <div class="event-media-photos">
                                @foreach($eventPhotos as $photo)
                                    @php
                                        $canDeletePhoto = auth()->check()
                                            && ((int) auth()->id() === (int) $photo->user_id || $canModerateEventMedia);
                                    @endphp

                                    <article class="event-media-photo">
                                        <button
                                            type="button"
                                            class="event-media-photo__image"
                                            data-event-image-zoom
                                            data-image-src="{{ $photo->url }}"
                                            data-image-alt="{{ $photo->getDisplayTitle() }}"
                                        >
                                            <img
                                                src="{{ $photo->url }}"
                                                alt="{{ $photo->getDisplayTitle() }}"
                                                loading="lazy"
                                            >
                                        </button>

                                        <div class="event-media-photo__meta">
                                            <div>
                                                <strong>{{ $photo->getDisplayTitle() }}</strong>
                                                <small>Añadida por {{ $photo->getAddedByName() }}</small>
                                            </div>

                                            @if($canDeletePhoto)
                                                <form
                                                    method="POST"
                                                    action="{{ route('events.media.destroy', [$event, $photo]) }}"
                                                    onsubmit="return confirm('¿Eliminar esta foto?');"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Eliminar foto" aria-label="Eliminar foto">×</button>
                                                </form>
                                            @endif
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    @endif


                    {{-- =================================================
                        VODS
                    ================================================== --}}

                    @if($eventVods->isNotEmpty())

                        <div class="event-media__vods">

                            <div class="event-media__subheading">

                                <div>
                                    <span>
                                        Partidas completas
                                    </span>

                                    <small>
                                        Retransmisiones y VODs completos
                                    </small>
                                </div>

                                <strong>
                                    {{ $eventVods->count() }}
                                </strong>

                            </div>


                            <div class="event-media-vods">

                                @foreach($eventVods as $vod)

                                    @php
                                        $canDeleteVod =
                                            auth()->check()
                                            && (
                                                (int) auth()->id()
                                                    === (int) $vod->user_id

                                                || $canModerateEventMedia
                                            );
                                    @endphp

                                    <article class="event-media-vod">

                                        <a
                                            href="{{ $vod->url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="event-media-vod__main"
                                        >

                                            <span
                                                class="
                                                    event-media-vod__provider

                                                    {{
                                                        $vod->isYoutube()
                                                            ? 'is-youtube'
                                                            : (
                                                                $vod->isTwitch()
                                                                    ? 'is-twitch'
                                                                    : ''
                                                            )
                                                    }}
                                                "
                                            >
                                                @if($vod->isYoutube())
                                                    ▶
                                                @else
                                                    ◈
                                                @endif
                                            </span>


                                            <span class="event-media-vod__copy">

                                                <strong>
                                                    {{
                                                        $vod
                                                            ->getDisplayTitle()
                                                    }}
                                                </strong>

                                                <small>
                                                    {{
                                                        $vod
                                                            ->getProviderName()
                                                    }}

                                                    · Añadido por

                                                    {{
                                                        $vod
                                                            ->getAddedByName()
                                                    }}
                                                </small>

                                            </span>


                                            <span
                                                class="
                                                    event-media-vod__external
                                                "
                                                aria-hidden="true"
                                            >
                                                ↗
                                            </span>

                                        </a>


                                        @if($canDeleteVod)

                                            <form
                                                method="POST"
                                                action="{{
                                                    route(
                                                        'events.media.destroy',
                                                        [
                                                            $event,
                                                            $vod,
                                                        ]
                                                    )
                                                }}"
                                                class="
                                                    event-media-vod__delete
                                                "
                                                onsubmit="
                                                    return confirm(
                                                        '¿Eliminar este VOD?'
                                                    );
                                                "
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button
                                                    type="submit"
                                                    title="Eliminar VOD"
                                                    aria-label="Eliminar VOD"
                                                >
                                                    ×
                                                </button>

                                            </form>

                                        @endif

                                    </article>

                                @endforeach

                            </div>

                        </div>

                    @endif


                    {{-- =================================================
                        ESTADO VACÍO
                    ================================================== --}}

                    @if(
                        $eventClips->isEmpty()
                        && $eventVods->isEmpty()
                        && $eventPhotos->isEmpty()
                    )

                        <div class="event-media__empty">

                            <strong>
                                Todavía no hay contenido multimedia
                            </strong>

                            <p>
                                @if($canAddEventMedia)
                                    Puedes añadir el primer clip, foto o VOD
                                    de esta partida.
                                @else
                                    Aún no se han compartido clips o
                                    retransmisiones de esta partida.
                                @endif
                            </p>

                        </div>

                    @endif

                </section>

            @endif


            @if(filled($eventBriefingExtra) || $descriptionSections->isNotEmpty())
                <section
                    id="briefing"
                    class="event-detail__section"
                >
                    <header>
                        <span>Briefing</span>
                    </header>

                    <div class="event-detail__descriptions">

                        @if(filled($eventBriefingExtra))
                            <section class="event-briefing-extra">
                                <div class="briefing-section__heading" role="heading" aria-level="3">
                                    Información del evento
                                </div>
                                <div class="briefing-section__content event-rich-content briefing-rich">
                                    {{ $eventBriefingExtra }}
                                </div>
                            </section>
                        @endif

                        @foreach($descriptionSections as $section)
                            @include('partials.briefing-section', ['section' => $section])
                        @endforeach

                    </div>
                </section>
            @endif

            @if($event->reservations_enabled || $eventReservations->isNotEmpty())
                <section
                    id="reservas"
                    class="event-detail__section event-detail__reservations"
                    aria-labelledby="event-reservations-title"
                >
                    <header>
                        <span id="event-reservations-title">RESERVAS</span>
                        <strong>{{ $eventReservations->count() }}</strong>
                    </header>

                    <div class="event-reservations">
                        <div class="event-reservations__intro">
                            <div>
                                <h3>Cola de reservas</h3>
                                <p>
                                    Los miembros de esta cola no ocupan un slot del ORBAT. Cuando aparezca un hueco libre,
                                    un gestor puede asignarlos manualmente desde el botón <b>Asignar</b> del slot.
                                </p>
                            </div>

                            @if($event->eventStatus?->name === 'ACTIVO' && ! $isReadOnly)
                                <div class="event-reservations__action">
                                    @if($rouletteLockRoom)
                                        <span class="event-reservations__paused">Pausado mientras la ruleta está activa</span>
                                    @elseif(auth()->check())
                                        @if($currentUserSlot)
                                            <span class="event-reservations__already">Ya estás apuntado en el ORBAT</span>
                                        @elseif($currentUserReservation)
                                            <form method="POST" action="{{ route('events.reservations.destroy', $event) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="event-reservations__button event-reservations__button--outline">Salir de reserva</button>
                                            </form>
                                        @elseif($event->reservations_enabled && $currentUserCanReserve)
                                            <form method="POST" action="{{ route('events.reservations.store', $event) }}">
                                                @csrf
                                                <button type="submit" class="event-reservations__button">Reservar</button>
                                            </form>
                                        @elseif($event->reservations_enabled)
                                            <span class="event-reservations__paused">
                                                {{ $hasPendingReserveRetutoring
                                                    ? 'Reincorporación pendiente de aprobación por Tutores'
                                                    : 'Tu estado actual no permite reservar'
                                                }}
                                            </span>
                                        @else
                                            <span class="event-reservations__paused">Reservas cerradas</span>
                                        @endif
                                    @elseif($event->reservations_enabled)
                                        <a class="event-reservations__button" href="#login-modal">Inicia sesión para reservar</a>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <details class="event-reservations__dropdown" @if($eventReservations->isNotEmpty()) open @endif>
                            <summary>
                                <span>Ver cola de reservas</span>
                                <strong>{{ $eventReservations->count() }}</strong>
                            </summary>

                            <div class="event-reservations__dropdown-body">
                                @if($eventReservations->isEmpty())
                                    <p class="event-reservations__empty">Todavía no hay nadie en reserva.</p>
                                @else
                                    <ol class="event-reservations__queue">
                                        @foreach($eventReservations as $reservation)
                                            <li>
                                                <span class="event-reservations__position">{{ $loop->iteration }}</span>
                                                <div class="event-reservations__member">
                                                    <x-user-link
                                                        :user="$reservation->user"
                                                        @style([
                                                            '--member-group-color: '.($reservation->user?->mainSqaGroup?->color ?? '')
                                                            => filled($reservation->user?->mainSqaGroup?->color),
                                                        ])
                                                    />
                                                    <small>En reserva desde {{ $reservation->created_at?->format('d/m H:i') }}</small>
                                                </div>
                                                @if($canManageOrbat && $event->eventStatus?->name === 'ACTIVO')
                                                    <span class="event-reservations__manager-note">Disponible para asignar</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                            </div>
                        </details>
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

                                            @if($group['faction']->army?->image)
                                                <img
                                                    src="{{
                                                        asset(
                                                            'storage/'
                                                            . $group['faction']->army->image
                                                        )
                                                    }}"
                                                    alt="{{
                                                        $group['faction']->army->name
                                                    }}"
                                                    class="event-orbat__army-logo"
                                                >
                                            @endif

                                            <div class="event-orbat__faction-copy">
                                                <strong>
                                                    {{ $group['faction']->name }}
                                                </strong>

                                                @if($group['faction']->army)
                                                    <span>
                                                        {{ $group['faction']->army->name }}
                                                    </span>
                                                @endif
                                            </div>

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

                                                $slotKey = $slot['slot_key'] ?? null;

                                                $isOrbatManager =
                                                    $canManageOrbat
                                                    && $event->eventStatus?->name === 'ACTIVO'
                                                    && filled($slotKey);
                                            @endphp

                                            <div
                                                @class([
                                                    'event-orbat__slot',
                                                    'is-occupied' => $slot['is_occupied'],
                                                    'is-owned' => $slot['is_owned_by_user'],
                                                ])

                                                @if($isOrbatManager)
                                                    data-orbat-slot
                                                    data-slot-key="{{ $slotKey }}"
                                                    data-manage-url="{{ route(
                                                        'events.slots.manage',
                                                        [
                                                            $event,
                                                            $slotKey,
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

                                                    <span class="event-orbat__slot-type">
                                                        @if($slot['slot_type']?->image)
                                                            <img
                                                                src="{{ asset('storage/' . $slot['slot_type']->image) }}"
                                                                alt="{{ $slot['slot_type']->name ?? 'Tipo de slot' }}"
                                                                class="event-orbat__slot-type-icon"
                                                            >
                                                        @endif

                                                        <span>
                                                            {{ $slot['slot_type']?->name ?? 'Sin tipo' }}
                                                        </span>
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
                                                                data-source-slot-key="{{ $slotKey }}"
                                                            >
                                                                <span
                                                                    class="event-orbat__drag-handle"
                                                                    aria-hidden="true"
                                                                    title="Arrastrar jugador"
                                                                >
                                                                    ⠿
                                                                </span>

                                                                <x-user-link
                                                                    :user="$assignment->user"
                                                                    class="event-orbat__occupant-user"
                                                                    style="--member-group-color: {{ $assignment->user->getStatusColor() }};"
                                                                />

                                                                @if($slot['is_owned_by_user'])
                                                                    <form
                                                                        method="POST"
                                                                        action="{{ route(
                                                                            'events.slots.unregister',
                                                                            [
                                                                                $event,
                                                                                $slotKey,
                                                                            ]
                                                                        ) }}"
                                                                        data-event-unregister-form
                                                                        data-slot-name="{{ $slot['name'] ?? 'Slot sin nombre' }}"
                                                                        class="event-orbat__self-unregister-form"
                                                                    >
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <input type="hidden" name="reason" value="" data-event-unregister-reason>

                                                                        <button
                                                                            type="button"
                                                                            class="event-orbat__remove-player"
                                                                            data-event-unregister-open
                                                                            draggable="false"
                                                                            title="Desapuntarme del ORBAT"
                                                                            aria-label="Desapuntarme del ORBAT"
                                                                        >
                                                                            ×
                                                                        </button>
                                                                    </form>
                                                                @else
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
                                                                @endif
                                                            </div>

                                                        @else

                                                            <strong
                                                                class="event-orbat__occupant-user"
                                                                style="--member-group-color: {{ $assignment->user->getStatusColor() }};"
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

                                                        @if($isOrbatManager)
                                                            <button
                                                                type="button"
                                                                class="event-orbat__assign-player"
                                                                data-orbat-assign
                                                                data-slot-key="{{ $slotKey }}"
                                                                data-slot-name="{{ $slot['name'] ?? 'Slot sin nombre' }}"
                                                                data-group-name="{{ $group['name'] ?? 'Grupo sin nombre' }}"
                                                            >
                                                                + Asignar
                                                            </button>
                                                        @endif

                                                    @endif


                                                    {{-- ======================================================
                                                        CONTROLES NORMALES DE INSCRIPCIÓN

                                                        Los dejamos para slots libres.

                                                        En un slot ocupado, si eres gestor ORBAT,
                                                        ya tienes arrastrar + X.
                                                    ======================================================= --}}

                                                    @if(! $isOrbatManager || ! $slot['is_occupied'])

                                                        @if($slot['is_owned_by_user'])

                                                            @if($rouletteLockRoom)
                                                                <span class="event-orbat__unavailable event-orbat__unavailable--roulette">
                                                                    🎯 Ruleta en juego
                                                                </span>
                                                            @elseif($event->eventStatus?->name === 'ACTIVO')
                                                                <form
                                                                    method="POST"
                                                                    action="{{ route(
                                                                        'events.slots.unregister',
                                                                        [
                                                                            $event,
                                                                            $slotKey,
                                                                        ]
                                                                    ) }}"
                                                                    data-event-unregister-form
                                                                    data-slot-name="{{ $slot['name'] ?? 'Slot sin nombre' }}"
                                                                >
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <input type="hidden" name="reason" value="" data-event-unregister-reason>

                                                                    <button
                                                                        type="button"
                                                                        class="event-orbat__unregister-button"
                                                                        data-event-unregister-open
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
                                                                        $slotKey,
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

                                                            @if($rouletteLockRoom)
                                                                <span class="event-orbat__unavailable event-orbat__unavailable--roulette">
                                                                    🎯 Ruleta en juego
                                                                </span>
                                                            @else
                                                            @guest
                                                                <a
                                                                    href="{{ route('login') }}"
                                                                    class="event-orbat__login-link"
                                                                >
                                                                    Inicia sesión para apuntarte
                                                                </a>
                                                            @else
                                                                <span class="event-orbat__unavailable">
                                                                    {{ $hasPendingReserveRetutoring
                                                                        ? 'Retutoría pendiente'
                                                                        : 'No disponible para reclutas'
                                                                    }}
                                                                </span>
                                                            @endguest
                                                            @endif

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
                @if(
                    $canManageOrbat
                    && $event->eventStatus?->name === 'ACTIVO'
                )
                    <dialog
                        class="event-orbat-assign-modal"
                        data-orbat-assign-modal
                    >
                        <div class="event-orbat-assign-modal__panel">

                            <header class="event-orbat-assign-modal__header">
                                <div>
                                    <span>Gestión de ORBAT</span>

                                    <h3>
                                        Asignar slot
                                    </h3>

                                    <p data-orbat-assign-context>
                                        Selecciona un miembro o aliado.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="event-orbat-assign-modal__close"
                                    data-orbat-assign-close
                                    aria-label="Cerrar"
                                >
                                    ×
                                </button>
                            </header>

                            <div class="event-orbat-assign-modal__search">
                                <label for="orbat-assignee-search">
                                    Buscar
                                </label>

                                <input
                                    id="orbat-assignee-search"
                                    type="search"
                                    placeholder="Buscar miembro o aliado..."
                                    autocomplete="off"
                                    data-orbat-assignee-search
                                >
                            </div>

                            <div
                                class="event-orbat-assign-modal__lists"
                                data-orbat-assignee-lists
                            >

                                {{-- ======================================================
                                    MIEMBROS
                                ======================================================= --}}

                                <section
                                    class="event-orbat-assign-modal__group"
                                    data-orbat-assignee-group
                                >
                                    <header>
                                        <h4>Miembros</h4>

                                        <span>
                                            {{ $orbatAssignableUsers->count() }}
                                        </span>
                                    </header>

                                    <div class="event-orbat-assign-modal__options">

                                        @forelse($orbatAssignableUsers as $assignableUser)

                                            <button
                                                type="button"
                                                class="event-orbat-assignee"
                                                data-orbat-assignee
                                                data-assignee-type="user"
                                                data-assignee-id="{{ $assignableUser->id }}"
                                                data-assignee-name="{{ $assignableUser->nick }}"
                                            >
                                                <span
                                                    class="event-orbat-assignee__avatar"
                                                    aria-hidden="true"
                                                >
                                                    {{ mb_strtoupper(
                                                        mb_substr(
                                                            $assignableUser->nick,
                                                            0,
                                                            1
                                                        )
                                                    ) }}
                                                </span>

                                                <span class="event-orbat-assignee__copy">
                                                    <strong>
                                                        {{ $assignableUser->nick }}
                                                    </strong>

                                                    <small>
                                                        {{ $assignableUser->getAttribute('is_event_reservation') ? 'Reserva · Miembro' : 'Miembro' }}
                                                    </small>
                                                </span>
                                            </button>

                                        @empty

                                            <p class="event-orbat-assign-modal__empty">
                                                No hay miembros disponibles.
                                            </p>

                                        @endforelse

                                    </div>
                                </section>


                                {{-- ======================================================
                                    ALIADOS
                                ======================================================= --}}

                                <section
                                    class="event-orbat-assign-modal__group"
                                    data-orbat-assignee-group
                                >
                                    <header>
                                        <h4>Aliados</h4>

                                        <span>
                                            {{ $orbatAssignableAllies->count() }}
                                        </span>
                                    </header>

                                    <div class="event-orbat-assign-modal__options">

                                        @forelse($orbatAssignableAllies as $assignableAlly)

                                            <button
                                                type="button"
                                                class="event-orbat-assignee"
                                                data-orbat-assignee
                                                data-assignee-type="ally"
                                                data-assignee-id="{{ $assignableAlly->id }}"
                                                data-assignee-name="{{ $assignableAlly->name }}"
                                            >

                                                @if($assignableAlly->image)

                                                    <img
                                                        src="{{ asset(
                                                            'storage/'
                                                            . $assignableAlly->image
                                                        ) }}"
                                                        alt=""
                                                        class="event-orbat-assignee__image"
                                                        loading="lazy"
                                                    >

                                                @else

                                                    <span
                                                        class="event-orbat-assignee__avatar"
                                                        aria-hidden="true"
                                                    >
                                                        {{ mb_strtoupper(
                                                            mb_substr(
                                                                $assignableAlly->name,
                                                                0,
                                                                1
                                                            )
                                                        ) }}
                                                    </span>

                                                @endif

                                                <span class="event-orbat-assignee__copy">
                                                    <strong>
                                                        {{ $assignableAlly->name }}
                                                    </strong>

                                                    <small>
                                                        Aliado
                                                    </small>
                                                </span>

                                            </button>

                                        @empty

                                            <p class="event-orbat-assign-modal__empty">
                                                No hay aliados disponibles.
                                            </p>

                                        @endforelse

                                    </div>
                                </section>

                            </div>

                            <p
                                class="event-orbat-assign-modal__no-results"
                                data-orbat-assignee-empty
                                hidden
                            >
                                No se encontraron resultados.
                            </p>

                            <footer class="event-orbat-assign-modal__footer">

                                <button
                                    type="button"
                                    class="btn btn-outline"
                                    data-orbat-assign-close
                                >
                                    Cancelar
                                </button>

                            </footer>

                        </div>
                    </dialog>
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

                                @php
                                    $memberName =
                                        $movement->user?->nick
                                        ?? $movement->ally?->name
                                        ?? 'Usuario eliminado';
                                @endphp

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

            @include('partials.enemy-factions-collapsible', ['activity' => $activity])

            @if($radioNetworks->isNotEmpty())
                <details
                    id="comunicaciones"
                    class="
                        event-detail__section
                        event-detail__collapsible
                    "
                >
                    <summary
                        class="event-detail__collapsible-summary"
                    >
                        <div>
                            <span>Comunicaciones</span>

                            <small>
                                Radios y frecuencias de la actividad
                            </small>
                        </div>

                        <strong>
                            {{ $radioNetworks->count() }}
                            {{ $radioNetworks->count() === 1
                                ? 'red'
                                : 'redes' }}
                        </strong>
                    </summary>

                    <div class="event-detail__collapsible-content">
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
                                                    {{ $network['name'] ?? 'Sin nombre' }}
                                                </strong>
                                            </td>

                                            <td>
                                                {{ $network['radio_model_name'] ?? '—' }}
                                            </td>

                                            <td>
                                                @foreach(
                                                    ($network['configuration'] ?? [])
                                                    as $key => $value
                                                )
                                                    @if(filled($value))
                                                        <span>
                                                            {{
                                                                match ($key) {
                                                                    'channel' => 'Canal',
                                                                    'block' => 'Bloque',
                                                                    'frequency' => 'Frecuencia',
                                                                    default => ucfirst($key),
                                                                }
                                                            }}:
                                                            {{ $value }}
                                                            {{ $key === 'frequency'
                                                                ? ' MHz'
                                                                : '' }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </td>

                                            <td>
                                                {{ $network['notes'] ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @endif

            @if(filled($addonPackageUrl))
                <section id="addons" class="event-detail__section">
                    <header>
                        <span>Addons</span>
                    </header>
                    <div class="event-detail__addon-package">
                        <div>
                            <strong>Paquete de addons de Reforger</strong>
                            <p>Abre el paquete configurado para esta actividad.</p>
                        </div>
                        <a href="{{ $addonPackageUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline">
                            Abrir paquete ↗
                        </a>
                    </div>
                </section>
            @elseif($addons->isNotEmpty())
                <details
                    id="addons"
                    class="
                        event-detail__section
                        event-detail__collapsible
                    "
                >
                    <summary
                        class="event-detail__collapsible-summary"
                    >
                        <div>
                            <span>Addons</span>

                            <small>
                                Mods utilizados por la actividad
                            </small>
                        </div>

                        <strong>
                            {{ $addons->count() }}
                            {{ $addons->count() === 1
                                ? 'addon'
                                : 'addons' }}
                        </strong>
                    </summary>

                    <div class="event-detail__collapsible-content">
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
                                            <td>{{ $addon->name }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @endif
            
            <dialog class="event-unregister-dialog" data-event-unregister-dialog>
                <form method="dialog" class="event-unregister-dialog__panel">
                    <header>
                        <div>
                            <span>Desapuntarse del ORBAT</span>
                            <small data-event-unregister-slot></small>
                        </div>
                        <button type="button" class="event-unregister-dialog__close" data-event-unregister-cancel aria-label="Cerrar">×</button>
                    </header>

                    <label for="event-unregister-reason-text">Motivo</label>
                    <textarea
                        id="event-unregister-reason-text"
                        rows="4"
                        maxlength="1000"
                        placeholder="Puedes indicar por qué te desapuntas."
                        data-event-unregister-text
                    ></textarea>
                    <p>Si escribes un motivo, quedará publicado en los comentarios del evento junto al desapunte.</p>

                    <footer>
                        <button type="button" class="btn btn-outline" data-event-unregister-cancel>Cancelar</button>
                        <button type="button" class="btn event-unregister-dialog__confirm" data-event-unregister-confirm>Desapuntarme</button>
                    </footer>
                </form>
            </dialog>

            <section id="comentarios" class="event-detail__section event-comments" aria-labelledby="event-comments-title">
                <header class="event-comments__header">
                    <span id="event-comments-title">Comentarios</span>
                    <strong>{{ $eventComments->count() }}</strong>
                </header>

                @if(session('comment_status'))
                    <div class="event-comments__notice" role="status">{{ session('comment_status') }}</div>
                @endif

                @if($isReadOnly)
                    <p class="event-comments__login">Los comentarios están desactivados mientras el evento permanezca en borrador.</p>
                @elseif(auth()->check())
                    <form method="POST" action="{{ route('events.comments.store', $event) }}" class="event-comment-form">
                        @csrf
                        @include('partials.bbcode-editor', [
                            'id' => 'event-comment-new',
                            'name' => 'comment',
                            'label' => 'Añadir un comentario',
                            'value' => old('comment'),
                            'rows' => 4,
                            'maxlength' => 5000,
                            'required' => true,
                            'placeholder' => 'Escribe tu comentario sobre el evento...',
                        ])
                        @error('comment')
                            <span class="event-comment-form__error">{{ $message }}</span>
                        @enderror
                        <div><button type="submit">Publicar comentario</button></div>
                    </form>
                @else
                    <p class="event-comments__login">
                        <a href="{{ route('login') }}">Inicia sesión</a> para publicar un comentario.
                    </p>
                @endif

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
