@php
    $likedClipIds = auth()->check() ? \App\Models\CommunityClipLike::query()
        ->where('user_id', auth()->id())->whereIn('community_clip_id', collect($viewerClips)->pluck('id'))
        ->pluck('community_clip_id')->map(fn ($id) => (int) $id)->all() : [];
    // Los candidatos de la edición se identifican en los tres visores: portada,
    // comunidad y Clip del año. Solo una consulta para todos los clips visibles.
    $viewerContest = $viewerContest ?? \App\Models\ClipYearContest::current();
    $viewerPhase = $viewerContest?->phase();
    $clipIds = collect($viewerClips)->pluck('id')->filter()->all();
    $candidateNominations = $viewerContest && $clipIds !== []
        ? $viewerContest->nominations()->where('is_approved', true)
            ->whereIn('community_clip_id', $clipIds)->orderBy('id')->get()->keyBy('community_clip_id')
        : collect();
    $mySkulls = auth()->check() && $candidateNominations->isNotEmpty()
        ? \App\Models\ClipYearVote::query()
            ->where('user_id', auth()->id())
            ->whereIn('nomination_id', $candidateNominations->pluck('id'))
            ->pluck('skulls', 'nomination_id')
        : collect();
    $viewerCanParticipate = $viewerContest?->allowsUser(auth()->user()) ?? false;
    $clipData = collect($viewerClips)->map(fn ($clip) => [
        'id' => $clip->id,
        'title' => $clip->title,
        'member' => $clip->user?->nick ?? $clip->broadcaster_name ?? 'Squad ALPHA',
        'protagonists' => $clip->protagonistNames(),
        'event' => $clip->event?->name,
        'embed' => $clip->embedUrl(),
        'url' => $clip->url,
        'platform' => $clip->platform,
        'thumbnail' => $clip->thumbnail_url,
        'share' => route('clips.show', $clip),
        'likes' => $clip->likes_count ?? 0,
        'comments' => $clip->comments_count ?? 0,
        'liked' => in_array($clip->id, $likedClipIds ?? [], true),
        'candidate' => ($nomination = $candidateNominations->get($clip->id)) ? [
            'year' => $viewerContest->year,
            'phase' => $viewerPhase,
            'vote_url' => route('clips.year.vote', $nomination),
            'own' => auth()->check() && (int) $nomination->user_id === (int) auth()->id(),
            'can_vote' => $viewerPhase === 'voting'
                && $viewerCanParticipate
                && (int) $nomination->user_id !== (int) auth()->id(),
            'eligible' => $viewerCanParticipate,
            'previous_vote' => $mySkulls->get($nomination->id),
            'vote_starts' => $viewerContest->voting_starts_at?->format('d/m/Y'),
        ] : null,
        'like_url' => route('clips.likes.toggle', $clip),
        'comments_url' => route('clips.comments.index', $clip),
        'comment_create_url' => route('clips.comments.store', $clip),
    ])->values()->all();
@endphp
<div class="sqa-clip-viewer" data-clip-viewer data-auto-clip="{{ $autoOpenClipId ?? '' }}" data-csrf="{{ csrf_token() }}" data-logged-in="{{ auth()->check() ? '1' : '0' }}" hidden role="dialog" aria-modal="true" aria-label="Reproductor de clips">
    <button type="button" class="sqa-clip-viewer__close" data-clip-close aria-label="Cerrar visor">×</button>
    <div class="sqa-clip-viewer__preview" data-clip-preview aria-hidden="true"><img data-clip-preview-image alt=""><strong data-clip-preview-title></strong></div>
    <div class="sqa-clip-viewer__body" data-clip-stage>
        <div class="sqa-clip-viewer__player">
            <iframe data-clip-frame title="Clip de la comunidad" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            <button type="button" class="sqa-clip-viewer__nav sqa-clip-viewer__nav--prev" data-clip-prev aria-label="Clip anterior">‹</button>
            <button type="button" class="sqa-clip-viewer__nav sqa-clip-viewer__nav--next" data-clip-next aria-label="Clip siguiente">›</button>
        </div>
        <div class="sqa-clip-viewer__caption"><strong data-clip-title></strong><span data-clip-member></span><span data-clip-context></span><a data-clip-original target="_blank" rel="noopener noreferrer">Abrir en la plataforma ↗</a><small class="sqa-clip-viewer__help" data-clip-tutorial>Desliza hacia abajo para el siguiente o arriba para el anterior</small></div>
        <div class="sqa-clip-social" aria-label="Opciones del clip">
            <button type="button" data-clip-like aria-label="Me gusta" aria-pressed="false"><span aria-hidden="true">♥</span><small data-clip-likes>0</small></button>
            <button type="button" data-clip-vote-open aria-label="Valorar candidato" aria-expanded="false" aria-controls="sqa-clip-voting-panel" hidden><span aria-hidden="true">💀</span><small data-clip-vote-label>Votar</small></button>
            <button type="button" data-clip-comments-open aria-label="Ver comentarios"><span aria-hidden="true">▤</span><small data-clip-comments-count>0</small></button>
            <button type="button" data-clip-share aria-label="Compartir clip"><span aria-hidden="true">↗</span><small>Compartir</small></button>
        </div>
        <span class="sqa-clip-feedback" data-clip-feedback role="status" hidden></span>
    <div class="sqa-clip-viewer__swipe-area sqa-clip-viewer__swipe-area--left" aria-hidden="true"></div>
        <div class="sqa-clip-viewer__swipe-area sqa-clip-viewer__swipe-area--right" aria-hidden="true"></div>
    </div>
    <aside class="sqa-clip-comments" data-clip-comments-panel hidden aria-label="Comentarios del clip">
        <header><strong>Comentarios <span data-clip-comments-total></span></strong><button type="button" data-clip-comments-close aria-label="Cerrar comentarios">×</button></header>
        <div class="sqa-clip-comments__list" data-clip-comments-list aria-live="polite"></div>
        <button type="button" class="sqa-clip-comments__more" data-clip-comments-more hidden>Ver comentarios anteriores</button>
        @auth
            <form data-clip-comment-form>
                <label for="sqa-clip-comment-body">Escribe un comentario</label>
                <textarea id="sqa-clip-comment-body" name="body" rows="2" maxlength="1000" placeholder="Tu comentario…" required></textarea>
                <button type="submit">Publicar</button>
            </form>
        @else
            <p class="sqa-clip-comments__signin"><a href="{{ route('login') }}">Inicia sesión</a> para comentar o dar me gusta.</p>
        @endauth
    </aside>
    <form id="sqa-clip-voting-panel" class="sqa-clip-viewer__vote" data-clip-vote-form method="POST" hidden aria-label="Valoración del Clip del año">
        @csrf
        <button type="button" class="sqa-clip-viewer__vote-close" data-clip-vote-close aria-label="Cerrar votación">×</button>
        <strong data-clip-vote-heading>Valora este clip</strong>
        <p data-clip-vote-info>Selecciona de 1 a 5 calaveras. Cinco es la máxima valoración.</p>
        <div class="sqa-clip-viewer__skulls" role="radiogroup" aria-label="Calaveras para este clip" data-clip-skull-list>
            @for($n=1;$n<=5;$n++)
                <label title="{{ $n }} {{ $n === 1 ? 'calavera' : 'calaveras' }}">
                    <input type="radio" name="skulls" value="{{ $n }}" aria-label="{{ $n }} de 5 calaveras" required>
                    <span aria-hidden="true">💀</span>
                    <small>{{ $n }}</small>
                </label>
            @endfor
        </div>
        <button type="submit" class="sqa-clip-viewer__vote-submit" data-clip-vote-submit>Guardar valoración</button>
        <small data-clip-vote-saved hidden>Tu valoración se ha guardado. Puedes cambiarla hasta el cierre.</small>
    </form>
</div>
<script type="application/json" id="sqa-clip-data">{!! json_encode($clipData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

