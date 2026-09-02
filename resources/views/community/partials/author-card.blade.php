@php
    $avatar = $author?->image
        ? asset('storage/' . $author->image)
        : asset('images/sqa-shield-white.png');
@endphp

<aside class="forum-author-card">
    @if($author)
        <a href="{{ route('users.show', ['user' => $author->nick]) }}" class="forum-author-card__avatar">
            <img src="{{ $avatar }}" alt="{{ $author->nick }}" loading="lazy">
        </a>
        <a
            href="{{ route('users.show', ['user' => $author->nick]) }}"
            class="forum-author-card__name"
            style="--author-color: {{ $author->getFrontendColor() }}"
        >
            {{ $author->nick }}
        </a>
        <small>
            {{ (int) ($author->community_posts_count ?? 0) }} publicaciones
            · {{ (int) ($author->community_comments_count ?? 0) }} comentarios
        </small>
        @if($author->mainSqaGroup?->name)
            <span class="forum-author-card__group">{{ $author->mainSqaGroup->name }}</span>
        @endif
    @else
        <div class="forum-author-card__avatar forum-author-card__avatar--empty">
            <img src="{{ asset('images/sqa-shield-white.png') }}" alt="Usuario eliminado">
        </div>
        <span class="forum-author-card__name" style="--author-color:#94a3b8">Usuario eliminado</span>
    @endif
</aside>
