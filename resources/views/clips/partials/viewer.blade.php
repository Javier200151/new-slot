@php
    $likedClipIds = auth()->check() ? \App\Models\CommunityClipLike::query()
        ->where('user_id', auth()->id())->whereIn('community_clip_id', collect($viewerClips)->pluck('id'))
        ->pluck('community_clip_id')->map(fn ($id) => (int) $id)->all() : [];
    $clipData = collect($viewerClips)->map(fn ($clip) => [
        'id' => $clip->id,
        'title' => $clip->title,
        'member' => $clip->user?->nick ?? $clip->broadcaster_name ?? 'Squad ALPHA',
        'protagonists' => $clip->protagonistNames(),
        'event' => $clip->event?->name,
        'embed' => $clip->embedUrl(),
        'url' => $clip->url,
        'platform' => $clip->platform,
        'share' => route('clips.show', $clip),
        'likes' => $clip->likes_count ?? 0,
        'comments' => $clip->comments_count ?? 0,
        'liked' => in_array($clip->id, $likedClipIds ?? [], true),
        'like_url' => route('clips.likes.toggle', $clip),
        'comments_url' => route('clips.comments.index', $clip),
        'comment_create_url' => route('clips.comments.store', $clip),
    ])->values()->all();
@endphp
<div class="sqa-clip-viewer" data-clip-viewer data-auto-clip="{{ $autoOpenClipId ?? '' }}" data-csrf="{{ csrf_token() }}" data-logged-in="{{ auth()->check() ? '1' : '0' }}" hidden role="dialog" aria-modal="true" aria-label="Reproductor de clips">
    <button type="button" class="sqa-clip-viewer__close" data-clip-close aria-label="Cerrar visor">×</button>
    <button type="button" class="sqa-clip-viewer__nav sqa-clip-viewer__nav--prev" data-clip-prev aria-label="Clip anterior">‹</button>
    <div class="sqa-clip-viewer__body" data-clip-stage>
        <div class="sqa-clip-viewer__player"><iframe data-clip-frame title="Clip de la comunidad" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
        <div class="sqa-clip-viewer__caption"><strong data-clip-title></strong><span data-clip-member></span><span data-clip-context></span><a data-clip-original target="_blank" rel="noopener noreferrer">Abrir en la plataforma ↗</a><small class="sqa-clip-viewer__help" data-clip-tutorial>Desliza hacia arriba o abajo para cambiar de clip</small></div>
        <div class="sqa-clip-social" aria-label="Opciones del clip">
            <button type="button" data-clip-like aria-label="Me gusta" aria-pressed="false"><span aria-hidden="true">♥</span><small data-clip-likes>0</small></button>
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
    @if(isset($voteNominees) && $voteNominees->isNotEmpty())
        <form class="sqa-clip-viewer__vote" data-clip-vote-form method="POST" hidden>
            @csrf
            <span>Valora este clip</span>
            <div class="sqa-clip-viewer__skulls" role="radiogroup" aria-label="Valoración del clip">
                @for($n=1;$n<=5;$n++)
                    <label><input type="radio" name="skulls" value="{{ $n }}" required><span>☠</span></label>
                @endfor
            </div>
            <button type="submit">Votar</button>
        </form>
    @endif
    <button type="button" class="sqa-clip-viewer__nav sqa-clip-viewer__nav--next" data-clip-next aria-label="Clip siguiente">›</button>
</div>
<script type="application/json" id="sqa-clip-data">{!! json_encode($clipData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

@if(isset($voteNominees) && $voteNominees->isNotEmpty())
<script type="application/json" id="sqa-clip-vote-data">{!! json_encode($voteNominees->mapWithKeys(fn ($entry) => [(string)$entry->community_clip_id => route('clips.year.vote', $entry)])->all(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
