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
        {{ config('app.name', 'New Slot') }}
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
    @if(session('birthday_celebration'))
        <div class="birthday-celebration" data-birthday-celebration>
            <div class="birthday-message">
                <strong>🎂 Feliz cumpleaños, {{ session('birthday_celebration')['nick'] ?? auth()->user()?->nick }}</strong>
                <span>Squad Alpha te desea un gran día.</span>
            </div>
        </div>
    @endif
    @include('partials.public-header')

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
                    Simulación
                    <span></span>
                    Disciplina
                    <span></span>
                    Equipo
                </p>

                <p class="hero-description">
                    Da el paso y entra en una comunidad de simulación militar
                    en Arma 3 y Arma REFORGER.
                </p>

                <div class="hero-actions">
                    @guest
                        <a
                            href="#alistamiento"
                            class="btn btn-primary btn-hero {{ $settings->recruitment_open ? 'is-recruitment-open' : 'is-recruitment-closed' }}"
                        >
                            <span class="btn-hero__status-dot" aria-hidden="true"></span>
                            <span>{{ $settings->recruitment_open ? 'Alístate' : 'Alistamiento cerrado' }}</span>
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

                <a href="#actualidad" class="scroll-indicator">
                    <span>Conoce Squad ALPHA</span>
                    <span class="scroll-indicator__line"></span>
                </a>

            </div>
        </section>

        {{-- ACTUALIDAD / NEWSLETTER --}}
        <section id="actualidad" class="home-news-section">
            <div class="container">
                <header class="home-section-heading">
                    <div>
                        <span class="section-index">01</span>
                        <span class="section-label">Actualidad</span>
                    </div>

                    <div class="home-section-heading__copy">
                        <h2>{{ $settings->news_title }}</h2>
                        @if($settings->news_intro)
                            <p>{{ $settings->news_intro }}</p>
                        @endif
                    </div>

                    @if($settings->instagram_url)
                        <a
                            class="social-link social-link--instagram"
                            href="{{ $settings->instagram_url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <span class="social-link__icon" aria-hidden="true">◎</span>
                            Instagram Squad ALPHA
                            <span aria-hidden="true">↗</span>
                        </a>
                    @endif
                </header>

                @if($news->isNotEmpty())
                    <div class="home-news-grid">
                        @foreach($news as $item)
                            <article class="home-news-card {{ $loop->first ? 'home-news-card--featured' : '' }}">
                                @if($item->image)
                                    <div class="home-news-card__media">
                                        <img src="{{ asset('storage/' . $item->image) }}" alt="{{ $item->title }}">
                                    </div>
                                @endif

                                <div class="home-news-card__body">
                                    <div class="home-news-card__meta">
                                        <span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                        @if($item->published_at)
                                            <time datetime="{{ $item->published_at->toDateString() }}">
                                                {{ $item->published_at->format('d · m · Y') }}
                                            </time>
                                        @endif
                                    </div>

                                    <h3>{{ $item->title }}</h3>

                                    @if($item->excerpt)
                                        <p>{{ $item->excerpt }}</p>
                                    @endif

                                    @if($item->body)
                                        <div class="home-news-card__content">
                                            {!! $item->body !!}
                                        </div>
                                    @endif

                                    @if($item->external_url)
                                        <a href="{{ $item->external_url }}" target="_blank" rel="noopener noreferrer" class="home-text-link">
                                            Ver publicación <span aria-hidden="true">↗</span>
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="home-empty-state">
                        <span>ALPHA / NEWS</span>
                        <p>La próxima novedad de la comunidad aparecerá aquí.</p>
                    </div>
                @endif

                <div class="instagram-feed">
                    <div class="instagram-feed__heading">
                        <div>
                            <span class="section-label">Instagram</span>
                            <h3>Último en @squadalpha_es</h3>
                        </div>

                        <a
                            class="home-text-link"
                            href="{{ $settings->instagram_url ?: 'https://www.instagram.com/squadalpha_es/' }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Ver perfil <span aria-hidden="true">↗</span>
                        </a>
                    </div>

                    @if($instagramPosts->isNotEmpty())
                        <div class="instagram-grid">
                            @foreach($instagramPosts as $post)
                                <a
                                    class="instagram-card"
                                    href="{{ $post['permalink'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Abrir publicación de Instagram de Squad ALPHA"
                                >
                                    <div class="instagram-card__media">
                                        @if($post['image'])
                                            <img
                                                src="{{ $post['image'] }}"
                                                alt="{{ $post['caption'] ? \Illuminate\Support\Str::limit($post['caption'], 90) : 'Publicación de @squadalpha_es' }}"
                                                loading="lazy"
                                            >
                                        @else
                                            <div class="instagram-card__placeholder">@squadalpha_es</div>
                                        @endif

                                        <div class="instagram-card__overlay">
                                            <span>◎</span>
                                            <strong>{{ $post['media_type'] === 'VIDEO' ? 'REEL / VÍDEO' : ($post['media_type'] === 'CAROUSEL_ALBUM' ? 'CARRUSEL' : 'PUBLICACIÓN') }}</strong>
                                        </div>
                                    </div>

                                    <div class="instagram-card__body">
                                        @if($post['timestamp'])
                                            <time datetime="{{ $post['timestamp']->toIso8601String() }}">
                                                {{ $post['timestamp']->format('d/m/Y') }}
                                            </time>
                                        @endif

                                        <p>
                                            {{ $post['caption']
                                                ? \Illuminate\Support\Str::limit($post['caption'], 135)
                                                : 'Ver publicación de Squad ALPHA en Instagram.' }}
                                        </p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="instagram-feed__fallback">
                            <span>◎</span>
                            <p>Sigue <strong>@squadalpha_es</strong> para ver las últimas publicaciones de la comunidad.</p>
                            <a
                                class="home-text-link"
                                href="{{ $settings->instagram_url ?: 'https://www.instagram.com/squadalpha_es/' }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Abrir Instagram ↗
                            </a>
                        </div>
                    @endif
                </div>

                <div class="google-photos-feed">
                    <div class="google-photos-feed__heading">
                        <div>
                            <span class="section-label">Google Fotos</span>
                            <h3>Últimas fotos de Squad ALPHA</h3>
                        </div>

                        <a
                            class="home-text-link"
                            href="{{ $googlePhotosAlbumUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Ver álbum completo <span aria-hidden="true">↗</span>
                        </a>
                    </div>

                    @if($googlePhotos->isNotEmpty())
                        <div class="google-photos-grid">
                            @foreach($googlePhotos as $photo)
                                <a
                                    class="google-photo-card"
                                    href="{{ $googlePhotosAlbumUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Abrir álbum FOTOS SQUAD ALPHA RRSS en Google Fotos"
                                >
                                    <img
                                        src="{{ $photo['image'] }}"
                                        alt="Foto reciente de Squad ALPHA"
                                        loading="lazy"
                                    >
                                    <span class="google-photo-card__overlay">
                                        <span aria-hidden="true">▣</span>
                                        <strong>VER EN GOOGLE FOTOS ↗</strong>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="google-photos-feed__fallback">
                            <span aria-hidden="true">▣</span>
                            <p>Las últimas fotos de Squad ALPHA están disponibles en nuestro álbum público.</p>
                            <a
                                class="home-text-link"
                                href="{{ $googlePhotosAlbumUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Abrir Google Fotos ↗
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- VODS DE STREAMERS --}}
        <section id="vods" class="home-vods-section">
            <div class="container">
                <header class="home-section-heading home-section-heading--compact">
                    <div>
                        <span class="section-index">02</span>
                        <span class="section-label">Comunidad en directo</span>
                    </div>

                    <div class="home-section-heading__copy">
                        <h2>{{ $settings->streams_title }}</h2>
                        @if($settings->streams_intro)
                            <p>{{ $settings->streams_intro }}</p>
                        @endif
                    </div>

                    <a class="home-text-link" href="{{ route('streams.index') }}">
                        Ver directos <span aria-hidden="true">→</span>
                    </a>
                </header>

                @if($vods->isNotEmpty())
                    <div class="vod-grid">
                        @foreach($vods as $vod)
                            <article class="vod-card">
                                <a href="{{ $vod['url'] }}" target="_blank" rel="noopener noreferrer" class="vod-card__media">
                                    @if($vod['thumbnail'])
                                        <img src="{{ $vod['thumbnail'] }}" alt="{{ $vod['title'] }}" loading="lazy">
                                    @else
                                        <div class="vod-card__placeholder">
                                            <span>{{ strtoupper($vod['platform'] ?: 'VOD') }}</span>
                                            <strong>PLAY</strong>
                                        </div>
                                    @endif
                                    <span class="vod-card__play" aria-hidden="true">▶</span>
                                </a>

                                <div class="vod-card__body">
                                    <div class="vod-card__meta">
                                        <span>{{ strtoupper($vod['platform'] ?: 'STREAM') }}</span>
                                        @if($vod['published_at'])
                                            <time>{{ $vod['published_at']->format('d/m/Y') }}</time>
                                        @endif
                                    </div>
                                    <h3>{{ $vod['title'] }}</h3>
                                    <p>{{ $vod['streamer'] }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="home-empty-state home-empty-state--vods">
                        <span>STREAM / ARCHIVE</span>
                        <p>Cuando nuestros streamers guarden una retransmisión, sus últimos VODs aparecerán aquí.</p>
                        <a class="home-text-link" href="{{ route('streams.index') }}">Ir a Directos →</a>
                    </div>
                @endif
            </div>
        </section>

        {{-- CONTACTO / ALISTAMIENTO --}}
        <section id="alistamiento" class="home-contact-section">
            <div class="container home-contact-layout">
                <div class="home-contact-intro">
                    <span class="section-index">03</span>
                    <span class="section-label">Contacto</span>

                    <h2>
                        {{ $settings->recruitment_open
                            ? '¿Quieres formar parte de Squad ALPHA?'
                            : '¿Quieres hablar con Squad ALPHA?' }}
                    </h2>

                    <p>
                        @if($settings->recruitment_open)
                            El alistamiento está abierto. Puedes enviarnos una consulta o marcar tu mensaje como solicitud de alistamiento y completar los requisitos.
                        @else
                            El alistamiento está cerrado actualmente, pero puedes enviarnos cualquier consulta desde este formulario.
                        @endif
                    </p>

                    <div class="recruitment-state {{ $settings->recruitment_open ? 'is-open' : 'is-closed' }}">
                        <span class="recruitment-state__dot"></span>
                        <div>
                            <strong>Alistamiento {{ $settings->recruitment_open ? 'abierto' : 'cerrado' }}</strong>
                            <small>{{ $settings->recruitment_open ? 'Aceptando nuevas solicitudes' : 'Solo consultas generales' }}</small>
                        </div>
                    </div>

                    <a href="{{ route('pages.show', 'faqs') }}" class="home-faq-cta">
                        <span class="home-faq-cta__icon" aria-hidden="true">?</span>
                        <span class="home-faq-cta__copy">
                            <small>Antes de escribirnos</small>
                            <strong>Preguntas frecuentes</strong>
                            <span>Consulta las FAQs de Squad ALPHA</span>
                        </span>
                        <span class="home-faq-cta__arrow" aria-hidden="true">→</span>
                    </a>
                </div>

                <div class="home-contact-panel">
                    @if(session('contact_status'))
                        <div class="contact-alert contact-alert--success">{{ session('contact_status') }}</div>
                    @endif

                    @if($errors->any() && old('contact_form'))
                        <div class="contact-alert contact-alert--error">
                            <strong>Revisa el formulario.</strong>
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('public.contact.store') }}" method="POST" class="recruitment-form" data-recruitment-form>
                        @csrf
                        <input type="hidden" name="contact_form" value="1">
                        <div class="contact-honeypot" aria-hidden="true">
                            <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                        </div>

                        <div class="form-grid">
                            <label class="form-field">
                                <span>Nick</span>
                                <input
                                    type="text"
                                    name="nickname"
                                    value="{{ old('nickname', auth()->user()?->nick) }}"
                                    maxlength="80"
                                    required
                                    autocomplete="nickname"
                                    placeholder="Cómo te conocemos en la comunidad"
                                >
                            </label>

                            <label class="form-field">
                                <span>Email *</span>
                                <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" required autocomplete="email">
                            </label>

                            <label class="form-field form-field--full">
                                <span>Mensaje</span>
                                <textarea name="message" rows="6" required>{{ old('message') }}</textarea>
                            </label>
                        </div>

                        @if($settings->recruitment_open)
                            <div class="recruitment-toggle-block">
                                <label class="form-check form-check--primary">
                                    <input type="checkbox" name="is_recruitment" value="1" @checked(old('is_recruitment')) data-recruitment-toggle>
                                    <span>
                                        <strong>Marcar en caso de ser una solicitud de alistamiento</strong>
                                        <small>Al marcarlo se activan los requisitos de ingreso.</small>
                                    </span>
                                </label>
                            </div>

                            <div class="recruitment-fields" data-recruitment-fields @if(!old('is_recruitment')) hidden @endif>
                                <div class="requirement-block">
                                    <h3>Datos personales</h3>

                                    <div class="form-grid">
                                        <label class="form-field form-field--full">
                                            <span>Nombre y apellidos (reales) *</span>
                                            <input
                                                type="text"
                                                name="full_name"
                                                value="{{ old('full_name') }}"
                                                maxlength="160"
                                                autocomplete="name"
                                                data-recruitment-required
                                                placeholder="Nombre y apellidos"
                                            >
                                        </label>

                                        <label class="form-field">
                                            <span>Fecha de nacimiento *</span>
                                            <input
                                                type="date"
                                                name="birth_date"
                                                value="{{ old('birth_date') }}"
                                                autocomplete="bday"
                                                data-recruitment-required
                                            >
                                        </label>

                                        <label class="form-field">
                                            <span>Lugar de residencia</span>
                                            <input
                                                type="text"
                                                name="residence"
                                                value="{{ old('residence') }}"
                                                maxlength="160"
                                                autocomplete="address-level2"
                                                data-recruitment-required
                                                placeholder="Ciudad / Provincia"
                                            >
                                        </label>

                                        <label class="form-field form-field--full">
                                            <span>Teléfono (contacto WhatsApp) *</span>
                                            <input
                                                type="tel"
                                                name="phone_whatsapp"
                                                value="{{ old('phone_whatsapp') }}"
                                                maxlength="40"
                                                autocomplete="tel"
                                                inputmode="tel"
                                                data-recruitment-required
                                                placeholder="+34 ..."
                                            >
                                        </label>
                                    </div>
                                </div>

                                <div class="requirement-block">
                                    <h3>Cómo nos conociste</h3>
                                    <label class="form-field">
                                        <span>Cuéntanos brevemente cómo llegaste a Squad ALPHA</span>
                                        <textarea
                                            name="how_heard_us"
                                            rows="4"
                                            maxlength="1500"
                                            data-recruitment-required
                                            placeholder="YouTube, Twitch, un amigo, redes sociales, buscador..."
                                        >{{ old('how_heard_us') }}</textarea>
                                    </label>
                                </div>

                                <div class="requirement-block">
                                    <h3>Normativa</h3>
                                    <label class="form-check">
                                        <input type="checkbox" name="accepted_rules" value="1" @checked(old('accepted_rules')) data-recruitment-required>
                                        <span>Confirmo haber leído y me comprometo a cumplir la <a href="{{ url('/normativa') }}" target="_blank">Normativa del Squad ALPHA</a>.</span>
                                    </label>
                                </div>

                                <div class="requirement-block">
                                    <h3>Mayoría de edad</h3>
                                    <label class="form-check">
                                        <input type="checkbox" name="is_adult" value="1" @checked(old('is_adult')) data-recruitment-required>
                                        <span>Certifico que soy mayor de edad.</span>
                                    </label>
                                </div>

                                <div class="requirement-block">
                                    <h3>Aportaciones económicas</h3>
                                    <label class="form-check">
                                        <input type="checkbox" name="accepts_contributions" value="1" @checked(old('accepts_contributions')) data-recruitment-required>
                                        <span>Acepto que tendré que realizar aportaciones económicas.</span>
                                    </label>
                                </div>

                                <div class="requirement-block">
                                    <h3>DLCs y ARMA 3 Original</h3>
                                    <label class="form-check">
                                        <input type="checkbox" name="has_required_game_content" value="1" @checked(old('has_required_game_content')) data-recruitment-required>
                                        <span>Confirmo tener Arma 3 original, así como sus DLC "APEX" y sus CDLCs "S.O.G. Prairie Fire" y "Spearhead 1944", o estar dispuesto/a a comprarlos en caso de ser reclutado/a por Squad ALPHA.</span>
                                    </label>
                                </div>

                                <div class="requirement-block">
                                    <h3>Disponibilidad martes</h3>
                                    <input type="hidden" name="tuesday_available" value="0">
                                    <label class="form-check">
                                        <input type="checkbox" name="tuesday_available" value="1" @checked((string) old('tuesday_available') === '1')>
                                        <span>Tengo disponibilidad para participar los martes (de 20:00 a 22:30h). Si no puedes normalmente, deja esta casilla sin marcar.</span>
                                    </label>
                                </div>

                                <div class="requirement-block">
                                    <h3>Disponibilidad viernes</h3>
                                    <input type="hidden" name="friday_available" value="0">
                                    <label class="form-check">
                                        <input type="checkbox" name="friday_available" value="1" @checked((string) old('friday_available') === '1')>
                                        <span>Tengo disponibilidad para participar los viernes (a partir de las 22:30h). Si no puedes normalmente, deja esta casilla sin marcar.</span>
                                    </label>
                                </div>

                                <div class="requirement-block">
                                    <h3>Experiencia previa</h3>
                                    <input type="hidden" name="has_previous_experience" value="0">
                                    <label class="form-check">
                                        <input type="checkbox" name="has_previous_experience" value="1" @checked((string) old('has_previous_experience') === '1')>
                                        <span>Tengo experiencia previa en simulación militar con Arma 3.</span>
                                    </label>

                                    <label class="form-field" style="margin-top: 14px;">
                                        <span>Resumen de experiencia en simulación militar en Arma 3</span>
                                        <textarea
                                            name="experience_summary"
                                            rows="6"
                                            maxlength="4000"
                                            data-recruitment-required
                                            placeholder="Cuéntanos comunidades anteriores, tiempo jugando, roles habituales, experiencia con ACE/TFAR/ACRE, etc. Si no tienes experiencia previa, indícalo."
                                        >{{ old('experience_summary') }}</textarea>
                                    </label>
                                </div>
                            </div>
                        @endif

                        <div class="privacy-block">
                            <h3>Política de privacidad</h3>
                            <label class="form-check">
                                <input type="checkbox" name="accepted_privacy" value="1" required @checked(old('accepted_privacy'))>
                                <span>He leído y acepto la <a href="{{ url('/politica-de-privacidad') }}" target="_blank">política de privacidad</a>.</span>
                            </label>

                            <h3>Consentimiento de contacto</h3>
                            <label class="form-check">
                                <input type="checkbox" name="accepted_contact" value="1" required @checked(old('accepted_contact'))>
                                <span>Acepto que mis datos podrán ser usados para informarme o contactarme, siendo la base legal mi consentimiento.</span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary contact-submit">
                            Enviar <span aria-hidden="true">→</span>
                        </button>
                    </form>
                </div>
            </div>
        </section>

    </main>

    @include('partials.public-footer', [
        'footerLinkUrl' => '#inicio',
        'footerLinkLabel' => 'Volver arriba ↑',
    ])

    @include('partials.auth-modals')

    <script
        src="{{ asset('js/landing.js') }}"
        defer
    ></script>

</body>
</html>
