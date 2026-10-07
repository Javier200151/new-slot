<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LinkedAccounts\DiscordAccountLinkService;
use App\Services\LinkedAccounts\SteamAccountLinkService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LinkedAccountController extends Controller
{
    private const FLOW_TTL_SECONDS = 600;

    public function redirectDiscord(
        Request $request,
        DiscordAccountLinkService $discord,
    ): RedirectResponse {
        if (! $discord->isConfigured()) {
            return $this->warning('La vinculación con Discord aún no está configurada en el servidor.');
        }

        $state = Str::random(64);
        $redirectUri = $this->discordRedirectUri();

        $request->session()->put('linked_accounts.discord', [
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'started_at' => now()->timestamp,
        ]);

        try {
            return redirect()->away($discord->authorizationUrl($state, $redirectUri));
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->warning($exception->getMessage());
        }
    }

    public function discordCallback(
        Request $request,
        DiscordAccountLinkService $discord,
        AuditLogger $audit,
    ): RedirectResponse {
        $flow = $request->session()->pull('linked_accounts.discord');

        if (! $this->validFlow($flow, (string) $request->query('state'))) {
            return $this->warning('La solicitud de vinculación con Discord ha caducado o no es válida. Inténtalo de nuevo.');
        }

        if ($request->filled('error')) {
            return $this->warning('Discord no autorizó la vinculación de la cuenta.');
        }

        $code = trim((string) $request->query('code'));
        if ($code === '') {
            return $this->warning('Discord no devolvió el código de autorización esperado.');
        }

        try {
            $identity = $discord->resolveIdentity($code, (string) $flow['redirect_uri']);
            $user = $request->user();

            if (! $user instanceof User) {
                return redirect()->route('login');
            }

            $conflict = User::withTrashed()
                ->where('discord_id', $identity['id'])
                ->where($user->getKeyName(), '!=', $user->getKey())
                ->exists();

            if ($conflict) {
                return $this->warning('Esa cuenta de Discord ya está vinculada a otro usuario de NewSlot.');
            }

            $currentDiscordId = trim((string) $user->discord_id);
            if ($currentDiscordId !== '' && ! hash_equals($currentDiscordId, $identity['id'])) {
                return $this->warning('Tu perfil ya tiene otro Discord ID. No se ha sobrescrito; un administrador debe revisar el conflicto.');
            }

            $user->forceFill([
                'discord_id' => $identity['id'],
                'discord_username' => $identity['username'],
                'discord_linked_at' => now(),
            ])->save();

            $this->auditAccountEvent($audit, $user, 'external_account_linked', 'discord');

            return redirect(route('profile.show') . '#profile-linked-accounts')
                ->with('status', 'discord-linked');
        } catch (QueryException $exception) {
            report($exception);

            return $this->warning('Esa cuenta de Discord ya está vinculada a otro usuario de NewSlot.');
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->warning($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->warning('No se pudo completar la vinculación con Discord. Inténtalo de nuevo.');
        }
    }

    public function unlinkDiscord(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'discord_id' => null,
            'discord_username' => null,
            'discord_linked_at' => null,
        ])->save();

        $this->auditAccountEvent($audit, $user, 'external_account_unlinked', 'discord');

        return redirect(route('profile.show') . '#profile-linked-accounts')
            ->with('status', 'discord-unlinked');
    }

    public function redirectSteam(
        Request $request,
        SteamAccountLinkService $steam,
    ): RedirectResponse {
        $state = Str::random(64);
        $returnTo = route('profile.accounts.steam.callback', ['state' => $state]);

        $request->session()->put('linked_accounts.steam', [
            'state' => $state,
            'return_to' => $returnTo,
            'started_at' => now()->timestamp,
        ]);

        try {
            return redirect()->away($steam->authorizationUrl($returnTo));
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->warning($exception->getMessage());
        }
    }

    public function steamCallback(
        Request $request,
        SteamAccountLinkService $steam,
        AuditLogger $audit,
    ): RedirectResponse {
        $flow = $request->session()->pull('linked_accounts.steam');

        if (! $this->validFlow($flow, (string) $request->query('state'))) {
            return $this->warning('La solicitud de vinculación con Steam ha caducado o no es válida. Inténtalo de nuevo.');
        }

        try {
            $identity = $steam->resolveIdentity($request->query(), (string) $flow['return_to']);
            $user = $request->user();

            if (! $user instanceof User) {
                return redirect()->route('login');
            }

            $conflict = User::withTrashed()
                ->where('steam_id', $identity['id'])
                ->where($user->getKeyName(), '!=', $user->getKey())
                ->exists();

            if ($conflict) {
                return $this->warning('Esa cuenta de Steam ya está vinculada a otro usuario de NewSlot.');
            }

            $currentSteamId = trim((string) $user->steam_id);
            if ($currentSteamId !== '' && ! hash_equals($currentSteamId, $identity['id'])) {
                return $this->warning('Tu perfil ya tiene otro SteamID64. No se ha sobrescrito; un administrador debe revisar el conflicto.');
            }

            $user->forceFill([
                'steam_id' => $identity['id'],
                'steam_linked_at' => now(),
                'steam_profile_url' => $identity['profile_url'],
            ])->save();

            $this->auditAccountEvent($audit, $user, 'external_account_linked', 'steam');

            return redirect(route('profile.show') . '#profile-linked-accounts')
                ->with('status', 'steam-linked');
        } catch (QueryException $exception) {
            report($exception);

            return $this->warning('Esa cuenta de Steam ya está vinculada a otro usuario de NewSlot.');
        } catch (RuntimeException $exception) {
            report($exception);

            return $this->warning($exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return $this->warning('No se pudo completar la vinculación con Steam. Inténtalo de nuevo.');
        }
    }

    public function unlinkSteam(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'steam_id' => null,
            'steam_linked_at' => null,
            'steam_profile_url' => null,
        ])->save();

        $this->auditAccountEvent($audit, $user, 'external_account_unlinked', 'steam');

        return redirect(route('profile.show') . '#profile-linked-accounts')
            ->with('status', 'steam-unlinked');
    }

    /**
     * @param  mixed  $flow
     */
    private function validFlow(mixed $flow, string $state): bool
    {
        if (! is_array($flow)
            || ! isset($flow['state'], $flow['started_at'])
            || $state === '') {
            return false;
        }

        if (! hash_equals((string) $flow['state'], $state)) {
            return false;
        }

        return now()->timestamp - (int) $flow['started_at'] <= self::FLOW_TTL_SECONDS;
    }

    private function discordRedirectUri(): string
    {
        $configured = trim((string) config('services.discord_oauth.redirect_uri'));

        return $configured !== ''
            ? $configured
            : route('profile.accounts.discord.callback');
    }


    private function auditAccountEvent(
        AuditLogger $audit,
        User $user,
        string $event,
        string $provider,
    ): void {
        try {
            $audit->security(
                event: $event,
                subject: $user,
                properties: ['provider' => $provider],
                causer: $user,
            );
        } catch (Throwable $exception) {
            // La asociación ya es correcta. Un fallo de auditoría no debe revertirla
            // ni hacer creer al usuario que la operación externa ha fallado.
            report($exception);
        }
    }

    private function warning(string $message): RedirectResponse
    {
        return redirect(route('profile.show') . '#profile-linked-accounts')
            ->with('warning', $message);
    }
}
