@extends('layouts.metopas')

@php
    $diaryCategory = \App\Support\CommunityForumCategory::diary();
@endphp
@section('title', ($diaryCategory['singular'] ?? 'Diario') . ' de ' . ($diary->author?->nick ?: $diary->author_nick))

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/community-forum.js') }}?v={{ filemtime(public_path('js/community-forum.js')) }}" defer></script>
    <script src="{{ asset('js/community-diary.js') }}?v={{ filemtime(public_path('js/community-diary.js')) }}" defer></script>
@endpush

@section('body-class', 'forum-body')

@section('content')
@php
    $author = $diary->author;
    $authorName = $author?->nick ?: $diary->author_nick;
@endphp
<div class="community-shell forum-thread-page diary-thread-page">
    <a class="community-kicker" style="color: {{ $diaryCategory['color'] ?? '#22c55e' }}" href="{{ route('community.diary.index') }}">← {{ $diaryCategory['label'] ?? 'Diarios' }}</a>

    <div class="thread-title-row">
        <div class="thread-owner-head">
            <div>
                <div class="thread-author-label">AUTOR DEL DIARIO</div>
                <h1 class="community-title" style="color: {{ $author?->getFrontendColor() ?? '#fff' }}">
                    {{ $diaryCategory['singular'] ?? 'Diario' }} de {{ $authorName }}
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

    <div class="diary-order-switch" aria-label="Orden de las entradas del diario">
        <span>Ordenar entradas:</span>
        <a
            @class(['is-active' => ($entryOrder ?? 'nuevos') === 'nuevos'])
            href="{{ route('community.diary.show', ['diary' => $diary, 'orden' => 'nuevos']) }}"
        >Más nuevas primero</a>
        <a
            @class(['is-active' => ($entryOrder ?? 'nuevos') === 'antiguos'])
            href="{{ route('community.diary.show', ['diary' => $diary, 'orden' => 'antiguos']) }}"
        >Más antiguas primero</a>
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

    @if($isOwner)
        <script type="application/json" id="diary-all-users-data">{!! json_encode($allUsers ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif

    @if($isOwner)
        <section class="community-panel forum-compose diary-compose">
            <div class="forum-compose__head">
                <div>
                    <span class="community-kicker">NUEVA ENTRADA</span>
                    <h2>Publicar en mi diario</h2>
                    <small>
                        Puedes vincular la entrada a un evento en el que hayas participado,
                        o dejarlo vacío si fue una tutoría, academia, práctica u otra actividad no programada.
                    </small>
                </div>
            </div>

            <form method="POST" action="{{ route('community.diary.store') }}" class="community-form">
                @csrf

                <div class="forum-field">
                    <label for="diary-entry-title">Título de la actividad realizada</label>
                    <input
                        id="diary-entry-title"
                        type="text"
                        name="entry_title"
                        maxlength="255"
                        required
                        value="{{ old('entry_title') }}"
                        placeholder="Ej. Tutoría de fusilero, práctica de academia, Lunes de prácticas..."
                        data-diary-entry-title
                    >
                </div>

                <div class="forum-field">
                    <label for="diary-event-id">Evento relacionado (opcional)</label>
                    <select id="diary-event-id" name="event_id" data-diary-event-select>
                        <option value="">Sin evento programado</option>
                        @foreach($availableEvents as $event)
                            <option
                                value="{{ $event->id }}"
                                data-entry-title="{{ $event->name }}"
                                @selected((string) old('event_id') === (string) $event->id)
                            >
                                {{ $event->date?->format('d/m/Y') }} · {{ $event->activity?->name ?? 'Actividad' }} · {{ $event->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="forum-field">
                    <label for="diary-squad-group">Escuadra / grupo (opcional)</label>
                    <input
                        id="diary-squad-group"
                        type="text"
                        name="squad_group"
                        maxlength="255"
                        value="{{ old('squad_group') }}"
                        placeholder="Ej. ALPHA 2-1"
                        data-diary-squad-group
                        @readonly(old('event_id'))
                    >
                </div>

                @include('community.partials.diary-roster-builder', [
                    'id' => 'new-entry-roster',
                    'eventSelectId' => 'diary-event-id',
                    'squadGroupInputId' => 'diary-squad-group',
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
    @endif

    <section class="diary-thread">
        @forelse($diary->entries as $entry)
            <section class="diary-conversation" id="entrada-{{ $entry->id }}">
            <article class="community-panel forum-message diary-thread-entry">
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
                        <span class="community-kicker">ACTIVIDAD</span>
                        <h2>{{ $entry->entry_title }}</h2>
                        <small>
                            @if($entry->event)
                                {{ $entry->event->date?->format('d/m/Y H:i') }}
                                · {{ $entry->event->name }}
                                @if($entry->event->activity?->name)
                                    · {{ $entry->event->activity->name }}
                                @endif
                                @if($entry->event?->activity?->activityType?->name)
                                    · {{ $entry->event->activity->activityType->name }}
                                @endif
                            @else
                                Sin evento programado vinculado
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
                            data-forum-quote-target="diary-reply-body-{{ $entry->id }}"
                        >Citar y comentar</button>

                    @if($entry->user_id === auth()->id())
                            <details class="forum-inline-editor">
                                <summary class="community-btn community-btn--ghost">Editar entrada</summary>
                                <form method="POST" action="{{ route('community.diary.update', $entry) }}" class="community-form">
                                    @csrf
                                    @method('PATCH')

                                    <div class="forum-field">
                                        <label for="edit-entry-title-{{ $entry->id }}">Título de la actividad</label>
                                        <input
                                            id="edit-entry-title-{{ $entry->id }}"
                                            type="text"
                                            name="entry_title"
                                            maxlength="255"
                                            required
                                            value="{{ $entry->event?->name ?? $entry->entry_title }}"
                                            @readonly($entry->event_id)
                                        >
                                    </div>

                                    <div class="forum-field">
                                        <label for="edit-entry-group-{{ $entry->id }}">Escuadra / grupo (opcional)</label>
                                        <input
                                            id="edit-entry-group-{{ $entry->id }}"
                                            type="text"
                                            name="squad_group"
                                            maxlength="255"
                                            value="{{ $entry->squad_group }}"
                                            placeholder="Ej. ALPHA 2-1"
                                            data-diary-squad-group
                                            @readonly($entry->event_id)
                                        >
                                    </div>

                                    @include('community.partials.diary-roster-builder', [
                                        'id' => 'edit-entry-roster-' . $entry->id,
                                        'eventId' => $entry->event_id,
                                        'squadGroupInputId' => 'edit-entry-group-' . $entry->id,
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

                            <form method="POST" action="{{ route('community.diary.destroy', $entry) }}" onsubmit="return confirm('¿Eliminar esta entrada y sus comentarios?')">
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

            <div class="diary-entry-comments">
                <div class="diary-entry-comments__head">
                    <span>Conversación de esta entrada</span>
                    <strong>{{ $entry->comments->count() }}</strong>
                </div>

                @foreach($entry->comments as $comment)
                    <article class="community-panel forum-message forum-comment diary-entry-comment" id="comentario-{{ $comment->id }}">
                        <div class="forum-message__content">
                            <div class="forum-post__meta">
                                <span class="thread-reply-label">COMENTARIO DE</span>
                                <span style="color: {{ $comment->author?->getFrontendColor() ?? '#fff' }}; font-weight:900">
                                    {{ $comment->author?->nick ?? 'Usuario eliminado' }}
                                </span>
                                · {{ $comment->created_at->format('d/m/Y H:i') }}
                                @if($comment->updated_at->gt($comment->created_at->copy()->addMinute()))
                                    · editado {{ $comment->updated_at->format('d/m/Y H:i') }}
                                @endif
                            </div>

                            <div class="forum-comment__body forum-rich">{!! \App\Support\ForumMarkup::render($comment->body) !!}</div>

                            <div class="forum-message__actions">
                                <button
                                    class="community-btn community-btn--ghost forum-quote-btn"
                                    type="button"
                                    data-forum-quote-source="quote-diary-comment-{{ $comment->id }}"
                                    data-forum-quote-author="{{ $comment->author?->nick ?? 'Usuario' }}"
                                    data-forum-quote-target="diary-reply-body-{{ $entry->id }}"
                                >Citar</button>

                                @if($comment->user_id === auth()->id() || auth()->user()->hasRole('admin'))
                                    <details class="forum-inline-editor">
                                        <summary class="community-btn community-btn--ghost">Editar</summary>
                                        <form method="POST" action="{{ route('community.diary.comments.update', [$diary, $comment]) }}" class="community-form">
                                            @csrf @method('PATCH')
                                            @include('community.partials.editor', [
                                                'id' => 'edit-diary-comment-' . $comment->id,
                                                'name' => 'body',
                                                'label' => 'Editar comentario',
                                                'value' => $comment->body,
                                                'rows' => 7,
                                            ])
                                            <button class="community-btn" type="submit">Guardar</button>
                                        </form>
                                    </details>

                                    <form method="POST" action="{{ route('community.diary.comments.destroy', [$diary, $comment]) }}" onsubmit="return confirm('¿Eliminar este comentario?')">
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
                @endforeach

                <form method="POST" action="{{ route('community.diary.comments.store', [$diary, $entry]) }}" class="community-form forum-reply-form diary-entry-comment-form">
                    @csrf
                    @include('community.partials.editor', [
                        'id' => 'diary-reply-body-' . $entry->id,
                        'name' => 'body',
                        'label' => 'Comentar esta entrada',
                        'value' => null,
                        'rows' => 6,
                    ])
                    <button class="community-btn" type="submit">Publicar comentario</button>
                </form>
            </div>
            </section>
        @empty
            <div class="community-empty">Este diario todavía no tiene entradas.</div>
        @endforelse
    </section>
</div>
@endsection
