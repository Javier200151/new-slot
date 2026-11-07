@extends('layouts.metopas')

@section('title', 'Campañas')
@section('meta-description', 'Listado de campañas de Squad Alpha.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
@endpush

@section('content')
<div class="community-shell">
    <span class="community-kicker">Comunidad</span>
    <h1 class="community-title">Campañas</h1>
    <p class="community-lead">
        Archivo de campañas de Squad Alpha. Entra en una campaña para consultar sus eventos y actividad asociada.
    </p>

    <form method="GET" action="{{ route('campaigns.index') }}" class="campaign-sort-form" role="search">
        <div class="campaign-sort-form__field campaign-sort-form__field--search">
            <label for="campaign-search">Buscar campaña</label>
            <input id="campaign-search" type="search" name="q" value="{{ $selectedSearch }}" maxlength="100" placeholder="Nombre de la campaña">
        </div>

        <div class="campaign-sort-form__field">
            <label for="campaign-persistent">Tipo de campaña</label>
            <select id="campaign-persistent" name="persistent">
                <option value="all" @selected($selectedPersistent === 'all')>Todas</option>
                <option value="yes" @selected($selectedPersistent === 'yes')>Persistentes</option>
                <option value="no" @selected($selectedPersistent === 'no')>No persistentes</option>
            </select>
        </div>

        <div class="campaign-sort-form__field">
            <label for="campaign-events">Eventos</label>
            <select id="campaign-events" name="events">
                <option value="all" @selected($selectedEvents === 'all')>Todas</option>
                <option value="with" @selected($selectedEvents === 'with')>Con eventos</option>
                <option value="without" @selected($selectedEvents === 'without')>Sin eventos</option>
            </select>
        </div>

        <div class="campaign-sort-form__field">
            <label for="campaign-sort">Ordenar por</label>
            <select id="campaign-sort" name="sort">
                <option value="published_desc" @selected($selectedSort === 'published_desc')>Últimas publicadas</option>
                <option value="published_asc" @selected($selectedSort === 'published_asc')>Primeras publicadas</option>
                <option value="first_event_desc" @selected($selectedSort === 'first_event_desc')>Primer evento: más reciente</option>
                <option value="first_event_asc" @selected($selectedSort === 'first_event_asc')>Primer evento: más antiguo</option>
                <option value="name_asc" @selected($selectedSort === 'name_asc')>Nombre A–Z</option>
                <option value="name_desc" @selected($selectedSort === 'name_desc')>Nombre Z–A</option>
            </select>
        </div>

        <div class="campaign-sort-form__actions">
            <button type="submit" class="campaign-sort-form__submit">Aplicar</button>
            @if($selectedSearch !== '' || $selectedPersistent !== 'all' || $selectedEvents !== 'all' || $selectedSort !== 'published_desc')
                <a href="{{ route('campaigns.index') }}" class="campaign-sort-form__reset">Limpiar</a>
            @endif
        </div>
    </form>

    <p class="campaign-results-count" role="status">
        {{ $campaigns->count() }} {{ $campaigns->count() === 1 ? 'campaña encontrada' : 'campañas encontradas' }}
    </p>

    <div class="campaign-community-grid">
        @forelse($campaigns as $campaign)
            <a href="{{ route('campaigns.show', $campaign) }}" class="campaign-community-card">
                <div class="campaign-community-card__head">
                    <span class="community-kicker">Campaña</span>
                    @if($campaign->persistent)
                        <span class="campaign-community-card__persistent">Persistente</span>
                    @endif
                </div>

                <h2>{{ $campaign->name }}</h2>

                @if(filled($campaign->summary))
                    <p>{{ \Illuminate\Support\Str::limit($campaign->summary, 220) }}</p>
                @else
                    <p class="campaign-community-card__empty">Sin descripción.</p>
                @endif

                <div class="campaign-community-card__meta">
                    <span>{{ $campaign->operations_count }} actividades</span>
                    <span>{{ $campaign->events_count }} eventos</span>
                    <span title="Fecha de la primera partida activa o finalizada">
                        Primera partida: {{ $campaign->first_event_date_label ?? 'sin fecha' }}
                    </span>
                    <strong>Ver campaña →</strong>
                </div>
            </a>
        @empty
            <div class="community-empty">No hay campañas que coincidan con los filtros.</div>
        @endforelse
    </div>
</div>
@endsection
