@extends('layouts.metopas')

@section('title', $post->title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/community-forum.js') }}?v={{ filemtime(public_path('js/community-forum.js')) }}" defer></script>
@endpush

@section('body-class', 'forum-body')

@section('content')
<div class="community-shell forum-thread-page">
    <a class="community-kicker" href="{{ $channel === 'personal' ? route('community.forum.category', $categoryKey) : route('community.forum.index', $channel) }}">← {{ $channel === 'personal' ? ($category['label'] ?? 'Foro') : $channelTitle }}</a>

    <div class="thread-title-row">
        <div>
            <div class="forum-row__badges">
                @if($post->is_pinned)<span>📌 Fijado</span>@endif
                @if($process)
                    <span class="is-process">{{ \App\Models\CommunityProcess::typeOptions()[$process->type] ?? 'Proceso' }}</span>
                    <span>{{ \App\Models\CommunityProcess::statusOptions()[$process->effectiveStatus()] ?? $process->effectiveStatus() }}</span>
                @endif
                @if($post->poll)<span class="is-poll">🗳 Votación</span>@endif
                @if($post->is_locked)<span class="is-locked">🔒 Cerrado</span>@endif
            </div>
            <h1 class="community-title">{{ $post->title }}</h1>
        </div>

        <div class="thread-title-actions">
            @include('community.partials.subscription-bell', [
                'type' => 'hilo',
                'subject' => $post,
                'subscribed' => $isSubscribed,
            ])

        @if($canModerate || $canDeleteAny)
            <div class="thread-moderation-bar">
                @if($canModerate)
                    <form method="POST" action="{{ route('community.forum.lock', [$channel, $post]) }}">
                        @csrf @method('PATCH')
                        <button class="community-btn community-btn--ghost" type="submit">{{ $post->is_locked ? '🔓 Reabrir' : '🔒 Cerrar' }}</button>
                    </form>
                    <form method="POST" action="{{ route('community.forum.pin', [$channel, $post]) }}">
                        @csrf @method('PATCH')
                        <button class="community-btn community-btn--ghost" type="submit">{{ $post->is_pinned ? 'Desfijar' : '📌 Fijar' }}</button>
                    </form>
                @endif
                @if($canDeleteAny)
                    <form method="POST" action="{{ route('community.forum.destroy', [$channel, $post]) }}" onsubmit="return confirm('¿Eliminar este hilo, sus respuestas y el proceso/votación vinculados?')">
                        @csrf @method('DELETE')
                        <button class="community-btn community-btn--danger" type="submit">Eliminar hilo</button>
                    </form>
                @endif
            </div>
        @endif
        </div>
    </div>

    @if(session('status') === 'subscription-enabled')
        <div class="community-flash">🔔 Recibirás avisos cuando haya novedades en este hilo.</div>
    @elseif(session('status') === 'subscription-disabled')
        <div class="community-notice">Has desactivado los avisos de este hilo.</div>
    @elseif(session('status') === 'thread-locked')
        <div class="community-notice">El hilo está cerrado y no admite nuevas respuestas.</div>
    @elseif(session('status') === 'thread-reopened')
        <div class="community-flash">El hilo se ha reabierto.</div>
    @elseif(session('status') === 'thread-pinned')
        <div class="community-flash">El hilo se ha fijado.</div>
    @elseif(session('status') === 'thread-unpinned')
        <div class="community-flash">El hilo ya no está fijado.</div>
    @elseif(session('status') === 'application-saved')
        <div class="community-flash">Tu candidatura se ha guardado.</div>
    @elseif(session('status') === 'application-withdrawn')
        <div class="community-notice">Has retirado tu candidatura.</div>
    @elseif(session('status') === 'application-locked')
        <div class="community-notice">Esta convocatoria no permite editar una candidatura ya enviada.</div>
    @elseif(session('status') === 'poll-created')
        <div class="community-flash">La votación se ha creado y queda vinculada a este hilo.</div>
    @elseif(session('status') === 'vote-saved')
        <div class="community-flash">Tu voto se ha guardado.</div>
    @elseif(session('status') === 'poll-closed')
        <div class="community-notice">Esta votación ya está cerrada.</div>
    @elseif(session('status') === 'vote-locked')
        <div class="community-notice">Tu voto ya está registrado y no puede modificarse.</div>
    @elseif(session('status'))
        <div class="community-flash">Cambios guardados.</div>
    @endif

    @if($errors->any())
        <div class="community-errors">
            <strong>Revisa los datos:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <article class="community-panel forum-post forum-message" id="mensaje-inicial">
        <div class="forum-message__content">
            <div class="forum-post__meta thread-originator">
                <span class="thread-author-label">AUTOR DEL HILO</span>
                <span style="color: {{ $post->author?->getFrontendColor() ?? '#fff' }}; font-weight:900">
                    {{ $post->author?->nick ?? 'Usuario eliminado' }}
                </span>
                · {{ $post->created_at->format('d/m/Y H:i') }}
                @if($post->updated_at->gt($post->created_at->copy()->addMinute()))
                    · editado {{ $post->updated_at->format('d/m/Y H:i') }}
                @endif
            </div>

            <div class="forum-post__body forum-rich">{!! \App\Support\ForumMarkup::render($post->body) !!}</div>

            @include('community.partials.reactions', [
                'reactable' => $post,
                'reactionRoute' => route('community.forum.reactions.post', [$channel, $post]),
            ])

            <div class="forum-message__actions">
                <button
                    class="forum-message-action forum-quote-btn"
                    type="button"
                    data-forum-quote-source="quote-post-{{ $post->id }}"
                    data-forum-quote-author="{{ $post->author?->nick ?? 'Usuario' }}"
                    data-forum-quote-target="reply-body"
                    title="Citar mensaje"
                >
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H6.75A3.75 3.75 0 0 0 3 12v4.5m6-8.25L6 5.25m3 3-3 3M11.25 18.75h6A3.75 3.75 0 0 0 21 15v-3a3.75 3.75 0 0 0-3.75-3.75H12"/>
                    </svg>
                    <span>Citar</span>
                </button>

                @if($canManageThread)
                    <details class="forum-inline-editor">
                        <summary class="forum-message-action" title="Editar mensaje">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 2.651 2.651M18.375 3a1.875 1.875 0 0 1 2.652 2.652L8.25 18.429 3.75 19.5l1.071-4.5L17.598 2.223"/>
                            </svg>
                            <span>Editar</span>
                        </summary>
                        <form method="POST" action="{{ route('community.forum.update', [$channel, $post]) }}" class="community-form">
                            @csrf @method('PATCH')
                            <div class="forum-field">
                                <label>Título</label>
                                <input name="title" value="{{ $post->title }}" required maxlength="180">
                            </div>
                            @include('community.partials.editor', [
                                'id' => 'edit-post-body',
                                'name' => 'body',
                                'label' => 'Mensaje',
                                'value' => $post->body,
                                'rows' => 10,
                            ])
                            <button class="community-btn" type="submit">Guardar cambios</button>
                        </form>
                    </details>
                @endif

                @if($post->user_id === auth()->id() && !$canDeleteAny)
                    <form method="POST" action="{{ route('community.forum.destroy', [$channel, $post]) }}" onsubmit="return confirm('¿Eliminar tu hilo?')">
                        @csrf @method('DELETE')
                        <button class="forum-message-action forum-message-action--danger" type="submit" title="Eliminar mensaje">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9 14.4 18m-4.8 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.327L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-10.978.397c-.34.059-.68.114-1.022.165m1.022-.165a48.11 48.11 0 0 1 3.478-.397m7.5 0V4.477c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                            </svg>
                            <span>Eliminar</span>
                        </button>
                    </form>
                @endif
            </div>
            <template id="quote-post-{{ $post->id }}">{{ $post->body }}</template>
        </div>

        @include('community.partials.author-card', ['author' => $post->author])
        @include('community.partials.signature', ['author' => $post->author])
    </article>

    @if($process)
        @include('community.partials.process-panel', [
            'process' => $process,
            'myApplication' => $myApplication,
            'canManageProcess' => $canManageProcess,
        ])
    @endif

    @if($pollData)
        @include('community.polls._card', ['pollData' => $pollData, 'canVote' => $canVote])
    @elseif($canCreatePoll)
        <details class="community-panel thread-add-poll" @if($errors->has('poll_options')) open @endif>
            <summary>
                <span>🗳</span>
                <span><strong>Añadir votación a este hilo</strong><small>Puedes crearla ahora o después del debate/postulaciones.</small></span>
            </summary>
            <form method="POST" action="{{ route('community.polls.store-for-post', $post) }}" class="community-form" style="margin-top:18px">
                @csrf
                @include('community.partials.poll-form', [
                    'showEnableToggle' => false,
                    'canUseCandidates' => (bool) ($process && !$process->applicationsAreOpen() && $process->activeApplications->count() >= 2),
                    'candidateCount' => $process?->activeApplications->count() ?? 0,
                ])
                <button class="community-btn" type="submit">Crear votación</button>
            </form>
        </details>
    @endif

    <section id="respuestas" class="community-panel forum-replies-panel">
        <div class="forum-replies-head">
            <div>
                <span class="community-kicker">CONVERSACIÓN</span>
                <h2>Respuestas · {{ $post->comments->count() }}</h2>
            </div>
            @if(!$post->is_locked && $canReply)
                <a class="community-btn community-btn--ghost" href="#responder">Responder</a>
            @endif
        </div>

        <div class="forum-comments">
            @forelse($post->comments as $comment)
                <article class="forum-comment forum-message" id="respuesta-{{ $comment->id }}">
                    <div class="forum-message__content">
                        <div class="forum-post__meta">
                            <span class="thread-reply-label">#{{ $loop->iteration }}</span>
                            {{ $comment->created_at->format('d/m/Y H:i') }}
                            @if($comment->updated_at->gt($comment->created_at->copy()->addMinute()))
                                · editado {{ $comment->updated_at->format('d/m/Y H:i') }}
                            @endif
                        </div>

                        <div class="forum-comment__body forum-rich">{!! \App\Support\ForumMarkup::render($comment->body) !!}</div>

                        @include('community.partials.reactions', [
                            'reactable' => $comment,
                            'reactionRoute' => route('community.forum.reactions.comment', [$channel, $post, $comment]),
                        ])

                        <div class="forum-message__actions">
                            @if(!$post->is_locked && $canReply)
                                <button
                                    class="forum-message-action forum-quote-btn"
                                    type="button"
                                    data-forum-quote-source="quote-comment-{{ $comment->id }}"
                                    data-forum-quote-author="{{ $comment->author?->nick ?? 'Usuario' }}"
                                    data-forum-quote-target="reply-body"
                                    title="Citar respuesta"
                                >
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H6.75A3.75 3.75 0 0 0 3 12v4.5m6-8.25L6 5.25m3 3-3 3M11.25 18.75h6A3.75 3.75 0 0 0 21 15v-3a3.75 3.75 0 0 0-3.75-3.75H12"/>
                                    </svg>
                                    <span>Citar</span>
                                </button>
                            @endif

                            @if($comment->user_id === auth()->id() || auth()->user()->hasRole('admin'))
                                <details class="forum-inline-editor">
                                    <summary class="forum-message-action" title="Editar mensaje">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 2.651 2.651M18.375 3a1.875 1.875 0 0 1 2.652 2.652L8.25 18.429 3.75 19.5l1.071-4.5L17.598 2.223"/>
                                        </svg>
                                        <span>Editar</span>
                                    </summary>
                                    <form method="POST" action="{{ route('community.forum.comments.update', [$channel, $post, $comment]) }}" class="community-form">
                                        @csrf @method('PATCH')
                                        @include('community.partials.editor', [
                                            'id' => 'edit-comment-' . $comment->id,
                                            'name' => 'body',
                                            'label' => 'Editar respuesta',
                                            'value' => $comment->body,
                                            'rows' => 7,
                                        ])
                                        <button class="community-btn" type="submit">Guardar</button>
                                    </form>
                                </details>
                            @endif

                            @if($comment->user_id === auth()->id() || $canDeleteAny)
                                <form method="POST" action="{{ route('community.forum.comments.destroy', [$channel, $post, $comment]) }}" onsubmit="return confirm('¿Eliminar esta respuesta?')">
                                    @csrf @method('DELETE')
                                    <button class="forum-message-action forum-message-action--danger" type="submit" title="Eliminar mensaje">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9 14.4 18m-4.8 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.327L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-10.978.397c-.34.059-.68.114-1.022.165m1.022-.165a48.11 48.11 0 0 1 3.478-.397m7.5 0V4.477c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                        </svg>
                                        <span>Eliminar</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                        <template id="quote-comment-{{ $comment->id }}">{{ $comment->body }}</template>
                    </div>

                    @include('community.partials.author-card', ['author' => $comment->author])
                    @include('community.partials.signature', ['author' => $comment->author])
                </article>
            @empty
                <div class="community-empty">Todavía no hay respuestas.</div>
            @endforelse
        </div>

        @if($post->is_locked)
            <div class="community-notice" style="margin-top:20px">
                🔒 Este hilo ha sido cerrado por moderación. Puede seguir leyéndose, pero no admite nuevas respuestas.
            </div>
        @elseif(!$canReply)
            <div class="community-notice" style="margin-top:20px">
                Puedes leer este hilo, pero tu rol no tiene permiso para responder en esta categoría.
            </div>
        @else
            <form id="responder" method="POST" action="{{ route('community.forum.comments.store', [$channel, $post]) }}" class="community-form forum-reply-form">
                @csrf
                @include('community.partials.editor', [
                    'id' => 'reply-body',
                    'name' => 'body',
                    'label' => 'Responder al hilo',
                    'value' => old('body'),
                    'rows' => 9,
                ])
                <button class="community-btn" type="submit">Publicar respuesta</button>
            </form>
        @endif
    </section>
</div>
@endsection
