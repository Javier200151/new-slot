@php
    $linkedAccountSettings = $linkedAccountSettings ?? \App\Models\HomepageSetting::current();

    $discordId = trim((string) $user->discord_id);
    $discordConfigured = preg_match('/^\d{17,20}$/', $discordId) === 1;
    $discordVerified = $discordConfigured && $user->discord_linked_at !== null;

    $steamId = trim((string) $user->steam_id);
    $steamConfigured = preg_match('/^\d{17}$/', $steamId) === 1;
    $steamVerified = $steamConfigured && $user->steam_linked_at !== null;

    $discordLogo = filled($linkedAccountSettings?->discord_account_logo)
        ? asset('storage/' . $linkedAccountSettings->discord_account_logo)
        : null;
    $steamLogo = filled($linkedAccountSettings?->steam_account_logo)
        ? asset('storage/' . $linkedAccountSettings->steam_account_logo)
        : null;
@endphp

<section class="profile-card linked-accounts" id="profile-linked-accounts">
    <header class="profile-card__header linked-accounts__header">
        <span>Cuentas vinculadas</span>
        <h2>Servicios conectados</h2>
        <p>Vincula tus cuentas para sincronizar tus grupos de trabajo y la información de Squad ALPHA entre Arma 3, Arma Reforger y Discord, y para registrar tus estadísticas de juego.</p>
    </header>

    <div class="linked-accounts__list">
        <article class="linked-account">
            <div class="linked-account__identity">
                <div class="linked-account__icon" aria-hidden="true">
                    @if($discordLogo)
                        <img src="{{ $discordLogo }}" alt="">
                    @else
                        <span>D</span>
                    @endif
                </div>
                <div>
                    <h3>Discord</h3>

                    @if($discordVerified)
                        <p class="linked-account__state linked-account__state--ok">
                            ✓ Vinculado{{ filled($user->discord_username) ? ' como @' . $user->discord_username : '' }}
                        </p>
                    @elseif($discordConfigured)
                        <p class="linked-account__state linked-account__state--manual">
                            Cuenta configurada
                        </p>
                    @else
                        <p class="linked-account__state">No vinculado</p>
                    @endif
                </div>
            </div>

            <div class="linked-account__actions">
                @if(! $discordVerified)
                    <a href="{{ route('profile.accounts.discord.redirect') }}" class="btn btn-outline">
                        {{ $discordConfigured ? 'Verificar con Discord' : 'Enlazar con Discord' }}
                    </a>
                @endif

                @if($discordConfigured)
                    <form method="POST" action="{{ route('profile.accounts.discord.unlink') }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="linked-account__unlink"
                            onclick="return confirm('¿Desvincular Discord? Las automatizaciones de Discord dejarán de funcionar hasta volver a vincular una cuenta. No se quitarán roles ni se ejecutará ninguna baja.')"
                        >
                            Desvincular
                        </button>
                    </form>
                @endif
            </div>
        </article>

        <article class="linked-account">
            <div class="linked-account__identity">
                <div class="linked-account__icon" aria-hidden="true">
                    @if($steamLogo)
                        <img src="{{ $steamLogo }}" alt="">
                    @else
                        <span>S</span>
                    @endif
                </div>
                <div>
                    <h3>Steam</h3>

                    @if($steamVerified)
                        <p class="linked-account__state linked-account__state--ok">✓ Cuenta vinculada</p>
                        @if(filled($user->steam_profile_url))
                            <small><a href="{{ $user->steam_profile_url }}" target="_blank" rel="noopener noreferrer">Abrir perfil de Steam</a></small>
                        @endif
                    @elseif($steamConfigured)
                        <p class="linked-account__state linked-account__state--manual">
                            Cuenta configurada
                        </p>
                    @else
                        <p class="linked-account__state">No vinculado</p>
                    @endif
                </div>
            </div>

            <div class="linked-account__actions">
                @if(! $steamVerified)
                    <a href="{{ route('profile.accounts.steam.redirect') }}" class="btn btn-outline">
                        {{ $steamConfigured ? 'Verificar con Steam' : 'Enlazar con Steam' }}
                    </a>
                @endif

                @if($steamConfigured)
                    <form method="POST" action="{{ route('profile.accounts.steam.unlink') }}">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="linked-account__unlink"
                            onclick="return confirm('¿Desvincular Steam? Algunas funciones vinculadas a Steam dejarán de estar disponibles hasta que vuelvas a enlazar la cuenta.')"
                        >
                            Desvincular
                        </button>
                    </form>
                @endif
            </div>
        </article>

        {{-- BookStack SSO is active. This page cannot infer whether an existing
             BookStack account has external_auth_id: never claim linked status. --}}
        @php
            $wikiAuthorized = app(\App\Services\Wiki\WikiAccessPolicy::class)->canAuthenticate($user);
        @endphp
        <article class="linked-account">
            <div class="linked-account__identity">
                <div class="linked-account__icon" aria-hidden="true"><span>W</span></div>
                <div>
                    <h3>Wiki Squad ALPHA</h3>
                    @if($wikiAuthorized)
                        <p class="linked-account__state linked-account__state--ok">Inicio de sesión con Squad ALPHA disponible</p>
                        <small>Accede sin otra contraseña. Si ya tenías una cuenta antigua en la wiki y aún no la has vinculado, contacta con administración antes del primer acceso para conservar tu historial y permisos.</small>
                    @else
                        <p class="linked-account__state linked-account__state--manual">Acceso privado no autorizado</p>
                        <small>Para iniciar sesión en la wiki necesitas tener el correo verificado y estado RECLUTA, ACTIVO o RESERVA, salvo la cuenta administrativa protegida. La documentación pública sigue disponible.</small>
                    @endif
                </div>
            </div>
            @if($wikiAuthorized && ! app(\App\Services\Wiki\WikiAccessPolicy::class)->isSuperAdmin($user) && config('wiki_link.enabled'))
                <a class="btn btn-outline" href="{{ route('profile.accounts.wiki.status') }}">Comprobar vinculación de Wiki</a>
                @error('wiki')<p role="alert">{{ $message }}</p>@enderror
                @if(session('wiki_link_notice'))<p role="status">{{ session('wiki_link_notice') }}</p>@endif
                <form method="POST" action="{{ route('profile.accounts.wiki.link') }}" autocomplete="off" style="margin-top:1rem">
                    @csrf
                    <p><strong>¿Ya tenías cuenta en la Wiki?</strong> Verifica las contraseñas de ambas cuentas para conservar tus libros, comentarios y permisos.</p>
                    <label>Contraseña de Squad ALPHA <input type="password" name="sqa_password" required autocomplete="current-password"></label>
                    <label>Contraseña antigua de BookStack <input type="password" name="bookstack_password" required autocomplete="off"></label>
                    <button type="submit" class="btn btn-outline">Vincular Wiki</button>
                </form>
            @endif
            <div class="linked-account__actions">
                <a href="https://wiki.squadalpha.es" target="_blank" rel="noopener noreferrer" class="btn btn-outline">
                    {{ $wikiAuthorized ? 'Acceder a la Wiki ↗' : 'Ver Wiki pública ↗' }}
                </a>
            </div>
        </article>
    </div>
</section>
