@extends('layouts.metopas')

@section('title', 'Ruleta')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/roulette.css') }}?v={{ filemtime(public_path('css/roulette.css')) }}">
@endpush

@section('content')
<div class="community-shell roulette-shell">
    <div class="roulette-page-head">
        <div>
            <span class="community-kicker">{{ \App\Support\CommunityArea::label(auth()->user()) }}</span>
            <h1 class="community-title">Ruleta de responsabilidad</h1>
            <p class="community-lead">
                Cuatro papeletas de salida, memoria para las tres últimas operaciones y absolutamente ninguna garantía de paz mental.
            </p>
        </div>

        @if($canManage && ! $currentRoom?->locksEventRegistration())
            <a class="community-btn" href="{{ route('community.roulette.create') }}">+ Crear sala</a>
        @endif
    </div>

    @if(session('roulette_status'))
        <div class="community-flash">{{ session('roulette_status') }}</div>
    @endif

    @if($currentRoom)
        <section class="roulette-current-card {{ $currentRoom->status === \App\Models\CommunityRouletteRoom::STATUS_COMPLETED ? 'is-completed' : '' }}">
            <div class="roulette-current-card__pulse"></div>
            <div>
                <span class="roulette-current-card__eyebrow">
                    {{ $currentRoom->locksEventRegistration() ? '● SALA EN DIRECTO' : 'ÚLTIMO RESULTADO' }}
                </span>
                <h2>{{ $currentRoom->event?->name ?: $currentRoom->event?->activity?->name ?: 'Evento' }}</h2>
                <p>
                    {{ $currentRoom->target_slot_group }} · {{ $currentRoom->target_slot_name }}
                    @if($currentRoom->targetSlotType) · {{ $currentRoom->targetSlotType->name }} @endif
                </p>
                @if($currentRoom->winner)
                    <strong class="roulette-current-card__winner">🎯 {{ $currentRoom->winner->nick }}</strong>
                @endif
            </div>
            <a class="community-btn community-btn--ghost" href="{{ route('community.roulette.show', $currentRoom) }}">
                {{ $currentRoom->locksEventRegistration() ? 'Entrar en la sala' : 'Ver resultado' }}
            </a>
        </section>
    @else
        <section class="roulette-empty-stage">
            <div class="roulette-empty-stage__wheel">?</div>
            <div>
                <h2>No hay ninguna ruleta activa</h2>
                <p>La tranquilidad es temporal. El histórico, por desgracia, es para siempre.</p>
            </div>
            @if($canManage)
                <a class="community-btn" href="{{ route('community.roulette.create') }}">Preparar una ruleta</a>
            @endif
        </section>
    @endif

    <section class="roulette-history">
        <div class="roulette-section-heading">
            <div>
                <span class="community-kicker">ARCHIVO DE DECISIONES DEL AZAR</span>
                <h2>Histórico</h2>
            </div>
        </div>

        <div class="roulette-history__list">
            @forelse($history as $historicalRoom)
                @php
                    $stateLabel = match($historicalRoom->status) {
                        \App\Models\CommunityRouletteRoom::STATUS_COMPLETED => 'Finalizada',
                        \App\Models\CommunityRouletteRoom::STATUS_CLOSED => 'Cerrada',
                        \App\Models\CommunityRouletteRoom::STATUS_EXPIRED => 'Caducada',
                        \App\Models\CommunityRouletteRoom::STATUS_FAILED => 'Incidencia',
                        default => ucfirst($historicalRoom->status),
                    };
                @endphp
                <a class="roulette-history-row" href="{{ route('community.roulette.show', $historicalRoom) }}">
                    <div>
                        <small>{{ $historicalRoom->created_at?->format('d/m/Y H:i') }}</small>
                        <strong>{{ $historicalRoom->event?->name ?: $historicalRoom->event?->activity?->name ?: 'Evento' }}</strong>
                        <span>{{ $historicalRoom->target_slot_group }} · {{ $historicalRoom->target_slot_name }}</span>
                    </div>
                    <div class="roulette-history-row__result">
                        @if($historicalRoom->winner)
                            <strong>🎯 {{ $historicalRoom->winner->nick }}</strong>
                        @endif
                        <span>{{ $stateLabel }}</span>
                    </div>
                </a>
            @empty
                <div class="community-empty">Todavía no hay salas en el histórico.</div>
            @endforelse
        </div>

        @if($history->hasPages())
            <div class="community-pagination">{{ $history->links() }}</div>
        @endif
    </section>
</div>
@endsection
