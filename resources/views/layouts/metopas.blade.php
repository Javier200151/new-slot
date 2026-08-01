<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta-description', 'Metopas y reconocimientos de Squad ALPHA.')">

    <title>@yield('title', 'Metopas') - Squad ALPHA</title>

    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/metopas.css') }}">
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

            <nav class="landing-nav" aria-label="Navegación principal">
                <a href="{{ route('home') }}">Inicio</a>
                <a href="{{ route('home') }}#comunidad">Quiénes somos</a>
                <a href="{{ route('home') }}#normativa">Normativa</a>
                <a href="{{ route('metopas.index') }}" class="is-active">Metopas</a>
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
                    <a href="{{ route('profile.show') }}" class="btn btn-outline">
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
    </header>

    <main class="metopas-main">
        @yield('content')
    </main>

    <footer class="landing-footer metopas-footer">
        <div class="container footer-content">
            <div class="footer-brand">
                <span class="footer-brand__mark">SA</span>

                <span>
                    <strong>Squad ALPHA</strong>
                    <small>Comunidad de simulación militar</small>
                </span>
            </div>

            <p>Realismo · Disciplina · Equipo</p>

            <a href="{{ route('metopas.index') }}">
                Ver todas las metopas
            </a>
        </div>
    </footer>
</body>
</html>
