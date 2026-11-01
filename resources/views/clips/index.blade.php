@extends('layouts.metopas')
@section('title', 'Clips de la comunidad')
@push('styles')<link rel="stylesheet" href="{{ asset('css/community-clips.css') }}?v=15">@endpush
@section('content')
<section class="sqa-clips-page container">
  <header class="sqa-clips-intro">
    <span class="section-label">SQUAD ALPHA · COMUNIDAD</span>
    <h1>Clips de la comunidad</h1>
    <p>Los mejores momentos compartidos por nuestros miembros.</p>
    @if(\App\Models\ClipYearContest::current())<p><a href="{{ route('clips.year') }}">☠ Clip del año {{ \App\Models\ClipYearContest::current()->year }}</a></p>@endif
  </header>
  @if(session('success'))<p class="sqa-clips-success">{{ session('success') }}</p>@endif
  @if($errors->any())<div class="sqa-clips-error">{{ $errors->first() }}</div>@endif
  @auth
    @if(auth()->user()->status?->name === 'ACTIVO' || auth()->user()->hasRole('admin'))
      <form action="{{ route('clips.store') }}" method="POST" class="sqa-clips-submit">
        @csrf
        <h2>Compartir un clip</h2>
        <label for="clip-url">Enlace de Twitch Clips o YouTube Shorts</label>
        <input id="clip-url" type="url" name="url" placeholder="https://clips.twitch.tv/..." maxlength="600" required value="{{ old('url') }}">
        <label for="clip-title">Título (opcional)</label>
        <input id="clip-title" type="text" name="title" maxlength="255" value="{{ old('title') }}">
        <div class="sqa-clip-extra-fields">
          <div class="sqa-clip-lookup" data-clip-lookup="events" data-url="{{ route('clips.lookup') }}">
            <label for="clip-event-query">Evento (opcional)</label>
            <input id="clip-event-query" type="text" autocomplete="off" placeholder="Buscar evento por nombre" aria-autocomplete="list" aria-expanded="false">
            <input type="hidden" name="event_id" value="{{ old('event_id') }}">
            <div class="sqa-clip-lookup__results" role="listbox" hidden></div>
          </div>
          <div class="sqa-clip-lookup" data-clip-multi-lookup="users" data-url="{{ route('clips.lookup') }}">
            <label for="clip-protagonist-query">Protagonistas (opcional)</label>
            <input id="clip-protagonist-query" type="text" autocomplete="off" placeholder="Buscar miembro por nick" aria-autocomplete="list" aria-expanded="false">
            <input type="hidden" name="protagonist_user_ids" value="{{ old('protagonist_user_ids') }}">
            <div class="sqa-clip-lookup__results" role="listbox" hidden></div>
            <div class="sqa-clip-selected-users" data-clip-selected-users></div>
          </div>
        </div>
        <button type="submit">Añadir clip</button>
      </form>
    @endif
  @endauth
  <div class="sqa-clips-list">
    @forelse($clips as $clip)
      <article class="sqa-clip-card">
        <button type="button" class="sqa-clip-card__open" data-clip-open="{{ $clip->id }}" aria-label="Reproducir {{ $clip->title }}">
          @if($clip->thumbnail_url)<img src="{{ $clip->thumbnail_url }}" alt="" loading="lazy">@else<div class="sqa-clip-card__placeholder">{{ strtoupper($clip->platform) }}</div>@endif
          <span class="sqa-clip-card__play">▶</span>
        </button>
        <div class="sqa-clip-card__info"><strong>{{ $clip->title }}</strong><small>{{ $clip->user?->nick ?? $clip->broadcaster_name ?? 'Squad ALPHA' }}@if($clip->protagonistNames()) · Protagonistas: {{ implode(', ', $clip->protagonistNames()) }}@endif · {{ $clip->clip_created_at?->format('d/m/Y') }}</small></div>
        @auth
          @if((int) $clip->user_id === (int) auth()->id() || auth()->user()->can('community-clips.delete') || auth()->user()->hasRole('admin'))
            <form method="POST" action="{{ route('clips.destroy', $clip) }}" onsubmit="return confirm('¿Retirar este clip?')">@csrf @method('DELETE')<button class="sqa-clip-remove" type="submit">Retirar</button></form>
          @endif
        @endauth
      </article>
    @empty
      <p class="sqa-clips-empty">Todavía no hay clips publicados. ¡Comparte el primero!</p>
    @endforelse
  </div>
  <div class="sqa-clips-pages">{{ $clips->links() }}</div>
</section>
@include('clips.partials.viewer', ['viewerClips' => $clips->items(), 'autoOpenClipId' => $autoOpenClipId ?? null])
@push('scripts')<script src="{{ asset('js/community-clips.js') }}?v=14" defer></script>@endpush
@endsection
