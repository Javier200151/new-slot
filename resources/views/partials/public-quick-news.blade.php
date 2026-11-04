@php
    $newsUser = auth()->user();
    $showNewsForum = \App\Services\QuickAccessNewsService::canSeeForum($newsUser);
    $showNewsEvents = \App\Services\QuickAccessNewsService::canSeeEvents($newsUser);
@endphp
<details class="sqa-quick-access__news" data-quick-news-url="{{ route('quick-access.news') }}">
    <summary class="sqa-quick-access__button sqa-quick-access__news-trigger" title="{{ $label }}" aria-label="{{ $label }}">
        <svg aria-hidden="true" viewBox="0 0 24 24"><use href="{{ asset('images/quick-access-icons.svg') }}#sqa-{{ $icon }}"></use></svg>
        <span class="sqa-quick-access__news-badge" data-quick-news-total hidden>0</span>
    </summary>
    <div class="sqa-quick-access__news-panel" aria-label="Novedades de Squad ALPHA">
        <strong>Novedades en SquadALPHA</strong>
        <p>Mensajes y eventos pendientes de revisar.</p>
        @if($showNewsForum)
            <a href="{{ route('community.forum.unread') }}" class="sqa-quick-access__news-link">
                <span>Foro</span>
                <span class="sqa-quick-access__news-number" data-quick-news-forum>0</span>
            </a>
        @endif
        @if($showNewsEvents)
            <a href="{{ route('events.news') }}" class="sqa-quick-access__news-link">
                <span>Eventos</span>
                <span class="sqa-quick-access__news-number" data-quick-news-events>0</span>
            </a>
        @endif
    </div>
</details>
