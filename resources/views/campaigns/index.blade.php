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
                    <strong>Ver campaña →</strong>
                </div>
            </a>
        @empty
            <div class="community-empty">Todavía no hay campañas registradas.</div>
        @endforelse
    </div>
</div>
@endsection
