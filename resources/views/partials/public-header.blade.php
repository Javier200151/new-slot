<link rel="stylesheet" href="{{ asset('css/sqa-dialog.css') }}?v={{ filemtime(public_path('css/sqa-dialog.css')) }}">
<script src="{{ asset('js/sqa-dialog.js') }}?v={{ filemtime(public_path('js/sqa-dialog.js')) }}" defer></script>
@php
    $navUser = auth()->user();
    $publicNavigationItems = \App\Support\PublicNavigation::items();
@endphp

<header class="landing-header">
    <div class="container nav-wrapper">
        <a href="{{ route('home') }}" class="brand brand--image" aria-label="Squad ALPHA">
            <img
                src="{{ asset('images/sqa-header-logo.png') }}"
                alt="Squad ALPHA"
                class="brand-logo-image"
            >
        </a>

        <div class="nav-mobile-tools">
            <button
                type="button"
                class="nav-toggle"
                aria-label="Mostrar menú"
                aria-controls="public-navigation"
                aria-expanded="false"
                data-nav-toggle
            >
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

        <div id="public-navigation" class="nav-menu" data-nav-menu>
            <nav class="landing-nav" aria-label="Navegación principal">
                @foreach($publicNavigationItems as $navigationItem)
                    @if(in_array(($navigationItem['type'] ?? null), ['link', 'external'], true))
                        @php
                            $destination = (string) ($navigationItem['destination'] ?? '');
                            $isExternal = \App\Support\PublicNavigation::itemIsExternal($navigationItem);
                            $canDisplay = \App\Support\PublicNavigation::canDisplayItem($navigationItem)
                                && \App\Support\PublicNavigation::canDisplayNavigationItem($navigationItem);
                        @endphp

                        @if($canDisplay)
                            <a
                                href="{{ \App\Support\PublicNavigation::itemUrl($navigationItem) }}"
                                @class([
                                    'is-active' => \App\Support\PublicNavigation::navigationItemIsActive($navigationItem),
                                ])
                                @if($isExternal) target="_blank" rel="noopener noreferrer" @endif
                            >
                                {{ $navigationItem['label'] }}@if($isExternal) <span aria-hidden="true">↗</span>@endif
                            </a>
                        @endif
                    @elseif(($navigationItem['type'] ?? null) === 'dropdown')
                        @php
                            $dropdownVisible = \App\Support\PublicNavigation::canDisplayItem($navigationItem);
                            $children = $dropdownVisible
                                ? \App\Support\PublicNavigation::visibleChildren($navigationItem['children'] ?? [])
                                : [];
                            $dropdownLabel = \App\Support\PublicNavigation::dropdownDisplayLabel($navigationItem);
                        @endphp

                        @if($children !== [])
                            <details @class([
                                'nav-dropdown',
                                'is-active' => \App\Support\PublicNavigation::dropdownIsActive($children),
                            ])>
                                <summary>{{ $dropdownLabel }}</summary>
                                <div class="nav-dropdown__menu">
                                    @foreach($children as $navigationChild)
                                        @php
                                            $childDestination = (string) ($navigationChild['destination'] ?? '');
                                            $childExternal = \App\Support\PublicNavigation::itemIsExternal($navigationChild);
                                        @endphp

                                        <a
                                            href="{{ \App\Support\PublicNavigation::itemUrl($navigationChild) }}"
                                            @if($childExternal) target="_blank" rel="noopener noreferrer" @endif
                                        >
                                            {{ $navigationChild['label'] }}@if($childExternal) <span aria-hidden="true">↗</span>@endif
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endif
                @endforeach
            </nav>

            <div @class(['nav-actions', 'nav-actions--guest' => ! $navUser, 'nav-actions--authenticated' => (bool) $navUser])>
                @guest
                    <a
                        href="{{ route('login') }}"
                        class="btn btn-outline"
                        @if(request()->routeIs('home')) data-open-modal="login-modal" @endif
                    >
                        Iniciar sesión
                    </a>

                    <a
                        href="{{ route('public.register') }}"
                        class="btn btn-primary"
                        @if(request()->routeIs('home')) data-open-modal="register-modal" @endif
                    >
                        Crear cuenta
                    </a>
                @else
                    @include('partials.notification-bell')

                    <a href="{{ route('profile.show') }}" class="btn btn-outline">
                        Mi perfil
                    </a>

                    @if(
                        $navUser->hasRole('admin')
                        || $navUser->can('filament.access')
                        || $navUser->can('event-calendar.view')
                        || $navUser->can('event-calendar.reserve')
                        || $navUser->can('event-calendar.manage')
                    )
                        <a href="{{ url('/admin') }}" class="btn btn-outline">
                            Administración
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="logout-form">
                        @csrf
                        <button type="submit" class="btn btn-primary">Cerrar sesión</button>
                    </form>
                @endguest
            </div>
        </div>
    </div>
</header>

@include('partials.public-quick-access')

@php
    $publicRaffleShortcut = app(\App\Services\CommunityRaffleService::class)->featured();
    $clipYearShortcut = auth()->check() ? \App\Models\ClipYearContest::current() : null;
    $showClipShortcut = $clipYearShortcut
        && ! request()->routeIs('clips.year')
        && $clipYearShortcut->allowsUser(auth()->user())
        && in_array($clipYearShortcut->phase(), ['nominations', 'voting'], true);
@endphp
@if($publicRaffleShortcut || $showClipShortcut)
    <link rel="stylesheet" href="{{ asset('css/community-raffles.css') }}?v={{ filemtime(public_path('css/community-raffles.css')) }}">
    <nav class="sqa-community-shortcuts" aria-label="Avisos de la comunidad" data-community-shortcuts>
        @if($showClipShortcut)
            <div class="sqa-community-shortcuts__item" data-shortcut-item>
                <a class="sqa-year-shortcut{{ $clipYearShortcut->phase() === 'nominations' ? ' sqa-year-shortcut--nominations' : '' }}" href="{{ route('clips.year') }}">
                    ☠ {{ $clipYearShortcut->tabText() }}
                </a>
                <button type="button" class="sqa-community-shortcuts__handle" data-shortcut-handle aria-label="Mostrar u ocultar Clip del año" aria-expanded="false">☠</button>
            </div>
        @endif
        @if($publicRaffleShortcut && ! request()->routeIs('raffles.show'))
            <div class="sqa-community-shortcuts__item" data-shortcut-item>
                <a class="sqa-raffle-shortcut{{ $publicRaffleShortcut->status === 'closed' ? ' is-closed' : '' }}" href="{{ route('raffles.show', $publicRaffleShortcut) }}">
                    🎁 {{ $publicRaffleShortcut->title }}
                </a>
                <button type="button" class="sqa-community-shortcuts__handle" data-shortcut-handle aria-label="Mostrar u ocultar sorteo" aria-expanded="false">🎁</button>
            </div>
        @endif
    </nav>
    @once
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var mobile = window.matchMedia('(max-width: 767px)');
                document.querySelectorAll('[data-shortcut-item]').forEach(function (item) {
                    var handle = item.querySelector('[data-shortcut-handle]');
                    var link = item.querySelector('a');
                    var startX = null;
                    var startY = null;
                    var moved = false;
                    function setOpen(open) {
                        item.classList.toggle('is-open', open);
                        handle.setAttribute('aria-expanded', String(open));
                    }
                    handle.addEventListener('click', function () {
                        if (!moved) setOpen(!item.classList.contains('is-open'));
                        moved = false;
                    });
                    item.addEventListener('touchstart', function (event) {
                        if (!mobile.matches || event.touches.length !== 1) return;
                        startX = event.touches[0].clientX;
                        startY = event.touches[0].clientY;
                        moved = false;
                    }, { passive: true });
                    item.addEventListener('touchend', function (event) {
                        if (!mobile.matches || startX === null || !event.changedTouches.length) return;
                        var dx = event.changedTouches[0].clientX - startX;
                        var dy = event.changedTouches[0].clientY - startY;
                        startX = null;
                        if (Math.abs(dx) > 35 && Math.abs(dx) > Math.abs(dy) * 1.3) {
                            moved = true;
                            setOpen(dx > 0);
                            // El gesto de arrastre no debe abrir el enlace ni plegar dos veces la pestaña.
                            window.setTimeout(function () { moved = false; }, 350);
                        }
                    }, { passive: true });
                    link.addEventListener('click', function (event) {
                        if (mobile.matches && (moved || !item.classList.contains('is-open'))) {
                            event.preventDefault();
                            if (!moved) setOpen(true);
                        }
                    });
                    window.addEventListener('resize', function () {
                        if (!mobile.matches) setOpen(false);
                    });
                });
            });
        </script>
    @endonce
@endif

{{-- Conmemoración de la operación Fallujah: visible solo el 27 de abril o por activación administrativa. --}}
@include('partials.fallujah-easter-egg')

{{-- Easter eggs de la comunidad; sin modificación de controles ni datos reales. --}}
@include('partials.sqa-easter-eggs')
