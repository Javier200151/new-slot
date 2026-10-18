@php
    $quickAccessConfig = \App\Support\PublicQuickAccessNavigation::settings();
    $quickAccessItems = $quickAccessConfig['is_enabled']
        ? \App\Support\PublicQuickAccessNavigation::visibleItems($quickAccessConfig['items'])
        : [];
@endphp
@if($quickAccessItems !== [])
    <link rel="stylesheet" href="{{ asset('css/quick-access.css') }}?v={{ filemtime(public_path('css/quick-access.css')) }}">
    <nav class="sqa-quick-access sqa-quick-access--{{ $quickAccessConfig['position'] }}" aria-label="Accesos rápidos" data-quick-access>
        <button type="button" class="sqa-quick-access__toggle" data-quick-access-toggle aria-controls="sqa-quick-access-links" aria-expanded="false" aria-label="Mostrar accesos rápidos" title="Accesos rápidos">
            <svg aria-hidden="true" viewBox="0 0 24 24"><use href="{{ asset('images/quick-access-icons.svg') }}#sqa-squares-2x2"></use></svg>
        </button>
        <div id="sqa-quick-access-links" class="sqa-quick-access__links">
            @foreach($quickAccessItems as $quickAccessItem)
                @if($quickAccessItem['type'] === 'server_status')
                    @include('partials.public-server-status-dropdown', ['mode' => 'quick', 'label' => $quickAccessItem['label'], 'icon' => $quickAccessItem['icon']])
                @elseif($quickAccessItem['type'] === 'scroll_top')
                    <button type="button" class="sqa-quick-access__button" aria-label="{{ $quickAccessItem['label'] }}" title="{{ $quickAccessItem['label'] }}" data-quick-access-top>
                        <svg aria-hidden="true" viewBox="0 0 24 24"><use href="{{ asset('images/quick-access-icons.svg') }}#sqa-{{ $quickAccessItem['icon'] }}"></use></svg>
                    </button>
                @else
                    <a class="sqa-quick-access__button" href="{{ \App\Support\PublicNavigation::itemUrl($quickAccessItem) }}" aria-label="{{ $quickAccessItem['label'] }}" title="{{ $quickAccessItem['label'] }}"
                        @if(\App\Support\PublicNavigation::itemIsExternal($quickAccessItem)) target="_blank" rel="noopener noreferrer" @endif>
                        <svg aria-hidden="true" viewBox="0 0 24 24"><use href="{{ asset('images/quick-access-icons.svg') }}#sqa-{{ $quickAccessItem['icon'] }}"></use></svg>
                    </a>
                @endif
            @endforeach
        </div>
    </nav>
    @if(collect($quickAccessItems)->contains(fn ($item) => $item['type'] === 'server_status')
        && ! collect(\App\Support\PublicFooterNavigation::visibleItems())->contains(fn ($item) => $item['type'] === 'server_status'))
        @once
            <script src="{{ asset('js/infrastructure-status.js') }}?v={{ filemtime(public_path('js/infrastructure-status.js')) }}" defer></script>
        @endonce
    @endif
    <script src="{{ asset('js/quick-access.js') }}?v={{ filemtime(public_path('js/quick-access.js')) }}" defer></script>
@endif
