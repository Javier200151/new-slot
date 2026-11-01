@extends('layouts.metopas')
@section('title', 'Clip del año · Squad ALPHA')
@push('styles')<link rel="stylesheet" href="{{ asset('css/community-clips.css') }}?v=5">@endpush
@section('content')
<section class="sqa-clips-page container sqa-year-page">
    <header class="sqa-clips-intro"><span class="section-label">SQUAD ALPHA · CONCURSO ANUAL</span>
        <h1>Clip del año {{ $contest->year }}</h1>
        <p>@if($phase === 'nominations') Presenta tu candidato antes del {{ $contest->nomination_ends_at->format('d/m/Y') }}.
           @elseif($phase === 'voting') Valora los candidatos con 1 a 5 calaveras hasta el {{ $contest->voting_ends_at->format('d/m/Y') }}.
           @elseif($phase === 'results') Estos son los resultados del concurso.
           @else El concurso todavía no está abierto. @endif</p>
    </header>
    @if(session('success'))<p class="sqa-clips-success">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="sqa-clips-error">{{ $errors->first() }}</div>@endif
    @if($phase === 'nominations' && (auth()->user()?->status?->name === 'ACTIVO' || auth()->user()?->hasRole('admin')))
        @if(! $contest->nominations()->where('user_id', auth()->id())->exists())
            <form action="{{ route('clips.year.nominate') }}" method="POST" class="sqa-clips-submit">
                @csrf <h2>Presentar candidato</h2>
                <label for="nomination-url">Enlace de Twitch Clips o YouTube Shorts</label>
                <input id="nomination-url" type="url" name="url" maxlength="600" required placeholder="https://clips.twitch.tv/..." value="{{ old('url') }}">
                <label for="nomination-description">Descripción del momento (opcional)</label>
                <textarea id="nomination-description" name="description" maxlength="900" rows="3">{{ old('description') }}</textarea>
                <button type="submit">Presentar clip</button>
            </form>
        @else<p>Ya has presentado tu candidato para esta edición.</p>@endif
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
                <small>Presentado por {{ $entry->user?->nick }}</small>
                @if($entry->description)<p>{{ $entry->description }}</p>@endif
                @if($phase === 'results')<strong>{{ $loop->iteration }}.º · {{ $entry->votes_count }} votos · {{ $entry->votes_count ? number_format((float)$entry->votes_avg_skulls, 2) : 'Sin votos' }} calaveras</strong>@endif
                @if($phase === 'voting' && (auth()->user()?->status?->name === 'ACTIVO' || auth()->user()?->hasRole('admin')) && $entry->user_id !== auth()->id())
                    <form method="POST" action="{{ route('clips.year.vote', $entry) }}" class="sqa-clip-skulls">
                        @csrf
                        <span>¿Cuántas calaveras merece?</span>
                        <div class="sqa-clip-skulls__options">
                          @for($n=1;$n<=5;$n++)<label><input type="radio" name="skulls" value="{{ $n }}" required><span title="{{ $n }} calaveras">☠</span></label>@endfor
                        </div>
                        <button type="submit">Guardar valoración</button>
                    </form>
                @endif
            </div>
        </article>
      @empty<p>Aún no hay candidatos.</p>@endforelse
    </div>
</section>
@include('clips.partials.viewer', ['viewerClips' => $nominations->map->clip, 'voteNominees' => $phase === 'voting' && (auth()->user()?->status?->name === 'ACTIVO' || auth()->user()?->hasRole('admin')) ? $nominations->filter(fn ($n) => $n->user_id !== auth()->id()) : collect()])
@push('scripts')<script src="{{ asset('js/community-clips.js') }}?v=5" defer></script>@endpush
@endsection
