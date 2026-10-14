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

        {{-- Estado OIDC comprobado por la política de acceso. La vinculación histórica se consulta al servicio privado. --}}
        @php
            $wikiPolicy = app(\App\Services\Wiki\WikiAccessPolicy::class);
            $wikiAuthorized = $wikiPolicy->canAuthenticate($user);
            $wikiCanLink = $wikiAuthorized && ! $wikiPolicy->isSuperAdmin($user) && config('wiki_link.enabled');
        @endphp
        <article class="linked-account sqa-wiki-card" aria-labelledby="sqa-wiki-card-title">
            <div class="sqa-wiki-card__main">
                <div class="sqa-wiki-card__heading">
                    <div class="sqa-wiki-card__emblem" aria-hidden="true">W</div>
                    <div class="sqa-wiki-card__heading-copy">
                        <h3 id="sqa-wiki-card-title">Wiki Squad ALPHA</h3>
                        @if($wikiAuthorized)
                            <p class="sqa-wiki-card__status sqa-wiki-card__status--ok"><span aria-hidden="true">●</span> Acceso con Squad ALPHA disponible</p>
                        @else
                            <p class="sqa-wiki-card__status sqa-wiki-card__status--restricted"><span aria-hidden="true">●</span> Acceso privado no disponible</p>
                        @endif
                    </div>
                </div>
                @if($wikiAuthorized)
                    <p class="sqa-wiki-card__description">Accede a la Wiki con tu cuenta de Squad ALPHA, sin recordar otra contraseña.</p>
                    <p class="sqa-wiki-card__hint">Si ya tenías una cuenta anterior, vincúlala antes de entrar por primera vez para conservar tu historial, comentarios y permisos.</p>
                @else
                    <p class="sqa-wiki-card__description">Puedes consultar la documentación pública. El acceso privado requiere correo verificado y un estado autorizado.</p>
                @endif
                <div class="sqa-wiki-card__actions">
                    <a href="https://wiki.squadalpha.es" target="_blank" rel="noopener noreferrer" class="btn sqa-wiki-card__primary">
                        {{ $wikiAuthorized ? 'Acceder a la Wiki ↗' : 'Ver Wiki pública ↗' }}
                    </a>
                    @if($wikiCanLink)
                        <a class="btn btn-outline sqa-wiki-card__secondary" href="{{ route('profile.accounts.wiki.status') }}">Comprobar mi cuenta</a>
                    @endif
                </div>
            </div>
            @if($wikiCanLink)
                <div class="sqa-wiki-card__divider" aria-hidden="true"></div>
                <div class="sqa-wiki-card__link">
                    <div class="sqa-wiki-card__link-heading">
                        <span class="sqa-wiki-card__link-symbol" aria-hidden="true">↗</span>
                        <div>
                            <h4>¿Ya tenías cuenta en la Wiki?</h4>
                            <p>Vincula tu cuenta anterior para conservar tus libros, comentarios y permisos.</p>
                        </div>
                    </div>
                    @error('wiki')
                        <div class="sqa-wiki-card__alert sqa-wiki-card__alert--error" role="alert">{{ $message }}</div>
                    @enderror
                    @if(session('wiki_link_notice'))
                        <div class="sqa-wiki-card__alert sqa-wiki-card__alert--success" role="status">{{ session('wiki_link_notice') }}</div>
                    @endif
                    <form method="POST" action="{{ route('profile.accounts.wiki.link') }}" autocomplete="off" class="sqa-wiki-card__form">
                        @csrf
                        <div class="sqa-wiki-card__fields">
                            <label class="sqa-wiki-card__field" for="sqa-wiki-sqa-password">
                                <span>Contraseña de Squad ALPHA</span>
                                <input id="sqa-wiki-sqa-password" type="password" name="sqa_password" required autocomplete="current-password" minlength="1" placeholder="Tu contraseña actual">
                            </label>
                            <label class="sqa-wiki-card__field" for="sqa-wiki-old-password">
                                <span>Contraseña anterior de la Wiki</span>
                                <input id="sqa-wiki-old-password" type="password" name="bookstack_password" required autocomplete="off" minlength="1" placeholder="Contraseña de tu antigua cuenta">
                            </label>
                        </div>
                        <button type="submit" class="btn sqa-wiki-card__submit">Vincular cuenta de la Wiki</button>
                    </form>
                </div>
            @endif
        </article>
    </div>
</section>

<style>
/* Wiki: estilos encapsulados para no alterar las tarjetas de Discord y Steam. */
.linked-accounts .sqa-wiki-card{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:0;align-items:stretch;border:1px solid rgba(225,177,69,.22);border-radius:16px;background:linear-gradient(140deg,rgba(233,160,34,.075),rgba(18,23,32,.35) 45%,rgba(18,23,32,.55));overflow:hidden;padding:0;color:inherit}
.linked-accounts .sqa-wiki-card__main,.linked-accounts .sqa-wiki-card__link{min-width:0;padding:24px}
.linked-accounts .sqa-wiki-card__main{display:flex;flex-direction:column;gap:14px}
.linked-accounts .sqa-wiki-card__divider{display:none}
.linked-accounts .sqa-wiki-card__link{border-left:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.018)}
.linked-accounts .sqa-wiki-card__heading{display:flex;align-items:center;gap:14px;min-width:0}
.linked-accounts .sqa-wiki-card__emblem{display:grid;place-items:center;flex:0 0 46px;height:46px;border-radius:13px;border:1px solid rgba(235,178,60,.35);background:rgba(230,164,39,.11);color:#f5c56a;font-weight:800;font-size:22px}
.linked-accounts .sqa-wiki-card__heading-copy{min-width:0}
.linked-accounts .sqa-wiki-card h3{font-size:1.12rem;line-height:1.3;margin:0 0 5px;font-weight:750;color:inherit}
.linked-accounts .sqa-wiki-card__status{font-size:.82rem;font-weight:650;margin:0;line-height:1.45}
.linked-accounts .sqa-wiki-card__status--ok{color:#76deb4}
.linked-accounts .sqa-wiki-card__status--restricted{color:#e9bc72}
.linked-accounts .sqa-wiki-card__status span{font-size:.65rem;margin-right:5px}
.linked-accounts .sqa-wiki-card__description{font-size:.93rem;line-height:1.6;margin:0;color:inherit}
.linked-accounts .sqa-wiki-card__hint{font-size:.84rem;line-height:1.65;margin:0;opacity:.75}
.linked-accounts .sqa-wiki-card__actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:auto;padding-top:8px}
.linked-accounts .sqa-wiki-card__actions .btn,.linked-accounts .sqa-wiki-card__submit{display:inline-flex;align-items:center;justify-content:center;min-height:40px;max-width:100%;padding:10px 15px;border-radius:10px;text-decoration:none;font-weight:700;font-size:.85rem;white-space:normal;text-align:center;line-height:1.35}
.linked-accounts .sqa-wiki-card__primary,.linked-accounts .sqa-wiki-card__submit{background:#e4a024;color:#171714;border:1px solid #e4a024}
.linked-accounts .sqa-wiki-card__secondary{border:1px solid rgba(231,171,55,.6);color:#f0bd5b;background:transparent}
.linked-accounts .sqa-wiki-card__link-heading{display:flex;align-items:flex-start;gap:12px;margin-bottom:20px}
.linked-accounts .sqa-wiki-card__link-symbol{flex:0 0 32px;width:32px;height:32px;border-radius:9px;background:rgba(231,171,55,.13);display:grid;place-items:center;color:#f0bd5b;font-size:19px}
.linked-accounts .sqa-wiki-card h4{font-size:1rem;line-height:1.4;font-weight:750;margin:0 0 5px;color:inherit}
.linked-accounts .sqa-wiki-card__link-heading p{font-size:.85rem;line-height:1.55;opacity:.78;margin:0}
.linked-accounts .sqa-wiki-card__form{display:flex;flex-direction:column;align-items:flex-start;gap:15px;margin:0}
.linked-accounts .sqa-wiki-card__fields{width:100%;display:grid;gap:13px}
.linked-accounts .sqa-wiki-card__field{min-width:0;width:100%;display:flex;flex-direction:column;gap:7px;font-size:.83rem;font-weight:650;line-height:1.4;color:inherit}
.linked-accounts .sqa-wiki-card__field input{box-sizing:border-box;display:block;width:100%;min-width:0;min-height:42px;padding:10px 12px;border:1px solid rgba(255,255,255,.20);border-radius:9px;background:rgba(11,15,23,.7);color:#fff;font:inherit;font-size:.92rem;outline-offset:2px}
.linked-accounts .sqa-wiki-card__field input:focus{border-color:#e4a024;outline:2px solid rgba(228,160,36,.2)}
.linked-accounts .sqa-wiki-card__field input::placeholder{color:#9ca3af;opacity:1;font-weight:400}
.linked-accounts .sqa-wiki-card__submit{cursor:pointer}
.linked-accounts .sqa-wiki-card__alert{padding:11px 13px;border-radius:9px;margin:0 0 14px;font-size:.85rem;line-height:1.5}
.linked-accounts .sqa-wiki-card__alert--error{background:rgba(239,68,68,.10);border:1px solid rgba(239,68,68,.35)}
.linked-accounts .sqa-wiki-card__alert--success{background:rgba(52,211,153,.10);border:1px solid rgba(52,211,153,.35)}
@media(max-width:920px){.linked-accounts .sqa-wiki-card{grid-template-columns:1fr}.linked-accounts .sqa-wiki-card__link{border-left:0;border-top:1px solid rgba(255,255,255,.1)}}
@media(max-width:560px){.linked-accounts .sqa-wiki-card__main,.linked-accounts .sqa-wiki-card__link{padding:18px}.linked-accounts .sqa-wiki-card__actions{flex-direction:column}.linked-accounts .sqa-wiki-card__actions .btn,.linked-accounts .sqa-wiki-card__submit{width:100%}}
</style>
