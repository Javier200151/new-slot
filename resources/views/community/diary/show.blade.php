@extends('layouts.metopas')

@section('title', 'Diario de ' . ($diary->author?->nick ?: $diary->author_nick))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/community-forum.js') }}?v={{ filemtime(public_path('js/community-forum.js')) }}" defer></script>
    <script src="{{ asset('js/community-diary.js') }}?v={{ filemtime(public_path('js/community-diary.js')) }}" defer></script>
@endpush

@section('content')
@php
    $author = $diary->author;
    $authorName = $author?->nick ?: $diary->author_nick;
@endphp
<div class="community-shell forum-thread-page diary-thread-page">
    <a class="community-kicker" href="{{ route('community.diary.index') }}">← Diarios</a>

    <div class="thread-title-row">
        <div class="thread-owner-head">
            <div>
                <div class="thread-author-label">AUTOR DEL DIARIO</div>
                <h1 class="community-title" style="color: {{ $author?->getFrontendColor() ?? '#fff' }}">
                    Diario de {{ $authorName }}
                </h1>
                <p class="community-lead" style="margin-bottom:0">
                    Iniciado {{ $diary->created_at->format('d/m/Y') }}
                    @if($author?->status?->name)
                        · Estado actual: {{ $author->status->name }}
                    @endif
                </p>
            </div>
            @if($isOwner)
                <span class="thread-owner-badge">TU DIARIO</span>
            @endif
        </div>

        @include('community.partials.subscription-bell', [
            'type' => 'diario',
            'subject' => $diary,
            'subscribed' => $isSubscribed,
        ])
    </div>

    @if(session('status') === 'subscription-enabled')
        <div class="community-flash">🔔 Recibirás avisos cuando haya nuevas entradas o respuestas en este diario.</div>
    @elseif(session('status') === 'subscription-disabled')
        <div class="community-notice">Has desactivado los avisos de este diario.</div>
    @elseif(session('status') === 'diary-started')
        <div class="community-flash">Tu diario está listo. Ya puedes publicar tu primera entrada.</div>
    @elseif(session('status'))
        <div class="community-flash">Diario actualizado.</div>
    @endif

    @if($errors->any())
        <div class="community-errors">
            <strong>Revisa el formulario:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if($isOwner && $missingEvents->isNotEmpty())
        <section class="community-panel forum-compose diary-compose">
            <div class="forum-compose__head">
                <div>
                    <span class="community-kicker">NUEVA ENTRADA</span>
                    <h2>Publicar en mi diario</h2>
                    <small>Solo aparecen eventos en los que constas en el ORBAT y que todavía no tienen entrada.</small>
                </div>
            </div>

            <form method="POST" action="{{ route('community.diary.store') }}" class="community-form">
                @csrf

                <div class="forum-field">
                    <label for="diary-event-id">Evento / operativo en el que participaste</label>
                    <select id="diary-event-id" name="event_id" required>
                        <option value="">Selecciona un evento…</option>
                        @foreach($missingEvents as $event)
                            <option value="{{ $event->id }}" @selected((string) old('event_id') === (string) $event->id)>
                                {{ $event->date?->format('d/m/Y') }} · {{ $event->operation?->name ?? 'Actividad' }} · {{ $event->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @include('community.partials.diary-roster-builder', [
                    'id' => 'new-entry-roster',
                    'eventSelectId' => 'diary-event-id',
                    'initialEventId' => old('event_id'),
                    'roster' => json_decode(old('squad_roster', '[]'), true) ?: [],
                ])

                @include('community.partials.editor', [
                    'id' => 'diary-entry-content',
                    'name' => 'content',
                    'label' => 'Bitácora',
                    'value' => old('content'),
                    'rows' => 11,
                ])

                <div class="community-actions">
                    <button class="community-btn" type="submit">Publicar entrada</button>
                </div>
            </form>
        </section>
    @elseif($isOwner && $missingEvents->isEmpty())
        <div class="community-notice">No tienes eventos pendientes de añadir al diario.</div>
    @endif

    <section class="diary-thread forum-comments">
        @forelse($diary->entries as $entry)
            <article class="community-panel forum-message diary-thread-entry" id="entrada-{{ $entry->id }}">
                <div class="forum-message__content">
                    <div class="forum-post__meta thread-originator">
                        <span class="thread-author-label">ENTRADA DE</span>
                        <span style="color: {{ $author?->getFrontendColor() ?? '#fff' }}; font-weight:900">{{ $authorName }}</span>
                        · {{ $entry->created_at->format('d/m/Y H:i') }}
                        @if($entry->updated_at->gt($entry->created_at->copy()->addMinute()))
                            · editada {{ $entry->updated_at->format('d/m/Y H:i') }}
                        @endif
                    </div>

                    <div class="diary-entry-event">
                        <span class="community-kicker">EVENTO</span>
                        <h2>{{ $entry->event?->name ?? 'Evento eliminado' }}</h2>
                        <small>
                            {{ $entry->event?->date?->format('d/m/Y H:i') }}
                            @if($entry->event?->operation?->name)
                                · {{ $entry->event->operation->name }}
                            @endif
                            @if($entry->event?->operation?->operationType?->name)
                                · {{ $entry->event->operation->operationType->name }}
                            @endif
                        </small>
                    </div>

                    @include('community.partials.diary-squad-roster', [
                        'roster' => $entry->squad_roster ?? [],
                        'group' => $entry->squad_group,
                    ])

                    <div class="forum-post__body forum-rich">{!! \App\Support\ForumMarkup::render($entry->content) !!}</div>

                    <div class="forum-message__actions">
                        <button
                            class="community-btn community-btn--ghost forum-quote-btn"
                            type="button"
                            data-forum-quote-source="quote-diary-entry-{{ $entry->id }}"
                            data-forum-quote-author="{{ $authorName }}"
                            data-forum-quote-target="diary-reply-body"
                        >Citar</button>

                    @if($entry->user_id === auth()->id())
                            <details class="forum-inline-editor">
                                <summary class="community-btn community-btn--ghost">Editar entrada</summary>
                                <form method="POST" action="{{ route('community.diary.update', $entry) }}" class="community-form">
                                    @csrf
                                    @method('PATCH')

                                    @include('community.partials.diary-roster-builder', [
                                        'id' => 'edit-entry-roster-' . $entry->id,
                                        'eventId' => $entry->event_id,
                                        'roster' => $entry->squad_roster ?? [],
                                    ])

                                    @include('community.partials.editor', [
                                        'id' => 'edit-diary-entry-' . $entry->id,
                                        'name' => 'content',
                                        'label' => 'Editar bitácora',
                                        'value' => $entry->content,
                                        'rows' => 9,
                                    ])
                                    <button class="community-btn" type="submit">Guardar cambios</button>
                                </form>
                            </details>

                            <form method="POST" action="{{ route('community.diary.destroy', $entry) }}" onsubmit="return confirm('¿Eliminar esta entrada del diario?')">
                                @csrf
                                @method('DELETE')
                                <button class="community-btn community-btn--danger" type="submit">Eliminar</button>
                            </form>
                    @endif
                    </div>
                    <template id="quote-diary-entry-{{ $entry->id }}">{{ $entry->content }}</template>
                </div>

                @include('community.partials.author-card', ['author' => $author])
                @include('community.partials.signature', ['author' => $author])
            </article>
        @empty
            <div class="community-empty">Este diario todavía no tiene entradas.</div>
        @endforelse
    </section>

    <section id="respuestas" class="community-panel diary-replies-panel">
        <div class="forum-section-head">
            <div>
                <span class="community-kicker">HILO DEL DIARIO</span>
                <h2>Respuestas y comentarios</h2>
                <p>Los usuarios con acceso al Área pueden comentar el diario sin modificar las entradas de su autor.</p>
            </div>
        </div>

        <div class="forum-comments">
            @forelse($diary->comments as $comment)
                <article class="community-panel forum-message forum-comment" id="comentario-{{ $comment->id }}">
                    <div class="forum-message__content">
                        <div class="forum-post__meta">
                            <span class="thread-reply-label">RESPUESTA DE</span>
                            <span style="color: {{ $comment->author?->getFrontendColor() ?? '#fff' }}; font-weight:900">
                                {{ $comment->author?->nick ?? 'Usuario eliminado' }}
                            </span>
                            · {{ $comment->created_at->format('d/m/Y H:i') }}
                            @if($comment->updated_at->gt($comment->created_at->copy()->addMinute()))
                                · editada {{ $comment->updated_at->format('d/m/Y H:i') }}
                            @endif
                        </div>

                        <div class="forum-comment__body forum-rich">{!! \App\Support\ForumMarkup::render($comment->body) !!}</div>

                        <div class="forum-message__actions">
                            <button
                                class="community-btn community-btn--ghost forum-quote-btn"
                                type="button"
                                data-forum-quote-source="quote-diary-comment-{{ $comment->id }}"
                                data-forum-quote-author="{{ $comment->author?->nick ?? 'Usuario' }}"
                                data-forum-quote-target="diary-reply-body"
                            >Citar</button>

                            @if($comment->user_id === auth()->id() || auth()->user()->hasRole('admin'))
                                <details class="forum-inline-editor">
                                    <summary class="community-btn community-btn--ghost">Editar</summary>
                                    <form method="POST" action="{{ route('community.diary.comments.update', [$diary, $comment]) }}" class="community-form">
                                        @csrf @method('PATCH')
                                        @include('community.partials.editor', [
                                            'id' => 'edit-diary-comment-' . $comment->id,
                                            'name' => 'body',
                                            'label' => 'Editar respuesta',
                                            'value' => $comment->body,
                                            'rows' => 7,
                                        ])
                                        <button class="community-btn" type="submit">Guardar</button>
                                    </form>
                                </details>

                                <form method="POST" action="{{ route('community.diary.comments.destroy', [$diary, $comment]) }}" onsubmit="return confirm('¿Eliminar esta respuesta?')">
                                    @csrf @method('DELETE')
                                    <button class="community-btn community-btn--danger" type="submit">Eliminar</button>
                                </form>
                            @endif
                        </div>
                        <template id="quote-diary-comment-{{ $comment->id }}">{{ $comment->body }}</template>
                    </div>

                    @include('community.partials.author-card', ['author' => $comment->author])
                    @include('community.partials.signature', ['author' => $comment->author])
                </article>
            @empty
                <div class="community-empty">Todavía no hay respuestas.</div>
            @endforelse
        </div>

        <form id="responder" method="POST" action="{{ route('community.diary.comments.store', $diary) }}" class="community-form forum-reply-form">
            @csrf
            @include('community.partials.editor', [
                'id' => 'diary-reply-body',
                'name' => 'body',
                'label' => 'Responder al diario de ' . $authorName,
                'value' => old('body'),
                'rows' => 9,
            ])
            <button class="community-btn" type="submit">Publicar respuesta</button>
        </form>
    </section>
</div>
@endsection
