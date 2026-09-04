@extends('layouts.metopas')

@section('title', $campaign->name)

@section('meta-description', 'Campaña ' . $campaign->name . ' de Squad ALPHA.')

@section('body-class', 'campaign-page-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ filemtime(public_path('css/events.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/campaign-aar.css') }}?v={{ filemtime(public_path('css/campaign-aar.css')) }}">
@endpush

@section('content')
    <article class="campaign-page">
        <div class="container campaign-page__container">
            {{-- <nav class="campaign-page__breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('events.index') }}">Eventos</a>
                <span aria-hidden="true">/</span>
                <span>{{ $campaign->name }}</span>
            </nav> --}}

            <header @class(['campaign-page__header', 'has-cover' => filled($campaignCoverImage)])>
                <div class="campaign-page__header-grid">
                    <div class="campaign-page__intro">
                        <span>Campaña</span>
                        <h1>{{ $campaign->name }}</h1>

                        @if(filled($campaign->description))
                            <div class="campaign-page__description">
                                {{ $description }}
                            </div>
                        @endif
                    </div>

                    <aside class="campaign-page__aside">
                        @if(filled($campaignCoverImage))
                            <figure class="campaign-page__cover">
                                <img
                                    src="{{ asset('storage/' . $campaignCoverImage) }}"
                                    alt="Primera partida de la campaña {{ $campaign->name }}"
                                    loading="eager"
                                >
                            </figure>
                        @endif

                        <section class="campaign-aar-teaser" aria-labelledby="campaign-aar-teaser-title">
                            <div class="campaign-aar-teaser__top">
                                <div>
                                    <span class="aar-kicker">After Action Report</span>
                                    <h2 id="campaign-aar-teaser-title">Archivo AAR</h2>
                                </div>
                                <span class="campaign-aar-teaser__badge">AAR</span>
                            </div>

                            <div class="campaign-aar-teaser__counts">
                                <span>
                                    <b>{{ $campaignAarPublishedCount }}</b>
                                    <small>Publicados</small>
                                </span>
                                <span>
                                    <b>{{ $campaignAarPendingCount }}</b>
                                    <small>Pendientes</small>
                                </span>
                            </div>

                            @if($campaignAarPendingEvent)
                                <a
                                    class="campaign-aar-teaser__pending"
                                    href="{{ $campaignAarPendingEvent->campaignAar
                                        ? route('campaigns.aars.show', [$campaign, $campaignAarPendingEvent])
                                        : route('campaigns.aars.index', $campaign) }}"
                                >
                                    <span>
                                        Operativo de campaña {{ $campaignAarPendingEvent->campaign_sequence }}
                                    </span>
                                    <b>PENDIENTE AAR →</b>
                                </a>
                            @endif

                            <a href="{{ route('campaigns.aars.index', $campaign) }}">
                                Abrir archivo de campaña →
                            </a>
                        </section>
                    </aside>
                </div>
            </header>

            <section class="campaign-events" aria-labelledby="campaign-events-title">
                <header class="campaign-events__header">
                    <span>Eventos de campaña</span>
                    <h2 id="campaign-events-title">{{ $campaign->events->count() }} {{ $campaign->events->count() === 1 ? 'evento' : 'eventos' }}</h2>
                </header>

                @if($campaign->events->isEmpty())
                    <div class="events-empty">
                        <strong>Sin eventos</strong>
                        <p>Esta campaña todavía no tiene eventos activos o finalizados.</p>
                    </div>
                @else
                    <div class="events-list">
                        @foreach($campaign->events as $event)
                            @include('events.partials.card', ['event' => $event])
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </article>
@endsection
