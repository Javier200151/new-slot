@extends('layouts.metopas')

@section('title', 'Directos')

@section(
    'meta-description',
    'Retransmisiones y streamers de Squad ALPHA.'
)

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/streams.css') }}"
    >
@endpush

@section('body-class', 'streams-body')

@section('content')

<section class="streams-page">

    <div class="container streams-container">

        <header class="streams-header">

            <div>
                <span class="streams-kicker">
                    SQUAD ALPHA LIVE
                </span>

                <h1>Directos</h1>

                <p>
                    Sigue las retransmisiones de los
                    miembros de Squad ALPHA.
                </p>
            </div>

        </header>


        @if(session('success'))
            <div class="streams-alert streams-alert--success">
                {{ session('success') }}
            </div>
        @endif


        {{-- EMISIONES ACTIVAS --}}
        @if($activeStreams->isNotEmpty())

            <section class="live-section">

                <header class="live-section__header">

                    <div>
                        <span class="live-dot"></span>

                        <span class="streams-kicker">
                            EN DIRECTO
                        </span>

                        <h2>
                            Retransmisiones activas
                        </h2>
                    </div>

                    <span class="live-count">
                        {{ $activeStreams->count() }}
                        {{ $activeStreams->count() === 1
                            ? 'emisión'
                            : 'emisiones'
                        }}
                    </span>

                </header>


                @foreach(
                    $activeStreams->groupBy('event_id')
                    as $eventStreams
                )

                    @php
                        $event =
                            $eventStreams
                                ->first()
                                ?->event;
                    @endphp

                    <div class="live-event">

                        <div class="live-event__title">

                            <span>
                                Evento
                            </span>

                            <strong>
                                {{ $event?->name
                                    ?? 'Emisión en directo'
                                }}
                            </strong>

                            @if($event?->date)
                                <small>
                                    {{
                                        $event->date
                                            ->format(
                                                'd/m/Y H:i'
                                            )
                                    }}
                                </small>
                            @endif

                        </div>
                        <div
                            class="live-layout-controls"
                            data-live-layout-controls
                            data-event-id="{{ $event?->id ?? 'unknown' }}"
                        >

                            <span class="live-layout-controls__label">
                                Vista:
                            </span>

                            <div class="live-layout-buttons">

                                <button
                                    type="button"
                                    class="
                                        live-layout-button
                                        is-active
                                    "
                                    data-live-view="auto"
                                    aria-pressed="true"
                                >
                                    Automática
                                </button>

                                <button
                                    type="button"
                                    class="live-layout-button"
                                    data-live-view="2"
                                    aria-pressed="false"
                                >
                                    2 columnas
                                </button>

                                <button
                                    type="button"
                                    class="live-layout-button"
                                    data-live-view="3"
                                    aria-pressed="false"
                                >
                                    3 columnas
                                </button>

                            </div>

                        </div>

                        @php
                            $streamCount = $eventStreams->count();
                        @endphp

                        <div
                            class="
                                live-grid
                                live-grid--count-{{ $streamCount }}
                                {{ $streamCount > 4 ? 'live-grid--many' : '' }}
                            "
                            data-live-grid
                            data-event-id="{{ $event?->id ?? 'unknown' }}"
                            data-view="auto"
                        >

                            @foreach($eventStreams as $stream)

                                <article
                                    class="live-card"
                                    id="directo-{{ $stream->id }}"
                                    data-stream-id="{{ $stream->id }}"
                                    draggable="true"
                                >

                                    <header
                                        class="live-card__header"
                                    >

                                        <div class="live-card__identity">

                                            <button
                                                type="button"
                                                class="live-drag-handle"
                                                title="Arrastra para cambiar la posición"
                                                aria-label="Mover retransmisión de {{
                                                    $stream->streamer?->user?->nick
                                                    ?? 'streamer'
                                                }}"
                                            >
                                                <span></span>
                                                <span></span>
                                                <span></span>
                                            </button>

                                            <span
                                                class="
                                                    live-dot
                                                    live-dot--small
                                                "
                                            ></span>

                                            <strong>
                                                {{
                                                    $stream
                                                        ->streamer
                                                        ?->user
                                                        ?->nick
                                                    ?? 'Streamer'
                                                }}
                                            </strong>

                                        </div>

                                        <span
                                            class="
                                                platform-badge
                                                platform-badge--{{
                                                    $stream
                                                        ->platform
                                                }}
                                            "
                                        >
                                            {{
                                                strtoupper(
                                                    $stream
                                                        ->platform
                                                        ?? ''
                                                )
                                            }}
                                        </span>

                                    </header>


                                    <div
                                        class="
                                            live-player
                                        "
                                    >

                                        @if(
                                            $stream->embed_url
                                        )

                                            <iframe
                                                src="{{
                                                    $stream
                                                        ->embed_url
                                                }}"
                                                title="Directo de {{
                                                    $stream
                                                        ->streamer
                                                        ?->user
                                                        ?->nick
                                                    ?? 'Squad ALPHA'
                                                }}"
                                                allow="
                                                    autoplay;
                                                    fullscreen;
                                                    picture-in-picture
                                                "
                                                allowfullscreen
                                                loading="lazy"
                                            ></iframe>

                                        @else

                                            <div
                                                class="
                                                    live-player__error
                                                "
                                            >
                                                No se puede
                                                reproducir esta
                                                emisión.
                                            </div>

                                        @endif

                                    </div>


                                    @<div class="live-card__footer">

                                    @if($stream->title)

                                        <div class="live-card__stream-title">
                                            {{ $stream->title }}
                                        </div>

                                    @endif


                                    @if(
                                        $stream->orbat_group_name
                                        || $stream->orbat_slot_name
                                    )

                                        <div class="live-card__orbat">

                                            <div class="live-card__orbat-item">

                                                <span>
                                                    Escuadra
                                                </span>

                                                <strong>
                                                    {{
                                                        $stream->orbat_group_name
                                                        ?? 'Sin escuadra'
                                                    }}
                                                </strong>

                                            </div>


                                            <div class="live-card__orbat-item">

                                                <span>
                                                    Slot
                                                </span>

                                                <strong>
                                                    {{
                                                        $stream->orbat_slot_name
                                                        ?? 'Sin slot'
                                                    }}
                                                </strong>

                                            </div>

                                        </div>

                                    @else

                                        <div class="live-card__orbat-empty">
                                            Sin slot asignado en este evento
                                        </div>

                                    @endif

                                </div>

                                </article>

                            @endforeach

                        </div>

                    </div>

                @endforeach

            </section>

        @else

            <section class="no-live">

                <span class="no-live__icon">
                    ◉
                </span>

                <div>
                    <strong>
                        No hay retransmisiones activas
                    </strong>

                    <p>
                        Cuando un streamer active su
                        emisión aparecerá aquí.
                    </p>
                </div>

            </section>

        @endif


        {{-- PANEL DEL STREAMER --}}
        @auth

            @if(
                $myStreamer
                && $myStreamer->enable
            )

                <section class="my-stream">

                    <header class="my-stream__header">

                        <span class="streams-kicker">
                            MI EMISIÓN
                        </span>

                        <h2>
                            Panel de streamer
                        </h2>

                        <p>
                            Controla la emisión que se
                            muestra al resto de usuarios.
                        </p>

                    </header>


                    @if(
                        ! auth()
                            ->user()
                            ->hasVerifiedEmail()
                    )

                        <div
                            class="
                                streams-alert
                                streams-alert--warning
                            "
                        >
                            Debes verificar tu correo
                            electrónico antes de publicar
                            una emisión.
                        </div>

                    @else

                        @if($myActiveStream)

                            <div
                                class="
                                    current-stream
                                "
                            >

                                <div>
                                    <span
                                        class="
                                            current-stream__status
                                        "
                                    >
                                        <span
                                            class="
                                                live-dot
                                                live-dot--small
                                            "
                                        ></span>

                                        Tu emisión está
                                        visible
                                    </span>

                                    <strong>
                                        {{
                                            $myActiveStream
                                                ->event
                                                ?->name
                                            ?? 'Evento'
                                        }}
                                    </strong>

                                    <small>
                                        {{
                                            strtoupper(
                                                $myActiveStream
                                                    ->platform
                                                ?? ''
                                            )
                                        }}
                                    </small>
                                </div>


                                <form
                                    method="POST"
                                    action="{{
                                        route(
                                            'streams.mine.destroy',
                                            $myActiveStream
                                                ->event_id
                                        )
                                    }}"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="
                                            stream-stop-button
                                        "
                                    >
                                        Desactivar emisión
                                    </button>
                                </form>

                            </div>

                        @endif


                        <form
                            method="POST"
                            action="{{
                                route(
                                    'streams.mine.update'
                                )
                            }}"
                            class="
                                streamer-control-form
                            "
                        >
                            @csrf
                            @method('PUT')


                            <div
                                class="
                                    streamer-control-field
                                "
                            >

                                <label for="event_id">
                                    Evento
                                </label>

                                <select
                                    id="event_id"
                                    name="event_id"
                                    required
                                >

                                    <option value="">
                                        Selecciona un evento
                                    </option>

                                    @foreach(
                                        $availableEvents
                                        as $event
                                    )

                                        <option
                                            value="{{
                                                $event->id
                                            }}"
                                            @selected(
                                                old(
                                                    'event_id',
                                                    $myActiveStream
                                                        ?->event_id
                                                )
                                                == $event->id
                                            )
                                        >
                                            {{
                                                $event->name
                                            }}
                                            ·
                                            {{
                                                $event
                                                    ->date
                                                    ?->format(
                                                        'd/m/Y H:i'
                                                    )
                                            }}
                                        </option>

                                    @endforeach

                                </select>

                                @error('event_id')
                                    <span
                                        class="
                                            stream-form-error
                                        "
                                    >
                                        {{ $message }}
                                    </span>
                                @enderror

                            </div>


                            <div
                                class="
                                    streamer-control-grid
                                "
                            >

                                <div
                                    class="
                                        streamer-control-field
                                    "
                                >

                                    <label
                                        for="platform"
                                    >
                                        Plataforma
                                    </label>

                                    <select
                                        id="platform"
                                        name="platform"
                                        required
                                    >
                                        <option
                                            value="twitch"
                                            @selected(
                                                old(
                                                    'platform',
                                                    $myActiveStream
                                                        ?->platform
                                                )
                                                === 'twitch'
                                            )
                                        >
                                            Twitch
                                        </option>

                                        <option
                                            value="youtube"
                                            @selected(
                                                old(
                                                    'platform',
                                                    $myActiveStream
                                                        ?->platform
                                                )
                                                === 'youtube'
                                            )
                                        >
                                            YouTube
                                        </option>
                                    </select>

                                </div>


                                <div
                                    class="
                                        streamer-control-field
                                    "
                                >

                                    <label for="title">
                                        Título
                                    </label>

                                    <input
                                        id="title"
                                        type="text"
                                        name="title"
                                        value="{{
                                            old(
                                                'title',
                                                $myActiveStream
                                                    ?->title
                                            )
                                        }}"
                                        maxlength="255"
                                        placeholder="
                                            Operativo en directo
                                        "
                                    >

                                </div>

                            </div>


                            @php
                                $currentTwitchUsername = '';

                                if (
                                    $myActiveStream
                                    && $myActiveStream->platform === 'twitch'
                                    && $myActiveStream->stream_url
                                ) {
                                    $currentTwitchUsername = \Illuminate\Support\Str::after(
                                        $myActiveStream->stream_url,
                                        'https://www.twitch.tv/'
                                    );
                                }
                            @endphp


                            {{-- Twitch --}}
                            <div
                                class="streamer-control-field"
                                data-twitch-field
                            >

                                <label for="twitch_username">
                                    Usuario de Twitch
                                </label>

                                <div class="stream-url-composer">

                                    <span class="stream-url-prefix">
                                        https://www.twitch.tv/
                                    </span>

                                    <input
                                        id="twitch_username"
                                        type="text"
                                        name="twitch_username"
                                        value="{{
                                            old(
                                                'twitch_username',
                                                $currentTwitchUsername
                                            )
                                        }}"
                                        maxlength="100"
                                        autocomplete="off"
                                        placeholder="usuario"
                                    >

                                </div>

                                <small>
                                    Escribe únicamente tu nombre de usuario de Twitch.
                                </small>

                                @error('twitch_username')
                                    <span class="stream-form-error">
                                        {{ $message }}
                                    </span>
                                @enderror

                            </div>


                            {{-- YouTube --}}
                            <div
                                class="streamer-control-field"
                                data-youtube-field
                                hidden
                            >

                                <label for="youtube_url">
                                    Enlace del directo de YouTube
                                </label>

                                <input
                                    id="youtube_url"
                                    type="url"
                                    name="youtube_url"
                                    value="{{
                                        old(
                                            'youtube_url',
                                            $myActiveStream?->platform === 'youtube'
                                                ? $myActiveStream->stream_url
                                                : ''
                                        )
                                    }}"
                                    maxlength="500"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                >

                                <small>
                                    Introduce el enlace completo del directo de YouTube.
                                </small>

                                @error('youtube_url')
                                    <span class="stream-form-error">
                                        {{ $message }}
                                    </span>
                                @enderror

                            </div>


                            @if(
                                $availableEvents
                                    ->isNotEmpty()
                            )

                                <button
                                    type="submit"
                                    class="
                                        stream-start-button
                                    "
                                >
                                    {{
                                        $myActiveStream
                                            ? 'Actualizar emisión'
                                            : 'Activar mi directo'
                                    }}
                                </button>

                            @else

                                <div
                                    class="
                                        streams-alert
                                        streams-alert--warning
                                    "
                                >
                                    No hay eventos disponibles
                                    para retransmitir.
                                </div>

                            @endif

                        </form>

                    @endif

                </section>

            @endif

        @endauth


        {{-- DIRECTORIO DE STREAMERS --}}
        <section class="streamers-directory">

            <header
                class="
                    streamers-directory__header
                "
            >

                <span class="streams-kicker">
                    STREAMERS
                </span>

                <h2>
                    Miembros que retransmiten
                </h2>

                <p>
                    Canales oficiales de los
                    streamers habilitados de
                    Squad ALPHA.
                </p>

            </header>


            <div class="streamers-grid">

                @foreach($streamers as $streamer)

                    @php
                        $user =
                            $streamer->user;

                        $avatar =
                            $user?->image
                                ? asset(
                                    'storage/'
                                    . $user->image
                                )
                                : asset(
                                    'images/'
                                    . 'sqa-shield-white.png'
                                );

                        $isLive =
                            $activeStreamerIds
                                ->contains(
                                    $streamer->id
                                );
                    @endphp


                    <article
                        class="
                            streamer-card
                            {{
                                $isLive
                                    ? 'is-live'
                                    : ''
                            }}
                        "
                    >

                        <div
                            class="
                                streamer-card__top
                            "
                        >

                            <div
                                class="
                                    streamer-card__avatar
                                "
                            >
                                <img
                                    src="{{ $avatar }}"
                                    alt="{{
                                        $user?->nick
                                        ?? 'Streamer'
                                    }}"
                                    loading="lazy"
                                >
                            </div>

                            <div>

                                <h3>
                                    {{
                                        $user?->nick
                                        ?? 'Streamer'
                                    }}
                                </h3>

                                @if($isLive)

                                    <span
                                        class="
                                            streamer-live-badge
                                        "
                                    >
                                        <span
                                            class="
                                                live-dot
                                                live-dot--small
                                            "
                                        ></span>

                                        EN DIRECTO
                                    </span>

                                @else

                                    <span
                                        class="
                                            streamer-offline
                                        "
                                    >
                                        STREAMER
                                    </span>

                                @endif

                            </div>

                        </div>


                        <div
                            class="
                                streamer-card__links
                            "
                        >

                            @if(
                                $streamer
                                    ->twitch_channel
                            )
                                <a
                                    href="{{
                                        $streamer
                                            ->twitch_channel
                                    }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Twitch
                                </a>
                            @endif


                            @if(
                                $streamer
                                    ->youtube_channel
                            )
                                <a
                                    href="{{
                                        $streamer
                                            ->youtube_channel
                                    }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    YouTube
                                </a>
                            @endif


                            @if(
                                $streamer
                                    ->website_url
                            )
                                <a
                                    href="{{
                                        $streamer
                                            ->website_url
                                    }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Web
                                </a>
                            @endif


                            @if(
                                $streamer
                                    ->other_channel
                            )
                                <a
                                    href="{{
                                        $streamer
                                            ->other_channel
                                    }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Otro
                                </a>
                            @endif

                        </div>

                    </article>

                @endforeach

            </div>

        </section>

    </div>

</section>

@endsection
@push('scripts')
    <script
        src="{{ asset('js/streams.js') }}"
        defer
    ></script>
@endpush