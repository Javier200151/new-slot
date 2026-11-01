@extends('layouts.metopas')
@section('title', 'Clip del año · Squad ALPHA')
@push('styles')<link rel="stylesheet" href="{{ asset('css/community-clips.css') }}?v=15">@endpush
@section('content')
<section class="sqa-clips-page container sqa-year-page">
    <header class="sqa-clips-intro"><span class="section-label">SQUAD ALPHA · CONCURSO ANUAL</span>
        <h1>Clip del año {{ $contest->year }}</h1>
        <p>@if($phase === 'nominations') Presenta tu candidato antes del {{ $contest->nomination_ends_at->format('d/m/Y') }}.
           @elseif($phase === 'voting') Valora los candidatos con 1 a 5 calaveras hasta el {{ $contest->voting_ends_at->format('d/m/Y') }}.
           @elseif($phase === 'results') Estos son los resultados del concurso.
           @else El concurso todavía no está abierto. @endif</p>
    </header>
    @php
        $showYearRules = trim($contest->rulesText()) !== '';
        $canSubmitYearClip = $phase === 'nominations' && $contest->allowsUser(auth()->user());
        $hasSubmittedYearClip = $canSubmitYearClip && $contest->nominations()->where('user_id', auth()->id())->exists();
    @endphp
    @if(session('success'))<p class="sqa-clips-success">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="sqa-clips-error">{{ $errors->first() }}</div>@endif
    @if($showYearRules || $canSubmitYearClip)
        <div @class(['sqa-year-intro-grid', 'sqa-year-intro-grid--two-col' => $showYearRules && $canSubmitYearClip])>
            @if($showYearRules)
                <section class="sqa-year-rules" aria-labelledby="sqa-year-rules-heading">
                    <details open>
                        <summary id="sqa-year-rules-heading">Bases y condiciones del concurso</summary>
                        <div class="sqa-year-rules__body">{{ $contest->rulesText() }}</div>
                    </details>
                </section>
            @endif
            @if($canSubmitYearClip)
                @if(! $hasSubmittedYearClip)
            <form action="{{ route('clips.year.nominate') }}" method="POST" class="sqa-clips-submit">
                @csrf <h2>Presentar candidato</h2>
                <label for="nomination-url">Enlace de Twitch Clips o YouTube Shorts</label>
                <input id="nomination-url" type="url" name="url" maxlength="600" required placeholder="https://clips.twitch.tv/..." value="{{ old('url') }}">
                <label for="nomination-description">Descripción del momento (opcional)</label>
                <textarea id="nomination-description" name="description" maxlength="900" rows="3">{{ old('description') }}</textarea>
                <div class="sqa-clip-extra-fields">
                    <div class="sqa-clip-lookup" data-clip-lookup="events" data-url="{{ route('clips.lookup') }}">
                        <label for="clip-year-event">Evento (opcional)</label>
                        <input id="clip-year-event" type="text" placeholder="Buscar evento" autocomplete="off">
                        <input type="hidden" name="event_id" value="{{ old('event_id') }}">
                        <div class="sqa-clip-lookup__results" role="listbox" hidden></div>
                    </div>
                    <div class="sqa-clip-lookup" data-clip-multi-lookup="users" data-url="{{ route('clips.lookup') }}">
                        <label for="clip-year-protagonists">Protagonistas (opcional)</label>
                        <input id="clip-year-protagonists" type="text" placeholder="Buscar miembros por nick" autocomplete="off">
                        <input type="hidden" name="protagonist_user_ids" value="{{ old('protagonist_user_ids') }}">
                        <div class="sqa-clip-lookup__results" role="listbox" hidden></div>
                        <div class="sqa-clip-selected-users" data-clip-selected-users></div>
                    </div>
                </div>
                <button type="submit">Presentar clip</button>
            </form>
                @else
                    <p class="sqa-year-already-submitted">Ya has presentado tu candidato para esta edición.</p>
                @endif
            @endif
        </div>
    @endif
    <div class="sqa-clips-list">
      @forelse($nominations as $entry)
        <article class="sqa-clip-card">
            <button class="sqa-clip-card__open" type="button" data-clip-open="{{ $entry->clip->id }}" aria-label="Reproducir {{ $entry->clip->title }}">
                @if($entry->clip->thumbnail_url)<img src="{{ $entry->clip->thumbnail_url }}" alt="" loading="lazy">@endif
                <span class="sqa-clip-card__play">▶</span>
            </button>
            <div class="sqa-clip-card__info">
                <strong>{{ $entry->clip->title }}</strong>
                <small>Presentado por {{ $entry->user?->nick }}@if($entry->clip->protagonistNames()) · Protagonistas: {{ implode(', ', $entry->clip->protagonistNames()) }}@endif</small>
                @if($entry->description)<p>{{ $entry->description }}</p>@endif
                @if($phase === 'results')<strong>{{ $loop->iteration }}.º · {{ $entry->votes_count }} votos · {{ $entry->votes_count ? number_format((float)$entry->votes_avg_skulls, 2) : 'Sin votos' }} calaveras</strong>@endif
                @if($phase === 'voting' && $contest->allowsUser(auth()->user()) && $entry->user_id !== auth()->id())
                    <form method="POST" action="{{ route('clips.year.vote', $entry) }}" class="sqa-clip-skulls">
                        @csrf
                        <span>¿Cuántas calaveras merece?</span>
                        <div class="sqa-clip-skulls__options">
                          @for($n=1;$n<=5;$n++)<label><input type="radio" name="skulls" value="{{ $n }}" required><span title="{{ $n }} calaveras">💀</span></label>@endfor
                        </div>
                        <button type="submit">Guardar valoración</button>
                    </form>
                @endif
            </div>
        </article>
      @empty<p>Aún no hay candidatos.</p>@endforelse
    </div>
</section>
@include('clips.partials.viewer', ['viewerClips' => $nominations->map->clip, 'viewerContest' => $contest])
@push('scripts')<script src="{{ asset('js/community-clips.js') }}?v=14" defer></script>@endpush
@endsection
