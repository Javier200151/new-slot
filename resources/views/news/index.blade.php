@extends('layouts.metopas')

@section('title', 'Tablón de anuncios')
@section('meta-description', 'Anuncios y novedades de Squad ALPHA.')

@section('content')
<section class="home-news-section home-news-archive" data-sqa-news-section data-sqa-account="{{ auth()->id() ?? 'guest' }}" data-sqa-latest="{{ $news->max(fn ($item) => ($item->published_at ?? $item->created_at)?->timestamp ?? 0) ?? 0 }}">
    <div class="container">
        <header class="home-section-heading">
            <div><span class="section-index">01</span><span class="section-label">Comunidad</span></div>
            <div class="home-section-heading__copy">
                <h1>Tablón de anuncios</h1>
                <p>Altas, promociones, grupos de trabajo y comunicados de Squad ALPHA.</p>
            </div>
        </header>
        @if($news->isNotEmpty())
            @include('news.partials.cards', ['news' => $news, 'isArchive' => true])
        @else
            <div class="home-empty-state"><p>Aún no hay anuncios publicados.</p></div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/home-bulletin.js') }}?v={{ filemtime(public_path('js/home-bulletin.js')) }}" defer></script>
@endpush
