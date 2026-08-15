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

                    @if($event->name && $event->name !== $operation->name)
                        <p class="event-detail__operation-name">{{ $operation->name }}</p>
                    @endif


                </div>

                @if($operation->image)
                    <figure class="event-detail__cover">
                        <img src="{{ asset('storage/' . $operation->image) }}" alt="{{ $operation->name }}">
                    </figure>
                @endif
            </header>

            <section class="event-detail__facts" aria-label="Datos del evento y del operativo">
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

            @if($operation->enemyFactions->isNotEmpty())
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
            @endif

            @if($descriptionSections->isNotEmpty())
                <section class="event-detail__section">
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

            <section class="event-detail__section event-detail__orbat" aria-labelledby="event-orbat-title">
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
                                            @php($assignment = $slot['assignment'])
                                            <div @class(['event-orbat__slot', 'is-occupied' => $assignment?->user_id || $assignment?->ally_id])>
                                                <div>
                                                    <strong>{{ $slot['name'] ?? 'Slot sin nombre' }}</strong>
                                                    {{-- <span>{{ $slot['slot_type']?->name ?? 'Sin tipo' }}</span> --}}
                                                </div>
                                                <span class="event-orbat__occupant">
                                                    {{ $assignment?->user?->nick ?? $assignment?->ally?->name ?? 'Libre' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endforeach
                    </div>
                @endif
            </section>

            @if($radioNetworks->isNotEmpty())
                <section class="event-detail__section">
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
                <section class="event-detail__section">
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
        </div>
    </article>
@endsection
