@php
    $authModal = request()->query('modal') ?? old('auth_form');
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'New Slot') }} - Squad ALPHA</title>
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body
    class="landing-body"
    data-open-auth-modal="{{ $authModal }}"
>
    <header class="landing-header">
        <div class="container nav-wrapper">
            <div class="brand">
                <div class="brand-mark">SA</div>
                <div class="brand-text">
                    <span class="brand-title">Squad <strong>ALPHA</strong></span>
                    <span class="brand-subtitle">Comunidad de simulación militar</span>
                </div>
            </div>

            <nav class="landing-nav">
                <a href="#inicio">Inicio</a>
                <a href="#comunidad">Comunidad</a>
                <a href="#ventajas">Ventajas</a>
                <a href="#unete">Únete</a>
            </nav>

            <div class="nav-actions">
                @guest
                    <a
                        href="{{ route('login') }}"
                        class="btn btn-outline"
                        data-open-modal="login-modal"
                    >
                        Iniciar sesión
                    </a>

                    <a
                        href="{{ route('public.register') }}"
                        class="btn btn-primary"
                        data-open-modal="register-modal"
                    >
                        Crear cuenta
                    </a>
                @else
                    @if(auth()->user()->hasRole('admin'))
                        <a
                            href="{{ url('/admin') }}"
                            class="btn btn-outline"
                        >
                            Panel de administración
                        </a>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Cerrar sesión
                        </button>
                    </form>
                @endguest
            </div>
        </div>
    </header>

    <main>
        <section id="inicio" class="hero-section">
            <div class="hero-overlay"></div>

            <div class="container hero-content">
                <div class="hero-text">
                    <span class="eyebrow">Squad ALPHA · Simulación militar</span>
                    <h1>
                        Vive operaciones organizadas,
                        tácticas y realistas en una
                        comunidad seria.
                    </h1>
                    <p>
                        Únete a una comunidad de simulación militar centrada en el trabajo en equipo,
                        la organización y la experiencia inmersiva dentro de <strong>Arma</strong>.
                    </p>

                    <div class="hero-actions">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
                                Alístate
                            </a>
                        @endif

                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="btn btn-outline btn-lg">
                                Acceder
                            </a>
                        @endif
                    </div>

                    <div class="hero-stats">
                        <div class="stat-card">
                            <strong>Operaciones</strong>
                            <span>Sesiones organizadas y estructuradas</span>
                        </div>
                        <div class="stat-card">
                            <strong>Comunidad</strong>
                            <span>Miembros con roles, grupos y progresión</span>
                        </div>
                        <div class="stat-card">
                            <strong>Disciplina</strong>
                            <span>Coordinación, táctica y trabajo en equipo</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="comunidad" class="section dark-section">
            <div class="container section-grid">
                <div>
                    <span class="section-tag">Quiénes somos</span>
                    <h2>Una comunidad preparada para operar en equipo</h2>
                    <p class="section-text">
                        Squad ALPHA es una comunidad orientada a la simulación militar,
                        donde la organización, el compañerismo y la experiencia táctica
                        son la base de cada operación.
                    </p>
                    <p class="section-text">
                        Aquí no solo entras a jugar: entras a formar parte de una estructura,
                        con eventos, grupos, metopas, operaciones y una progresión clara.
                    </p>
                </div>

                <div class="info-panel">
                    <div class="info-box">
                        <span class="info-number">01</span>
                        <h3>Realismo organizado</h3>
                        <p>Operaciones estructuradas, roles definidos y experiencia inmersiva.</p>
                    </div>
                    <div class="info-box">
                        <span class="info-number">02</span>
                        <h3>Comunidad activa</h3>
                        <p>Participa en eventos, mejora tu perfil y colabora con tu escuadra.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="ventajas" class="section">
            <div class="container">
                <div class="section-heading">
                    <span class="section-tag">Qué ofrece la plataforma</span>
                    <h2>Un espacio moderno para la gestión de la comunidad</h2>
                    <p>
                        Este portal será el punto de acceso para centralizar miembros,
                        operaciones, streams, metopas, promos y más.
                    </p>
                </div>

                <div class="feature-grid">
                    <article class="feature-card">
                        <div class="feature-icon">🎖️</div>
                        <h3>Gestión de miembros</h3>
                        <p>Perfiles organizados, estados, promos, roles y seguimiento de actividad.</p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">🪖</div>
                        <h3>Operaciones</h3>
                        <p>Planificación y control de eventos, campañas, días de operación y estados.</p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">🏅</div>
                        <h3>Metopas y progresión</h3>
                        <p>Visualiza logros, asignaciones y reconocimiento dentro de la comunidad.</p>
                    </article>

                    <article class="feature-card">
                        <div class="feature-icon">📡</div>
                        <h3>Streams y comunidad</h3>
                        <p>Espacio para streamers, visibilidad del contenido y presencia pública.</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="unete" class="section cta-section">
            <div class="container cta-box">
                <div>
                    <span class="section-tag">Da el siguiente paso</span>
                    <h2>Prepárate para formar parte de Squad ALPHA</h2>
                    <p>
                        Crea tu cuenta para acceder a la plataforma y empezar tu recorrido dentro de la comunidad.
                    </p>
                </div>

                <div class="cta-actions">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Crear cuenta</a>
                    @endif

                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="btn btn-outline btn-lg">Iniciar sesión</a>
                    @endif
                </div>
            </div>
        </section>
    </main>
@include('partials.auth-modals')

<script
    src="{{ asset('js/landing.js') }}"
    defer
></script>

</body>
</html>