@extends('layouts.metopas')

@section('title', $map->name)

@section('meta-description', $map->description ?: 'Información del mapa ' . $map->name . ' de Squad ALPHA.')

@section('body-class', 'map-page-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/maps.css') }}">
@endpush

@section('content')
    <article class="map-page">
        <div class="container map-page__container">
            <nav class="map-page__breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('events.index') }}">Eventos</a>
                <span aria-hidden="true">/</span>
                <span>{{ $map->name }}</span>
            </nav>

            <header @class(['map-page__hero', 'map-page__hero--without-image' => blank($map->image)])>
                <div class="map-page__heading">
                    <span>Mapa</span>
                    <h1>{{ $map->name }}</h1>

                    @if($map->platform)
                        <div class="map-page__platform">
                            @if($map->platform->image)
                                <img src="{{ asset('storage/' . $map->platform->image) }}" alt="">
                            @endif
                            <span>Plataforma</span>
                            <strong>{{ $map->platform->name }}</strong>
                        </div>
                    @endif
                </div>

                @if($map->image)
                    <figure class="map-page__image">
                        <img src="{{ asset('storage/' . $map->image) }}" alt="Mapa {{ $map->name }}">
                    </figure>
                @endif
            </header>

            @if(filled($map->description) || filled($map->url))
                <section class="map-page__content">
                    @if(filled($map->description))
                        <div>
                            <span>Descripción</span>
                            <p>{{ $map->description }}</p>
                        </div>
                    @endif

                    @if(filled($map->url))
                        <a href="{{ $map->url }}" target="_blank" rel="noopener noreferrer" class="map-page__external-link">
                            Ver información externa
                            <span aria-hidden="true">↗</span>
                        </a>
                    @endif
                </section>
            @endif

            <section class="map-page__operations" aria-labelledby="map-operations-title">
                <header class="map-page__operations-header">
                    <span>Historial del mapa</span>
                    <h2 id="map-operations-title">
                        {{ $map->operations->count() }}
                        {{ $map->operations->count() === 1 ? 'actividad creada' : 'actividades creadas' }}
                        en {{ $map->name }}
                    </h2>
                </header>

                @if($map->operations->isEmpty())
                    <div class="map-page__operations-empty">
                        Todavía no hay actividades asociadas a este mapa.
                    </div>
                @else
                    <div class="map-page__operations-grid">
                        @foreach($map->operations as $operation)
                            <a
                                href="{{ route('operations.show', $operation) }}"
                                class="map-page__operation-card"
                                @style([
                                    '--operation-color: ' . ($operation->operationType?->color ?? '')
                                        => filled($operation->operationType?->color),
                                ])
                            >
                                <span>{{ $operation->operationType?->name ?? 'Actividad' }}</span>
                                <strong>{{ $operation->name }}</strong>
                                <small>
                                    {{ $operation->operationStatus?->name ?? 'Sin estado' }}
                                    @if($operation->editor)
                                        · {{ $operation->editor->nick }}
                                    @endif
                                </small>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </article>
@endsection
