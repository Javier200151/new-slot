@extends('layouts.metopas')

@section('title', 'Ruleta · ' . ($room->event?->name ?: $room->event?->activity?->name ?: 'Evento'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/roulette.css') }}?v={{ filemtime(public_path('css/roulette.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/roulette.js') }}?v={{ filemtime(public_path('js/roulette.js')) }}" defer></script>
@endpush

@section('content')
@php
    $selectedPreviousIds = $room->previousEvents->pluck('event_id')->filter()->values();
    $positiveCandidates = $room->candidates->where('tickets', '>', 0);
    $statusLabel = match($room->status) {
        \App\Models\CommunityRouletteRoom::STATUS_ACTIVE => 'Preparando',
        \App\Models\CommunityRouletteRoom::STATUS_SPINNING => 'Girando',
        \App\Models\CommunityRouletteRoom::STATUS_COMPLETED => 'Finalizada',
        \App\Models\CommunityRouletteRoom::STATUS_CLOSED => 'Cerrada',
        \App\Models\CommunityRouletteRoom::STATUS_EXPIRED => 'Caducada',
        \App\Models\CommunityRouletteRoom::STATUS_FAILED => 'Incidencia',
        default => ucfirst($room->status),
    };
@endphp

<div
    class="community-shell roulette-shell roulette-room"
    data-roulette-room
    data-state-url="{{ route('community.roulette.state', $room) }}"
    data-spin-url="{{ route('community.roulette.spin', $room) }}"
    data-csrf="{{ csrf_token() }}"
    data-room-id="{{ $room->id }}"
>
    <div class="roulette-room__topbar">
        <a class="community-kicker" href="{{ route('community.roulette.index') }}">← Ruleta</a>
        <div class="roulette-room__live-state">
            <span class="roulette-live-dot" data-roulette-live-dot></span>
            <strong data-roulette-status-label>{{ $statusLabel }}</strong>
        </div>
    </div>

    @if(session('roulette_status'))
        <div class="community-flash">{{ session('roulette_status') }}</div>
    @endif
    @if($errors->any())
        <div class="community-errors"><strong>{{ $errors->first() }}</strong></div>
    @endif

    <header class="roulette-room__hero">
        <div>
            <span class="community-kicker">SALA #{{ $room->id }}</span>
            <h1>{{ $room->event?->name ?: $room->event?->activity?->name ?: 'Evento' }}</h1>
            <p>
                Sorteando <b>{{ $room->target_slot_group }} · {{ $room->target_slot_name }}</b>
                @if($room->targetSlotType) · {{ $room->targetSlotType->name }} @endif
            </p>
        </div>
        <div class="roulette-room__meta">
            <span>Creada por <b>{{ $room->creator?->nick ?: 'Usuario' }}</b></span>
            <span>Caduca <b>{{ $room->expires_at?->format('H:i') }}</b></span>
            <a href="{{ route('events.show', $room->event_id) }}#orbat">Ver evento ↗</a>
        </div>
    </header>

    @if($room->locksEventRegistration())
        <section class="roulette-viewers-panel">
            <div>
                <span class="community-kicker">EN LA SALA</span>
                <strong><span data-roulette-viewer-count>{{ count($initialState['viewers'] ?? []) }}</span> espectador(es)</strong>
            </div>
            <div class="roulette-viewers" data-roulette-viewers>
                @foreach(($initialState['viewers'] ?? []) as $viewer)
                    <span>{{ $viewer['nick'] }}</span>
                @endforeach
            </div>
        </section>
    @endif

    <div class="roulette-main-grid">
        <section class="roulette-stage">
            <div class="roulette-stage__halo"></div>
            <div class="roulette-wheel-wrap">
                <div class="roulette-pointer" aria-hidden="true"></div>
                <canvas class="roulette-wheel" width="620" height="620" data-roulette-canvas></canvas>
                <div class="roulette-wheel__hub">SQA</div>
            </div>

            <div class="roulette-result" data-roulette-result @if($room->status !== \App\Models\CommunityRouletteRoom::STATUS_COMPLETED) hidden @endif>
                @if($room->winner)
                    <span>EL AZAR HA DECIDIDO</span>
                    <strong data-roulette-winner>{{ $room->winner->nick }}</strong>
                    <p data-roulette-phrase>{{ $room->winner_phrase_text }}</p>
                @else
                    <strong data-roulette-winner></strong>
                    <p data-roulette-phrase></p>
                @endif
            </div>

            @if($canControl && $room->canBeConfigured())
                <button
                    type="button"
                    class="roulette-spin-button"
                    data-roulette-spin
                    @disabled($positiveCandidates->isEmpty())
                >
                    <span>GIRAR</span>
                    <small>{{ $positiveCandidates->sum('tickets') }} papeletas en juego</small>
                </button>
            @elseif($room->status === \App\Models\CommunityRouletteRoom::STATUS_SPINNING)
                <div class="roulette-spin-wait">La ruleta está girando. La dignidad de alguien está en proceso.</div>
            @endif
        </section>

        <aside class="roulette-candidates-panel">
            <div class="roulette-section-heading">
                <div>
                    <span class="community-kicker">PAPELETAS</span>
                    <h2>Participantes</h2>
                </div>
                <span>{{ $room->candidates->count() }} apuntados</span>
            </div>

            <div class="roulette-candidates">
                @forelse($room->candidates as $candidate)
                    @php $details = $candidate->details ?? []; @endphp
                    <article
                        data-roulette-candidate-user-id="{{ $candidate->user_id }}"
                        @class([
                            'roulette-candidate',
                            'is-excluded' => $candidate->tickets === 0,
                            'is-winner' => $room->status === \App\Models\CommunityRouletteRoom::STATUS_COMPLETED
                                && (int) $room->winner_user_id === (int) $candidate->user_id,
                        ])
                    >
                        <div class="roulette-candidate__head">
                            <div>
                                <strong>{{ $candidate->nick_snapshot }}</strong>
                                <small>{{ $details['current']['slot_type'] ?? $candidate->currentSlotType?->name ?? 'Sin tipo' }}</small>
                            </div>
                            <span class="roulette-ticket-count">{{ $candidate->tickets }}</span>
                        </div>

                        @if($candidate->excluded_reason)
                            <p class="roulette-candidate__exclusion">{{ $candidate->excluded_reason }}</p>
                        @else
                            <p class="roulette-candidate__math">
                                4 iniciales
                                @if($candidate->previous_responsibility_count > 0)
                                    − {{ $candidate->previous_responsibility_count }} responsabilidad(es)
                                @endif
                                = <b>{{ $candidate->tickets }}</b>
                            </p>
                        @endif

                        @if(! empty($details['history']))
                            <details class="roulette-candidate__history">
                                <summary>Últimas operaciones</summary>
                                <div>
                                    @foreach($details['history'] as $historicalRole)
                                        <span @class(['is-responsibility' => $historicalRole['responsibility'] ?? false, 'is-hq' => $historicalRole['hq'] ?? false])>
                                            <b>{{ $historicalRole['event_date'] ?? '' }}</b>
                                            {{ $historicalRole['slot_type'] ?? 'Sin tipo' }}
                                            @if($historicalRole['hq'] ?? false) · HQ @elseif($historicalRole['responsibility'] ?? false) · −1 @endif
                                        </span>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </article>
                @empty
                    <div class="community-empty">No hay miembros SQA apuntados en el ORBAT actual.</div>
                @endforelse
            </div>
        </aside>
    </div>

    @if($canControl && $room->canBeConfigured())
        <details class="roulette-config" data-roulette-config open>
            <summary>
                <span>
                    <b>Ajustes excepcionales antes de girar</b>
                    <small>Estos cambios solo afectan a esta sala.</small>
                </span>
                <span>Editar criterios</span>
            </summary>

            <form method="POST" action="{{ route('community.roulette.update', $room) }}" class="roulette-config__form">
                @csrf
                @method('PATCH')

                <section>
                    <h3>Operaciones históricas</h3>
                    <p>Puedes sustituir cualquiera de las tres propuestas mientras la ruleta no haya empezado.</p>
                    <div class="roulette-history-selects">
                        @for($position = 0; $position < 3; $position++)
                            <label>
                                <span>Anterior {{ $position + 1 }}</span>
                                <select name="previous_event_ids[]">
                                    <option value="">Sin evento</option>
                                    @foreach($previousEventOptions as $previousEvent)
                                        <option value="{{ $previousEvent->id }}" @selected((int) ($selectedPreviousIds[$position] ?? 0) === (int) $previousEvent->id)>
                                            {{ $previousEvent->date?->format('d/m/Y') }} · {{ $previousEvent->name ?: $previousEvent->activity?->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @endfor
                    </div>
                </section>

                <section>
                    <h3>Qué cuenta como responsabilidad</h3>
                    <p>
                        Por defecto NewSlot considera responsabilidad cualquier tipo de slot que no permita RECLUTA.
                        Puedes cambiarlo aquí de forma excepcional. <b>Mando global</b> permanece fijo porque aplica la regla HQ = 0.
                    </p>
                    <div class="roulette-responsibility-grid">
                        @foreach($room->rules as $rule)
                            <label @class(['is-fixed' => $rule->is_hq])>
                                @if($rule->is_hq)
                                    <input type="hidden" name="responsibility_slot_type_ids[]" value="{{ $rule->slot_type_id }}">
                                @endif
                                <input
                                    type="checkbox"
                                    name="responsibility_slot_type_ids[]"
                                    value="{{ $rule->slot_type_id }}"
                                    @checked($rule->is_responsibility)
                                    @disabled($rule->is_hq)
                                >
                                <span>
                                    <strong>{{ $rule->slot_type_name_snapshot }}</strong>
                                    <small>{{ $rule->is_hq ? 'HQ · regla fija' : ($rule->source === 'manual' ? 'Excepción de esta sala' : 'Detectado por estados permitidos') }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <button class="community-btn" type="submit">Recalcular papeletas</button>
            </form>
        </details>
    @endif

    @if($canControl)
        <form
            method="POST"
            action="{{ route('community.roulette.repeat', $room) }}"
            class="roulette-repeat-room"
            data-roulette-repeat-room
            @if(! ($initialState['can_repeat'] ?? false)) hidden @endif
            onsubmit="return confirm('¿Repetir el sorteo? El ganador anterior quedará excluido, se intentará devolver a su slot previo y el ORBAT volverá a bloquearse.')"
        >
            @csrf
            <button class="community-btn" type="submit">↻ Repetir sorteo</button>
            <small>El ganador anterior no volverá a entrar en esta ruleta.</small>
        </form>
    @endif

    @if($canControl && $room->locksEventRegistration())
        <form method="POST" action="{{ route('community.roulette.destroy', $room) }}" class="roulette-close-room" data-roulette-close-room onsubmit="return confirm('¿Cerrar la sala? Se desbloquearán las inscripciones y esta ruleta pasará al histórico.')">
            @csrf
            @method('DELETE')
            <button type="submit">Cerrar sala y desbloquear ORBAT</button>
        </form>
    @endif

    <div class="community-notice roulette-live-error" data-roulette-failure @if(! $room->failure_reason) hidden @endif>
        @if($room->failure_reason)⚠ {{ $room->failure_reason }}@endif
    </div>
</div>
@endsection
