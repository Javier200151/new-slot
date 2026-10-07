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
    </div>
</section>
