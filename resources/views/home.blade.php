@php
    $authModal = request()->query('modal') ?? old('auth_form');
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="description"
        content="Squad ALPHA, comunidad de simulación militar centrada en el realismo, la disciplina y el trabajo en equipo."
    >

    <title>
        {{ config('app.name', 'New Slot') }} - Squad ALPHA
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('css/landing.css') }}"
    >
</head>

<body
    class="landing-body"
    data-open-auth-modal="{{ $authModal }}"
>
    <header class="landing-header">
        <div class="container nav-wrapper">

            <a href="#inicio" class="brand brand--image" aria-label="Squad ALPHA">
                <img
                    src="{{ asset('images/sqa-header-logo.png') }}"
                    alt="Squad Alpha"
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
                    <a href="#inicio">Inicio</a>
                    <a href="#comunidad">Quiénes somos</a>
                    <a href="#normativa">Normativa</a>
                    <a href="{{ route('metopas.index') }}">Metopas</a>
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
                        <a
                            href="{{ route('profile.show') }}"
                            class="btn btn-outline"
                        >
                            Mi perfil
                        </a>

                        @if(auth()->user()->hasRole('admin'))
                            <a
                                href="{{ url('/admin') }}"
                                class="btn btn-outline"
                            >
                                Administración
                            </a>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('logout') }}"
                            class="logout-form"
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

        </div>
    </header>

    <main>

        {{-- PORTADA --}}
        <section id="inicio" class="minimal-hero">

            <div class="hero-grid" aria-hidden="true"></div>

            <div class="container minimal-hero__content">

                <div class="hero-emblem" aria-hidden="true">

                    <div class="hero-emblem__logo">
                        <img
                            src="{{ asset('images/sqa-shield-white.png') }}"
                            alt="Escudo Squad Alpha"
                        >
                    </div>
                </div>

                <span class="hero-kicker">
                    Comunidad de simulación militar
                </span>

                <h1 class="hero-title hero-title--recruitment">
                    <span class="hero-title__line hero-title__line--primary">ALÍSTATE</span>
                    <span class="hero-title__line hero-title__line--secondary">A SQUAD ALPHA</span>
                </h1>

                <p class="hero-motto">
                    Realismo
                    <span></span>
                    Disciplina
                    <span></span>
                    Equipo
                </p>

                <p class="hero-description">
                    Da el paso y entra en una comunidad de simulación militar
                    centrada en el realismo, la disciplina y el trabajo en equipo.
                </p>

                <div class="hero-actions">
                    @guest
                        <a
                            href="{{ route('public.register') }}"
                            class="btn btn-primary btn-hero"
                            data-open-modal="register-modal"
                        >
                            Alístate
                            <span aria-hidden="true">→</span>
                        </a>
                    @else
                        <div class="member-status">
                            <span class="member-status__indicator"></span>

                            Sesión iniciada como

                            <strong>
                                {{ auth()->user()->nick }}
                            </strong>
                        </div>
                    @endguest
                </div>

                <a href="#comunidad" class="scroll-indicator">
                    <span>Conoce Squad ALPHA</span>
                    <span class="scroll-indicator__line"></span>
                </a>

            </div>
        </section>

        {{-- QUIÉNES SOMOS --}}
        <section id="comunidad" class="about-section">
            <div class="container">

                <header class="section-header">
                    <span class="section-index">01</span>

                    <div>
                        <span class="section-label">Quiénes somos</span>

                        <h2>
                            Mucho más que entrar a jugar.
                        </h2>
                    </div>
                </header>

                <div class="about-layout">

                    <div class="about-statement">
                        <p>
                            Somos una comunidad de simulación militar construida
                            alrededor del trabajo en equipo.
                        </p>
                    </div>

                    <div class="about-content">
                        <p>
                            Squad ALPHA reúne a personas que comparten una misma forma
                            de entender la simulación: organización, responsabilidad,
                            comunicación y respeto por el resto de miembros.
                        </p>

                        <p>
                            Cada integrante forma parte de una estructura común.
                            El objetivo no es competir individualmente, sino aprender,
                            mejorar y disfrutar de una experiencia coordinada junto al equipo.
                        </p>

                        <div class="principles">

                            <article class="principle">
                                <span class="principle__number">01</span>

                                <div>
                                    <h3>Realismo</h3>
                                    <p>
                                        Buscamos una experiencia inmersiva, organizada
                                        y coherente.
                                    </p>
                                </div>
                            </article>

                            <article class="principle">
                                <span class="principle__number">02</span>

                                <div>
                                    <h3>Disciplina</h3>
                                    <p>
                                        La preparación y el respeto por la estructura
                                        hacen posible el trabajo conjunto.
                                    </p>
                                </div>
                            </article>

                            <article class="principle">
                                <span class="principle__number">03</span>

                                <div>
                                    <h3>Compañerismo</h3>
                                    <p>
                                        Ningún miembro está por encima del equipo
                                        ni de la comunidad.
                                    </p>
                                </div>
                            </article>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        {{-- NORMATIVA --}}
        <section id="normativa" class="rules-section">
            <div class="container">

                <div class="rules-panel">

                    <div class="rules-introduction">
                        <span class="section-index">02</span>
                        <span class="section-label">Normativa</span>

                        <h2>
                            Una comunidad sólida necesita unas reglas claras.
                        </h2>

                        <p>
                            Nuestra normativa establece las bases de convivencia,
                            participación y comportamiento que todos los miembros
                            deben conocer y respetar.
                        </p>

                        <p class="rules-note">
                            El registro en la plataforma implica el compromiso de
                            conocer y aceptar estas normas.
                        </p>
                    </div>

                    <div class="rules-list">

                        <article class="rule">
                            <span>01</span>

                            <div>
                                <h3>Respeto y convivencia</h3>

                                <p>
                                    Se espera un trato correcto y respetuoso hacia
                                    cualquier miembro de la comunidad.
                                </p>
                            </div>
                        </article>

                        <article class="rule">
                            <span>02</span>

                            <div>
                                <h3>Compromiso</h3>

                                <p>
                                    La participación requiere responsabilidad,
                                    puntualidad y comunicación con el grupo.
                                </p>
                            </div>
                        </article>

                        <article class="rule">
                            <span>03</span>

                            <div>
                                <h3>Trabajo en equipo</h3>

                                <p>
                                    Las decisiones individuales nunca deben perjudicar
                                    al funcionamiento del conjunto.
                                </p>
                            </div>
                        </article>

                        <article class="rule">
                            <span>04</span>

                            <div>
                                <h3>Juego limpio</h3>

                                <p>
                                    No se toleran comportamientos que dañen la experiencia,
                                    la confianza o el ambiente de la comunidad.
                                </p>
                            </div>
                        </article>

                    </div>

                </div>
            </div>
        </section>

        {{-- ALISTAMIENTO --}}
        <section id="alistamiento" class="join-section">
            <div class="container join-content">

                <div class="join-emblem" aria-hidden="true">
                    SA
                </div>

                <span class="section-label">
                    Da el primer paso
                </span>

                <h2>
                    Tu lugar en Squad ALPHA comienza aquí.
                </h2>

                <p>
                    Crea tu cuenta y comienza el proceso para formar parte
                    de nuestra comunidad.
                </p>

                @guest
                    <a
                        href="{{ route('public.register') }}"
                        class="btn btn-primary btn-hero"
                        data-open-modal="register-modal"
                    >
                        Alístate
                        <span aria-hidden="true">→</span>
                    </a>
                @else
                    <div class="member-status">
                        <span class="member-status__indicator"></span>

                        Ya formas parte de la plataforma
                    </div>
                @endguest

            </div>
        </section>

    </main>

    <footer class="landing-footer">
        <div class="container footer-content">

            <div class="footer-brand">
                <span class="footer-brand__mark">SA</span>

                <span>
                    <strong>Squad ALPHA</strong>
                    <small>Comunidad de simulación militar</small>
                </span>
            </div>

            <p>
                Realismo · Disciplina · Equipo
            </p>

            <a href="#inicio">
                Volver arriba ↑
            </a>

        </div>
    </footer>

    @include('partials.auth-modals')

    <script
        src="{{ asset('js/landing.js') }}"
        defer
    ></script>

</body>
</html>
