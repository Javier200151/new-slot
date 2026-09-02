@extends('layouts.metopas')

@section('title', $campaign->name)

@section('meta-description', 'Campaña ' . $campaign->name . ' de Squad ALPHA.')

@section('body-class', 'campaign-page-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ filemtime(public_path('css/events.css')) }}">
@endpush

@section('content')
    <article class="campaign-page">
        <div class="container campaign-page__container">
            {{-- <nav class="campaign-page__breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('events.index') }}">Eventos</a>
                <span aria-hidden="true">/</span>
                <span>{{ $campaign->name }}</span>
            </nav> --}}

            <header class="campaign-page__header">
                <span>Campaña</span>
                <h1>{{ $campaign->name }}</h1>

                @if(filled($campaign->description))
                    <div class="campaign-page__description">
                        {{ $description }}
                    </div>
                @endif
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
