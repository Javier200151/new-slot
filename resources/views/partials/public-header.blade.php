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
