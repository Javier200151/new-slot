@extends('layouts.metopas')

@section('title', 'Crear ruleta')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/roulette.css') }}?v={{ filemtime(public_path('css/roulette.css')) }}">
@endpush

@section('content')
<div class="community-shell roulette-shell">
    <a class="community-kicker" href="{{ route('community.roulette.index') }}">← Ruleta</a>
    <h1 class="community-title">Preparar una sala</h1>
    <p class="community-lead">
        Al crearla se congelan las inscripciones y movimientos públicos del ORBAT de ese evento hasta que haya ganador, cierres la sala o caduque.
    </p>

    @if($errors->any())
        <div class="community-errors">
            <strong>No se puede crear la sala:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($events->isEmpty())
        <div class="community-notice">No hay eventos ACTIVOS de tipo OPERACIÓN disponibles para crear una ruleta.</div>
    @else
        <section class="community-panel roulette-create-step">
            <span class="roulette-step-number">1</span>
            <div>
                <h2>Evento actual</h2>
                <p>Selecciona la partida para la que necesitas cubrir el slot.</p>
            </div>
            <form method="GET" action="{{ route('community.roulette.create') }}" class="roulette-event-selector">
                <select name="event_id" onchange="this.form.submit()">
                    @foreach($events as $eventOption)
                        <option value="{{ $eventOption->id }}" @selected((int) $selectedEvent?->id === (int) $eventOption->id)>
                            {{ $eventOption->date?->format('d/m/Y H:i') }} · {{ $eventOption->name ?: $eventOption->activity?->name }}
                        </option>
                    @endforeach
                </select>
                <noscript><button class="community-btn" type="submit">Cambiar</button></noscript>
            </form>
        </section>

        <form method="POST" action="{{ route('community.roulette.store') }}" class="roulette-create-form">
            @csrf
            <input type="hidden" name="event_id" value="{{ $selectedEvent?->id }}">

            <section class="community-panel roulette-create-step">
                <span class="roulette-step-number">2</span>
                <div>
                    <h2>Slot que va a decidir el destino</h2>
                    <p>Solo aparecen slots visibles y libres del ORBAT actual.</p>
                </div>
                <div class="roulette-field roulette-field--wide">
                    <label for="target-slot">Slot a sortear</label>
                    <select id="target-slot" name="target_slot_key" required>
                        <option value="">Selecciona un slot…</option>
                        @foreach($targetSlots as $targetSlot)
                            <option value="{{ $targetSlot['key'] }}" @selected(old('target_slot_key') === $targetSlot['key'])>
                                {{ $targetSlot['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @if($targetSlots === [])
                        <small>No hay slots libres disponibles en este evento.</small>
                    @endif
                </div>
            </section>

            <section class="community-panel roulette-create-step">
                <span class="roulette-step-number">3</span>
                <div>
                    <h2>Tres operaciones anteriores</h2>
                    <p>NewSlot propone automáticamente las tres anteriores por fecha. Puedes sustituir cualquiera por una excepción histórica.</p>
                </div>
                <div class="roulette-history-selects">
                    @for($position = 0; $position < 3; $position++)
                        @php
                            $oldPrevious = old('previous_event_ids.' . $position, $defaultPreviousEventIds[$position] ?? null);
                        @endphp
                        <label>
                            <span>Anterior {{ $position + 1 }}</span>
                            <select name="previous_event_ids[]">
                                <option value="">Sin evento</option>
                                @foreach($previousEvents as $previousEvent)
                                    <option value="{{ $previousEvent->id }}" @selected((int) $oldPrevious === (int) $previousEvent->id)>
                                        {{ $previousEvent->date?->format('d/m/Y') }} · {{ $previousEvent->name ?: $previousEvent->activity?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endfor
                </div>
            </section>

            <section class="roulette-rule-summary">
                <h3>Reglas que se aplicarán</h3>
                <div class="roulette-rule-summary__grid">
                    <span><b>4</b> papeletas iniciales</span>
                    <span><b>−1</b> por responsabilidad en cada anterior, mínimo 1</span>
                    <span><b>0</b> si lleva responsabilidad ahora</span>
                    <span><b>0</b> si fue Mando global en una anterior</span>
                    <span><b>0</b> reclutas</span>
                    <span><b>0</b> miembros con menos de 3 meses</span>
                </div>
                <p>Después de crear la sala podrás revisar y cambiar excepcionalmente qué tipos de slot cuentan como responsabilidad antes de girar.</p>
            </section>

            <div class="community-actions">
                <a class="community-btn community-btn--ghost" href="{{ route('community.roulette.index') }}">Cancelar</a>
                <button class="community-btn" type="submit" @disabled($targetSlots === [])>Crear sala y congelar ORBAT</button>
            </div>
        </form>
    @endif
</div>
@endsection
