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


                        <div class="live-grid">

                            @foreach(
                                $eventStreams
                                as $stream
                            )

                                <article
                                    class="live-card"
                                    id="directo-{{
                                        $stream->id
                                    }}"
                                >

                                    <header
                                        class="live-card__header"
                                    >

                                        <div>
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


                                    @if($stream->title)
                                        <div
                                            class="
                                                live-card__footer
                                            "
                                        >
                                            {{
                                                $stream->title
                                            }}
                                        </div>
                                    @endif

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


                            <div
                                class="
                                    streamer-control-field
                                "
                            >

                                <label for="stream_url">
                                    Enlace del directo
                                </label>

                                <input
                                    id="stream_url"
                                    type="url"
                                    name="stream_url"
                                    value="{{
                                        old(
                                            'stream_url',
                                            $myActiveStream
                                                ?->stream_url
                                        )
                                    }}"
                                    required
                                    maxlength="500"
                                    placeholder="
https://www.twitch.tv/usuario
                                    "
                                >

                                <small>
                                    Introduce la URL del
                                    canal de Twitch o del
                                    directo de YouTube.
                                </small>

                                @error('stream_url')
                                    <span
                                        class="
                                            stream-form-error
                                        "
                                    >
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