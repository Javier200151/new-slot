@extends('layouts.metopas')

@section('title', $channel === 'personal' && $category ? $category['label'] . ' · Foro' : $channelTitle)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/community-forum.js') }}?v={{ filemtime(public_path('js/community-forum.js')) }}" defer></script>
@endpush

@section('body-class', 'forum-body')

@section('content')
<div class="community-shell forum-shell">
    <span class="community-kicker">{{ \App\Support\CommunityArea::label(auth()->user()) }}</span>

    @if($channel === 'personal' && !$category)
        <div class="forum-page-head forum-page-head--categories">
            <div>
                <h1 class="community-title">Foro</h1>
                <p class="community-lead">{{ $channelDescription }}</p>
            </div>
        </div>

        @if(session('status'))
            <div class="community-flash">Cambios guardados.</div>
        @endif

        <section class="forum-category-grid" aria-label="Categorías del Foro">
            @foreach($categories as $item)
                <a
                    class="forum-category-card"
                    href="{{ $item['url'] }}"
                    style="--forum-category-color: {{ $item['color'] ?? '#f59e0b' }}"
                >
                    <span class="forum-category-card__icon">{{ $item['icon'] }}</span>
                    <div class="forum-category-card__body">
                        <div class="forum-category-card__title-row">
                            <h2>{{ $item['label'] }}</h2>
                            @unless($item['can_create'])
                                <span class="forum-category-card__readonly">Solo lectura</span>
                            @endunless
                        </div>
                        <p>{{ $item['description'] }}</p>
                        <div class="forum-category-card__stats">
                            <span><strong>{{ $item['threads_count'] }}</strong> hilos</span>
                            <span><strong>{{ $item['replies_count'] }}</strong> respuestas</span>
                        </div>
                        @if($item['last_activity'])
                            <small>
                                Última actividad: {{ $item['last_title'] }}
                                · {{ $item['last_activity']->format('d/m/Y H:i') }}
                            </small>
                        @else
                            <small>Todavía no hay publicaciones.</small>
                        @endif
                    </div>
                    <span class="forum-category-card__arrow">→</span>
                </a>
            @endforeach
        </section>
    @else
        @php
            $isPersonalCategory = $channel === 'personal' && $category;
            $forumTitle = $isPersonalCategory ? $category['label'] : $channelTitle;
            $forumDescription = $isPersonalCategory ? $category['description'] : $channelDescription;
        @endphp

        @if($isPersonalCategory)
            <a class="community-kicker forum-back-link" href="{{ route('community.forum.home') }}">← Foro</a>
        @endif

        <div class="forum-page-head">
            <div>
                @if($isPersonalCategory)
                    <div class="forum-category-heading">
                        <span>{{ $category['icon'] }}</span>
                        <div>
                            <h1 class="community-title">{{ $forumTitle }}</h1>
                            <p class="community-lead">{{ $forumDescription }}</p>
                        </div>
                    </div>
                @else
                    <h1 class="community-title">{{ $forumTitle }}</h1>
                    <p class="community-lead">{{ $forumDescription }}</p>
                @endif
            </div>
            @if($canCreate)
                <button class="community-btn" type="button" data-forum-compose-open>+ Nuevo hilo</button>
            @else
                <span class="forum-category-permission-note">Tu rol puede leer y responder, pero no abrir hilos aquí.</span>
            @endif
        </div>

        @if(session('status') === 'subscription-enabled')
            <div class="community-flash">🔔 Notificaciones activadas para este hilo.</div>
        @elseif(session('status') === 'subscription-disabled')
            <div class="community-notice">Notificaciones desactivadas para este hilo.</div>
        @elseif(session('status') === 'post-deleted')
            <div class="community-flash">La publicación se ha eliminado.</div>
        @elseif(session('status'))
            <div class="community-flash">Cambios guardados.</div>
        @endif

        @if($errors->any())
            <div class="community-errors">
                <strong>Revisa el formulario:</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="forum-tools" aria-label="Herramientas del foro">
            <form method="GET" action="{{ $isPersonalCategory ? route('community.forum.category', $categoryKey) : route('community.forum.index', $channel) }}" class="forum-search-form">
                <input type="search" name="q" value="{{ $search }}" placeholder="Buscar por título, mensaje o autor…">
                <select name="filtro">
                    <option value="all" @selected($filter === 'all')>Todos</option>
                    @if($channel === 'personal')<option value="poll" @selected($filter === 'poll')>Con votación</option>@endif
                    <option value="locked" @selected($filter === 'locked')>Cerrados</option>
                </select>
                <button class="community-btn community-btn--ghost" type="submit">Buscar / filtrar</button>
                @if($search !== '' || $filter !== 'all')
                    <a class="community-btn community-btn--ghost" href="{{ $isPersonalCategory ? route('community.forum.category', $categoryKey) : route('community.forum.index', $channel) }}">Limpiar</a>
                @endif
            </form>
        </section>

        @if($canCreate)
            <section class="community-panel forum-compose" data-forum-compose @if(!$errors->any()) hidden @endif>
                <div class="forum-compose__head">
                    <div>
                        <span class="community-kicker">NUEVA PUBLICACIÓN</span>
                        <h2>{{ $isPersonalCategory ? 'Nuevo ' . mb_strtolower($category['singular']) : 'Crear hilo' }}</h2>
                        @if($isPersonalCategory)<small>{{ $category['hint'] }}</small>@endif
                    </div>
                    <button type="button" class="forum-compose__close" data-forum-compose-close aria-label="Cerrar">×</button>
                </div>

                <form
                    method="POST"
                    action="{{ $isPersonalCategory ? route('community.forum.category.store', $categoryKey) : route('community.forum.store', $channel) }}"
                    class="community-form forum-thread-form"
                >
                    @csrf

                    <div class="forum-field">
                        <label for="thread-title">Título</label>
                        <input id="thread-title" name="title" value="{{ old('title') }}" maxlength="180" required placeholder="Un título claro para el hilo">
                    </div>

                    @include('community.partials.editor', [
                        'id' => 'thread-body',
                        'name' => 'body',
                        'label' => 'Mensaje',
                        'value' => old('body'),
                        'rows' => 11,
                    ])

                    @if($isPersonalCategory && $categoryKey === 'convocatoria')
                        <div class="forum-process-config">
                            <div class="forum-config-head">
                                <div>
                                    <span class="community-kicker">CONVOCATORIA</span>
                                    <h3>Postulaciones</h3>
                                </div>
                                <small>Configura el plazo y quién puede postularse. Todo se gestionará después dentro del propio hilo.</small>
                            </div>

                            <label class="forum-switch forum-switch--major">
                                <input type="checkbox" name="process_applications_enabled" value="1" @checked(old('process_applications_enabled', true))>
                                <span><strong>Permitir postulaciones</strong><small>Los usuarios elegibles podrán presentar su candidatura dentro del hilo.</small></span>
                            </label>

                            <div class="forum-form-grid forum-form-grid--3">
                                <div class="forum-field">
                                    <label>Inicio</label>
                                    <input type="datetime-local" name="process_applications_start_at" value="{{ old('process_applications_start_at') }}">
                                </div>
                                <div class="forum-field">
                                    <label>Cierre</label>
                                    <input type="datetime-local" name="process_applications_end_at" value="{{ old('process_applications_end_at') }}">
                                </div>
                                <div class="forum-field">
                                    <label>Máximo de elegidos</label>
                                    <input type="number" name="process_max_winners" min="1" max="20" value="{{ old('process_max_winners', 1) }}">
                                </div>
                            </div>

                            <div class="forum-check-grid">
                                <label class="forum-switch">
                                    <input type="checkbox" name="process_allow_application_edit" value="1" @checked(old('process_allow_application_edit', true))>
                                    <span><strong>Permitir editar candidatura</strong></span>
                                </label>
                                <label class="forum-switch">
                                    <input type="checkbox" name="process_allow_application_withdraw" value="1" @checked(old('process_allow_application_withdraw', true))>
                                    <span><strong>Permitir retirar candidatura</strong></span>
                                </label>
                            </div>

                            <div class="forum-field">
                                <label>Quién puede postularse</label>
                                <div class="forum-inline-checks">
                                    @foreach(['ACTIVO' => 'Miembro', 'RESERVA' => 'Reserva', 'RECLUTA' => 'Recluta'] as $status => $label)
                                        <label>
                                            <input type="checkbox" name="process_eligible_statuses[]" value="{{ $status }}" @checked(in_array($status, old('process_eligible_statuses', ['ACTIVO']), true))>
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($isPersonalCategory)
                        @include('community.partials.poll-form', ['showEnableToggle' => true])
                    @endif

                    <div class="community-actions forum-compose__actions">
                        <button type="submit" class="community-btn">Publicar hilo</button>
                        <button type="button" class="community-btn community-btn--ghost" data-forum-compose-close>Cancelar</button>
                    </div>
                </form>
            </section>
        @endif

        <div class="forum-list forum-list--real">
            @forelse($posts as $post)
                @php
                    $processStatus = $post->process?->effectiveStatus();
                    $processLabel = $post->process ? (\App\Models\CommunityProcess::typeOptions()[$post->process->type] ?? 'Proceso') : null;
                @endphp

                <article class="forum-row forum-row--real">
                    <a class="forum-row__main" href="{{ route('community.forum.show', [$channel, $post]) }}">
                        <div class="forum-row__badges">
                            @if($post->is_pinned)<span>📌 Fijado</span>@endif
                            @if($processLabel)<span class="is-process">{{ $processLabel }}</span>@endif
                            @if($post->process)<span>{{ \App\Models\CommunityProcess::statusOptions()[$processStatus] ?? $processStatus }}</span>@endif
                            @if($post->poll)<span class="is-poll">🗳 Votación</span>@endif
                            @if($post->is_locked)<span class="is-locked">🔒 Cerrado</span>@endif
                        </div>
                        <h3>{{ $post->title }}</h3>
                        <div class="forum-row__meta">
                            <span class="thread-author-label">INICIADO POR</span>
                            <span class="forum-row__author" style="--author-color: {{ $post->author?->getFrontendColor() ?? '#fff' }}">{{ $post->author?->nick ?? 'Usuario eliminado' }}</span>
                            · última actividad {{ $post->updated_at->format('d/m/Y H:i') }}
                        </div>
                    </a>

                    <div class="forum-row__stats">
                        <strong>{{ $post->comments_count }}</strong><span>respuestas</span>
                        @if($post->process?->applications_enabled)<small>{{ $post->process->activeApplications->count() }} postulaciones</small>@endif
                    </div>

                    @include('community.partials.subscription-bell', [
                        'type' => 'hilo',
                        'subject' => $post,
                        'subscribed' => (bool) $post->is_subscribed,
                    ])

                    @if($canModerate || $canDeleteAny)
                        <div class="forum-row__moderation">
                            @if($canModerate)
                                <form method="POST" action="{{ route('community.forum.lock', [$channel, $post]) }}">@csrf @method('PATCH')<button type="submit" title="{{ $post->is_locked ? 'Reabrir' : 'Cerrar' }}">{{ $post->is_locked ? '🔓' : '🔒' }}</button></form>
                                <form method="POST" action="{{ route('community.forum.pin', [$channel, $post]) }}">@csrf @method('PATCH')<button type="submit" title="{{ $post->is_pinned ? 'Desfijar' : 'Fijar' }}">📌</button></form>
                            @endif
                            @if($canDeleteAny)
                                <form method="POST" action="{{ route('community.forum.destroy', [$channel, $post]) }}" onsubmit="return confirm('¿Eliminar este hilo y su contenido asociado?')">@csrf @method('DELETE')<button type="submit" class="is-danger" title="Eliminar">🗑</button></form>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <div class="community-empty">Todavía no hay hilos en esta categoría.</div>
            @endforelse
        </div>

        @if($posts->hasPages())
            <nav class="community-pagination" aria-label="Paginación del foro">
                @if($posts->onFirstPage())<span>← Anterior</span>@else<a href="{{ $posts->previousPageUrl() }}">← Anterior</a>@endif
                <strong>Página {{ $posts->currentPage() }} de {{ $posts->lastPage() }}</strong>
                @if($posts->hasMorePages())<a href="{{ $posts->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif
            </nav>
        @endif
    @endif
</div>
@endsection
