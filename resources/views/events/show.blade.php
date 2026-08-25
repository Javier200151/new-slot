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
                        @if($canEditOperation)
                            <a
                                href="{{
                                    \App\Filament\Resources\Operations\OperationResource::getUrl(
                                        'edit',
                                        ['record' => $operation]
                                    )
                                }}"
                                class="btn btn-outline"
                            >
                                Editar operativo
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

            <header class="event-detail__hero">
                <div class="event-detail__hero-copy">
                    <div class="event-detail__eyebrow">

                        <span>
                            {{ $operation->operationType?->name ?? 'Evento' }}
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

                        @if(
                            $event->eventStatus?->name === 'FINALIZADO'
                            && (
                                $eventClips->isNotEmpty()
                                || $eventVods->isNotEmpty()
                                || $canAddEventMedia
                            )
                        )
                            <a href="#multimedia">
                                Multimedia
                            </a>
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
                @elseif($operation->ocap)
                    <span class="is-enabled">
                        OCAP
                    </span>
                @else
                    <span>
                        OCAP
                    </span>
                @endif

                <span @class([
                    'is-enabled' => $operation->respawn,
                ])>
                    Respawn
                </span>

                <span @class([
                    'is-enabled' => $operation->jip,
                ])>
                    JIP
                </span>

            </section>

            {{-- =========================================================
                MULTIMEDIA
            ========================================================= --}}

            @if(
                $event->eventStatus?->name === 'FINALIZADO'
                && (
                    $eventClips->isNotEmpty()
                    || $eventVods->isNotEmpty()
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
                                Clips y retransmisiones de la partida
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
                                        required
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
                                        required
                                    >

                                    @error('url')
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
                    )

                        <div class="event-media__empty">

                            <strong>
                                Todavía no hay contenido multimedia
                            </strong>

                            <p>
                                @if($canAddEventMedia)
                                    Puedes añadir el primer clip o VOD
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


            @if($descriptionSections->isNotEmpty())
                <section
                    id="briefing"
                    class="event-detail__section"
                >
                    <header>
                        <span>Briefing</span>
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
                                            class="
                                                briefing-section__image
                                            "
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
                                            <div class="event-rich-content">{{ $section['content'] }}</div>
                                        </section>                                    
                                    </div>

                                </div>

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

                                                                <x-user-link
                                                                    :user="$assignment->user"
                                                                    class="event-orbat__occupant-user"
                                                                    @style([
                                                                        '--member-group-color: '
                                                                        . ($assignment->user->mainSqaGroup?->color ?? '')
                                                                        => filled(
                                                                            $assignment->user->mainSqaGroup?->color
                                                                        ),
                                                                    ])
                                                                />

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

                                                        @if($isOrbatManager)
                                                            <button
                                                                type="button"
                                                                class="event-orbat__assign-player"
                                                                data-orbat-assign
                                                                data-slot-key="{{ $slot['slot_key'] }}"
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
                                                                    No disponible para reclutas
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
                                                        Miembro
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
                                Radios y frecuencias del operativo
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

            @if($addons->isNotEmpty())
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
                                Mods utilizados por el operativo
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
