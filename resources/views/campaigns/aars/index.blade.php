@extends('layouts.metopas')

@section('title', 'AAR · ' . $campaign->name)
@section('meta-description', 'After Action Reports de la campaña ' . $campaign->name . ' de Squad ALPHA.')
@section('body-class', 'campaign-aar-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/campaign-aar.css') }}?v={{ filemtime(public_path('css/campaign-aar.css')) }}">
@endpush

@section('content')
    <main class="aar-archive">
        <div class="container aar-shell">
            <nav class="aar-breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('campaigns.show', $campaign) }}">{{ $campaign->name }}</a>
                <span>/</span>
                <span>Archivo AAR</span>
            </nav>

            <header class="aar-archive__hero">
                <div>
                    <span class="aar-kicker">Archivo operacional · After Action Report</span>
                    <h1>{{ $campaign->name }}</h1>
                    <p>
                        Registro público de los informes posteriores a cada operativo finalizado de la campaña.
                    </p>
                </div>

                <div class="aar-stamp" aria-hidden="true">
                    <strong>AAR</strong>
                    <span>ARCHIVO</span>
                </div>
            </header>

            @if($events->isEmpty())
                <section class="aar-empty">
                    <span>Sin expedientes</span>
                    <strong>Todavía no hay operativos finalizados en esta campaña.</strong>
                </section>
            @else
                <section class="aar-file-list" aria-label="After Action Reports">
                    @foreach($events as $event)
                        @php
                            $aar = $event->campaignAar;
                            $published = $aar?->status === 'published';
                            $canEdit = $aar && auth()->check() && auth()->user()->can('update', $aar);
                        @endphp

                        <article @class(['aar-file', 'is-pending' => ! $published])>
                            <div class="aar-file__sequence">
                                <small>Operativo de campaña</small>
                                <strong>{{ str_pad((string) $event->campaign_sequence, 2, '0', STR_PAD_LEFT) }}</strong>
                            </div>

                            <div class="aar-file__body">
                                <div class="aar-file__topline">
                                    <span @class(['aar-status', 'is-published' => $published, 'is-pending' => ! $published])>
                                        {{ $published ? 'AAR PUBLICADO' : 'PENDIENTE AAR' }}
                                    </span>
                                    <time datetime="{{ $event->date?->toIso8601String() }}">
                                        {{ $event->date?->format('d/m/Y · H:i') }}
                                    </time>
                                </div>

                                <h2>{{ $event->name ?: $event->activity?->name }}</h2>

                                <div class="aar-file__meta">
                                    <span>
                                        <small>Mando global</small>
                                        <b>{{ $aar?->commander?->nick ?? 'Sin identificar' }}</b>
                                    </span>
                                    <span>
                                        <small>Actividad</small>
                                        <b>{{ $event->activity?->name ?? '—' }}</b>
                                    </span>
                                </div>
                            </div>

                            <div class="aar-file__actions">
                                @if($aar)
                                    <a href="{{ route('campaigns.aars.show', [$campaign, $event]) }}">
                                        {{ $published ? 'Leer AAR' : 'Ver expediente' }} →
                                    </a>

                                    @if($canEdit)
                                        <a class="aar-file__edit" href="{{ route('campaigns.aars.show', ['campaign' => $campaign, 'event' => $event, 'editar' => 1]) }}">
                                            {{ $published ? 'Editar' : 'Completar AAR' }}
                                        </a>
                                    @endif
                                @else
                                    <span class="aar-file__unavailable">Pendiente de inicializar</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </section>
            @endif
        </div>
    </main>
@endsection
