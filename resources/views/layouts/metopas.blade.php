<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta-description', 'Metopas y reconocimientos de Squad ALPHA.')">

    <title>@yield('title', 'Metopas') - Squad ALPHA</title>

    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/metopas.css') }}">
    @stack('styles')
</head>

<body class="landing-body metopas-body @yield('body-class')">
    @yield('page-background')

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
                    <a href="{{ route('home') }}">Inicio</a>
                    <a href="{{ route('home') }}#comunidad">Quiénes somos</a>
                    <a
                        href="{{ route('pages.show', 'normativa') }}"
                        @class(['is-active' => request()->routeIs('pages.show') && request()->route('page')?->slug === 'normativa'])
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
                        href="{{ route('metopas.index') }}"
                        @class(['is-active' => request()->routeIs('metopas.*')])
                    >
                        Metopas
                    </a>
                </nav>

                <div class="nav-actions">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-outline">
                            Iniciar sesión
                        </a>

                        <a href="{{ route('public.register') }}" class="btn btn-primary">
                            Crear cuenta
                        </a>
                    @else
                        @include(
                            'partials.notification-bell'
                        )

                        <a
                            href="{{ route('profile.show') }}"
                            class="btn btn-outline"
                        >
                            Mi perfil
                        </a>

                        @if(auth()->user()->hasRole('admin'))
                            <a href="{{ url('/admin') }}" class="btn btn-outline">
                                Administración
                            </a>
                        @endif

                        <form method="POST" action="{{ route('logout') }}" class="logout-form">
                            @csrf

                            <button type="submit" class="btn btn-primary">
                                Cerrar sesión
                            </button>
                        </form>
                    @endguest
                </div>
            </div>
        </div>
    </header>

    <main class="metopas-main">
        @yield('content')
    </main>

    @include('partials.public-footer', [
        'footerClass' => 'metopas-footer',
        'footerLinkUrl' => request()->routeIs('metopas.*')
            ? route('metopas.index')
            : route('home'),
        'footerLinkLabel' => request()->routeIs('metopas.*')
            ? 'Ver todas las metopas'
            : 'Volver al inicio',
    ])

    <script src="{{ asset('js/landing.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
