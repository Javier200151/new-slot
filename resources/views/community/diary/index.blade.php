@extends('layouts.metopas')

@section('title', 'Diarios')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
@endpush

@section('body-class', 'forum-body')

@section('content')
<div class="community-shell forum-shell diary-forum-shell">
    <span class="community-kicker">{{ \App\Support\CommunityArea::label(auth()->user()) }}</span>
    <div class="forum-page-head">
        <div>
            <h1 class="community-title">Diarios</h1>
            <p class="community-lead">
                Bitácoras de reclutamiento con formato de foro. Están pensadas para reclutas, aunque un miembro también puede iniciar la suya si quiere conservar este formato de seguimiento.
            </p>
        </div>

        @if($myDiary)
            <a class="community-btn" href="{{ route('community.diary.show', $myDiary) }}">Abrir mi diario</a>
        @elseif($canStartDiary)
            <form method="POST" action="{{ route('community.diary.start') }}">
                @csrf
                <button class="community-btn" type="submit">+ Iniciar mi diario</button>
            </form>
        @endif
    </div>

    @if(session('status') === 'subscription-enabled')
        <div class="community-flash">🔔 Notificaciones activadas para ese diario.</div>
    @elseif(session('status') === 'subscription-disabled')
        <div class="community-notice">Notificaciones desactivadas.</div>
    @elseif(session('status'))
        <div class="community-flash">Cambios guardados.</div>
    @endif

    <div class="forum-list forum-list--real diary-forum-list">
        @forelse($diaries as $diary)
            @php
                $author = $diary->author;
                $authorName = $author?->nick ?: $diary->author_nick;
            @endphp
            <article class="forum-row forum-row--real diary-forum-row">
                <a class="forum-row__main" href="{{ route('community.diary.show', $diary) }}">
                    <div class="forum-row__badges">
                        <span>📓 Diario</span>
                        @if($author?->status?->name)<span>{{ $author->status->name }}</span>@endif
                    </div>
                    <h3 style="color: {{ $author?->getFrontendColor() ?? '#fff' }}">Diario de {{ $authorName }}</h3>
                    <div class="forum-row__meta">
                        <span class="thread-author-label">AUTOR DEL DIARIO</span>
                        <span class="forum-row__author" style="--author-color: {{ $author?->getFrontendColor() ?? '#fff' }}">{{ $authorName }}</span>
                        · última actividad {{ $diary->updated_at->format('d/m/Y H:i') }}
                    </div>
                </a>

                <div class="forum-row__stats">
                    <strong>{{ $diary->entries_count }}</strong><span>entradas</span>
                    <small>{{ $diary->comments_count }} respuestas</small>
                </div>

                @include('community.partials.subscription-bell', [
                    'type' => 'diario',
                    'subject' => $diary,
                    'subscribed' => (bool) $diary->is_subscribed,
                ])
            </article>
        @empty
            <div class="community-empty">Todavía no se ha iniciado ningún diario.</div>
        @endforelse
    </div>

    @if($diaries->hasPages())
        <nav class="community-pagination" aria-label="Paginación de diarios">
            @if($diaries->onFirstPage())<span>← Anterior</span>@else<a href="{{ $diaries->previousPageUrl() }}">← Anterior</a>@endif
            <strong>Página {{ $diaries->currentPage() }} de {{ $diaries->lastPage() }}</strong>
            @if($diaries->hasMorePages())<a href="{{ $diaries->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif
        </nav>
    @endif
</div>
@endsection
