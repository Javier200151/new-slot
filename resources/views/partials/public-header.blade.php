@php
    $navUser = auth()->user();
    $areaLabel = $navUser
        ? \App\Support\CommunityArea::label($navUser)
        : null;

    $communityActive = request()->routeIs('operations.*')
        || request()->routeIs('metopas.*')
        || request()->routeIs('community.organization')
        || request()->routeIs('campaigns.*');

    $areaActive = request()->routeIs('community.diary.*')
        || request()->routeIs('community.forum.*')
        || request()->routeIs('community.polls.*');
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

        <div id="public-navigation" class="nav-menu" data-nav-menu>
            <nav class="landing-nav" aria-label="Navegación principal">
                <a
                    href="{{ route('pages.show', 'normativa') }}"
                    @class([
                        'is-active' => request()->routeIs('pages.show')
                            && request()->route('page')?->slug === 'normativa',
                    ])
                >
                    Normativa
                </a>

                <a
                    href="{{ route('events.index') }}"
                    @class(['is-active' => request()->routeIs('events.*')])
                >
                    Eventos
                </a>

                <a
                    href="{{ route('streams.index') }}"
                    @class(['is-active' => request()->routeIs('streams.*')])
                >
                    Directos
                </a>

                <details @class(['nav-dropdown', 'is-active' => $communityActive])>
                    <summary>Comunidad</summary>
                    <div class="nav-dropdown__menu">
                        <a href="{{ route('activities.index') }}">Actividades</a>
                        <a href="{{ route('metopas.index') }}">Metopas</a>
                        <a href="{{ route('campaigns.index') }}">Campañas</a>
                        <a href="{{ route('community.organization') }}">Organigrama</a>
                        <a
                            href="https://wiki.squadalpha.es/"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Wiki ↗
                        </a>
                    </div>
                </details>

                @if($navUser && $areaLabel)
                    <details @class(['nav-dropdown', 'nav-dropdown--area', 'is-active' => $areaActive])>
                        <summary>{{ $areaLabel }}</summary>
                        <div class="nav-dropdown__menu">
                            <a href="{{ route('community.forum.home') }}">Foro</a>
                        </div>
                    </details>
                @endif
            </nav>

            <div class="nav-actions">
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
