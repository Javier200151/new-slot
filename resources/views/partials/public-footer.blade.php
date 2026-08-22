<footer @class(['landing-footer', $footerClass ?? null])>
    <div class="container footer-content">
        <div class="footer-brand">
            <span>
                <strong>Squad ALPHA</strong>
                <small>Grupo de Simulación Táctica en Arma 3 y Arma Reforger</small>
            </span>
        </div>

        <nav class="footer-social" aria-label="Redes sociales">
            {{-- Sustituir estas URL por las direcciones oficiales de Squad ALPHA. --}}
            <a href="https://x.com/SquadALPHA_ES" target="_blank" rel="noopener noreferrer" aria-label="Squad ALPHA en X">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.21-6.82-5.97 6.82H1.67l7.73-8.84L1.25 2.25h6.83l4.71 6.23 5.45-6.23Zm-1.16 17.52h1.83L7.08 4.13H5.12l11.96 15.64Z"/>
                </svg>
                <span class="sr-only">X</span>
            </a>

            <a href="https://www.instagram.com/squadalpha_es/" target="_blank" rel="noopener noreferrer" aria-label="Squad ALPHA en Instagram">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="5"/>
                    <circle cx="12" cy="12" r="4.25"/>
                    <circle cx="17.4" cy="6.7" r="1" class="footer-social__fill"/>
                </svg>
                <span class="sr-only">Instagram</span>
            </a>

            <a href="https://www.youtube.com/c/SquadALPHA" target="_blank" rel="noopener noreferrer" aria-label="Squad ALPHA en YouTube">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path fill-rule="evenodd" d="M21.58 7.19a2.96 2.96 0 0 0-2.08-2.1C17.66 4.6 12 4.6 12 4.6s-5.66 0-7.5.49a2.96 2.96 0 0 0-2.08 2.1A30.8 30.8 0 0 0 1.92 12c0 1.61.16 3.22.5 4.81a2.96 2.96 0 0 0 2.08 2.1c1.84.49 7.5.49 7.5.49s5.66 0 7.5-.49a2.96 2.96 0 0 0 2.08-2.1c.34-1.59.5-3.2.5-4.81s-.16-3.22-.5-4.81ZM10 15.2V8.8l5.2 3.2-5.2 3.2Z"/>
                </svg>
                <span class="sr-only">YouTube</span>
            </a>

            <a href="https://discord.com/login?redirect_to=%2Fchannels%2F438069558595813417%2F476062464891551744" target="_blank" rel="noopener noreferrer" aria-label="Servidor de Discord de Squad ALPHA">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18.9 5.34A16.6 16.6 0 0 0 15.08 4l-.47.96a14.75 14.75 0 0 0-5.22 0L8.92 4A16.6 16.6 0 0 0 5.1 5.34C2.68 8.9 2.02 12.37 2.35 15.8a15.45 15.45 0 0 0 4.68 2.36l1.14-1.56a10.1 10.1 0 0 1-1.79-.86l.44-.34a11.92 11.92 0 0 0 10.36 0l.44.34c-.57.34-1.17.63-1.79.86l1.14 1.56a15.45 15.45 0 0 0 4.68-2.36c.4-3.98-.69-7.42-2.75-10.46ZM8.68 14.1c-1.02 0-1.85-.94-1.85-2.1s.81-2.1 1.85-2.1 1.87.95 1.85 2.1c0 1.16-.81 2.1-1.85 2.1Zm6.64 0c-1.02 0-1.85-.94-1.85-2.1s.81-2.1 1.85-2.1 1.87.95 1.85 2.1c0 1.16-.81 2.1-1.85 2.1Z"/>
                </svg>
                <span class="sr-only">Discord</span>
            </a>
        </nav>
        <a
            href="{{ route('users.index') }}"
            @class([
                'footer-users-link',
                'is-active' => request()->routeIs(
                    'users.*'
                ),
            ])
        >
            <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                />
                <circle
                    cx="9"
                    cy="7"
                    r="4"
                />
                <path
                    d="M22 21v-2a4 4 0 0 0-3-3.87"
                />
                <path
                    d="M16 3.13a4 4 0 0 1 0 7.75"
                />
            </svg>

            Usuarios
        </a>
        <a href="{{ $footerLinkUrl }}">
            {{ $footerLinkLabel }}
        </a>
    </div>
</footer>
