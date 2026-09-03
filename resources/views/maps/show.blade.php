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
                                <img src="{{ asset('storage/' . $map->platform->image) }}" alt="" width="32" height="32" style="width:32px;height:32px;max-width:32px;max-height:32px;object-fit:contain;">
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

            <section class="map-page__activities" aria-labelledby="map-activities-title">
                <header class="map-page__activities-header">
                    <span>Historial del mapa</span>
                    <h2 id="map-activities-title">
                        {{ $map->activities->count() }}
                        {{ $map->activities->count() === 1 ? 'actividad creada' : 'actividades creadas' }}
                        en {{ $map->name }}
                    </h2>
                </header>

                @if($map->activities->isEmpty())
                    <div class="map-page__activities-empty">
                        Todavía no hay actividades asociadas a este mapa.
                    </div>
                @else
                    <div class="map-page__activities-grid">
                        @foreach($map->activities as $activity)
                            <a
                                href="{{ route('activities.show', $activity) }}"
                                class="map-page__operation-card"
                                @style([
                                    '--operation-color: ' . ($activity->activityType?->color ?? '')
                                        => filled($activity->activityType?->color),
                                ])
                            >
                                <span>{{ $activity->activityType?->name ?? 'Actividad' }}</span>
                                <strong>{{ $activity->name }}</strong>
                                <small>
                                    {{ $activity->activityStatus?->name ?? 'Sin estado' }}
                                    @if($activity->editor)
                                        · {{ $activity->editor->nick }}
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
