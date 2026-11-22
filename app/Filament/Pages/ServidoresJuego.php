<?php

namespace App\Filament\Pages;

use App\Models\GameServerActionLog;
use App\Services\MissionControl\MissionControlClient;
use App\Services\MissionControl\MissionControlDashboard;
use App\Services\MissionControl\MissionControlException;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Private Filament page: every agent call passes through this authenticated Livewire class. */
class ServidoresJuego extends Page
{
    protected static ?string $slug = 'servidores-juego';
    protected static ?string $title = 'Servidores de juego';
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'filament.pages.servidores-juego';
    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole('admin') || $user->can('game-servers.view')));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /** @return array<string,mixed> */
    public function loadPanel(bool $refreshCatalog = false): array
    {
        abort_unless(static::canAccess(), 403);
        $result = app(MissionControlDashboard::class)->snapshot($refreshCatalog);
        $result['canLaunch'] = $this->allowed('game-servers.launch');
        $result['canStop'] = $this->allowed('game-servers.stop');
        $result['canConfigure'] = $this->allowed('game-servers.stop');
        $result['actorName'] = $this->actor()['nombre'];
        return $result;
    }

    /**
     * Actions never trust a browser-supplied state. Mission Control is authoritative.
     * A force retry requires a short-lived token issued only after the agent's 409.
     * @return array<string,mixed>
     */
    public function controlServer(string $server, string $action, string $selection = '', bool $stopAcademies = false, bool $force = false, string $forceToken = ''): array
    {
        abort_unless(static::canAccess(), 403);
        $isLaunch = in_array($action, ['lanzar', 'arrancar'], true);
        abort_unless($this->allowed($isLaunch ? 'game-servers.launch' : 'game-servers.stop'), 403);
        if (! in_array($server, ['operativos', 'academia', 'academia_vietnam', 'academia_ww2'], true)
            || ! in_array($action, ['lanzar', 'arrancar', 'parar', 'reiniciar'], true)
            || ($action === 'lanzar' && $server !== 'operativos')
            || ($action === 'arrancar' && $server === 'operativos')) {
            return ['ok' => false, 'message' => 'Acción o servidor no válido.'];
        }

        $recentKey = 'sqa-mc-recent-action:'.$server;
        if (Cache::has($recentKey)) {
            return ['ok' => false, 'message' => 'El servidor acaba de recibir una orden. Comprueba el estado antes de enviar otra.'];
        }

        $actor = $this->actor();
        $subject = $server === 'operativos' && $action === 'lanzar' ? mb_substr($selection, 0, 80) : $server;
        $rateKey = 'sqa-mc-action:'.$actor['id'];
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return ['ok' => false, 'message' => 'Demasiadas acciones seguidas. Inténtalo después.'];
        }

        // The token is deliberately tied to the user, action, and the exact launch selection.
        $confirmationKey = 'sqa-mc-force:'.$actor['id'].':'.$server.':'.$action;
        $fingerprint = hash('sha256', $selection.'|'.($stopAcademies ? '1' : '0'));
        if ($force) {
            $authorization = Cache::get($confirmationKey);
            if (! is_array($authorization) || ! is_string($authorization['token'] ?? null)
                || ! hash_equals($authorization['token'], $forceToken)
                || ! hash_equals((string) ($authorization['fingerprint'] ?? ''), $fingerprint)) {
                return ['ok' => false, 'message' => 'La confirmación ha caducado. Vuelve a solicitar la acción.'];
            }
            Cache::forget($confirmationKey);
        } else {
            Cache::forget($confirmationKey);
        }

        // Only one order per server can be sent at once, even from separate admin sessions.
        $lock = Cache::lock('sqa-mc-server-action:'.$server, 180);
        if (! $lock->get()) {
            return ['ok' => false, 'message' => 'Ya hay una orden en curso para este servidor. Comprueba su estado antes de repetir.'];
        }

        RateLimiter::hit($rateKey, 60);
        try {
            $client = app(MissionControlClient::class);
            if (! $client->isConfigured()) {
                throw new MissionControlException((string) $client->configError(), 'sin_configurar');
            }
            $health = $client->health();
            if (($health['solo_lectura'] ?? true) === true) {
                throw new MissionControlException('Mission Control está en modo de solo lectura.', 'solo_lectura');
            }
            $serverInfo = collect($client->servers())->firstWhere('id', $server);
            if (! is_array($serverInfo) || empty($serverInfo['controlable']) || ($serverInfo['estado'] ?? '') === 'externo') {
                throw new MissionControlException('Este servidor no se puede controlar desde el panel.', 'no_controlable');
            }
            $state = (string) ($serverInfo['estado'] ?? '');
            $isOperation = $server === 'operativos';
            $allowed = match ($action) {
                'lanzar' => $isOperation && in_array($state, ['detenido', 'caido'], true),
                'arrancar' => ! $isOperation && ($serverInfo['tipo'] ?? '') === 'academia' && in_array($state, ['detenido', 'caido'], true),
                'parar' => $isOperation ? in_array($state, ['en_marcha', 'arrancando', 'caido'], true) : $state === 'en_marcha',
                'reiniciar' => in_array($state, ['en_marcha', 'caido'], true) && ($isOperation || $state === 'en_marcha'),
                default => false,
            };
            if (! $allowed) {
                throw new MissionControlException('El estado del servidor ha cambiado. Actualiza la página antes de repetir.', 'estado_invalido');
            }

            if ($action === 'lanzar') {
                $payload = app(MissionControlDashboard::class)->launchPayload($selection, $stopAcademies, $actor['id'], $actor['nombre']);
                $checkPayload = $payload;
                unset($checkPayload['actor']);
                if ($force) { $checkPayload['forzar'] = true; }
                $preflight = $client->checkLaunch($checkPayload);
                if (! ($preflight['ok'] ?? false)) {
                    $missing = implode(', ', (array) ($preflight['addons']['faltan'] ?? []));
                    throw new MissionControlException('No se puede lanzar: '.($missing !== '' ? 'faltan addons: '.$missing : 'revisa el PBO, la campaña y el estado del servidor.'), 'comprobacion_fallida');
                }
                if ($force) { $payload['forzar'] = true; }
                $client->launch($payload);
            } elseif ($action === 'arrancar') {
                $client->command($server, 'arrancar', ['actor' => $actor]);
            } else {
                // Do not decide from the number of players seen in a previous poll.
                // The agent returns 409 hay_jugadores if confirmation is needed.
                $client->command($server, $action, ['actor' => $actor, 'forzar' => $force]);
            }

            Cache::put($recentKey, true, now()->addSeconds(6));
            $this->logAction($actor, $server, $action, $subject, 'aceptada');
            return ['ok' => true, 'message' => match ($action) {
                'lanzar' => 'Lanzando el evento. Consulta el estado del servidor.',
                'arrancar' => 'Arrancando la academia.',
                'parar' => 'Parando el servidor.',
                'reiniciar' => 'Reiniciando el servidor.',
            }];
        } catch (MissionControlException $e) {
            if ($e->errorCode === 'hay_jugadores' && ! $force) {
                $number = $e->detail['jugadores'] ?? null;
                $number = is_numeric($number) ? (int) $number : null;
                $token = bin2hex(random_bytes(16));
                Cache::put($confirmationKey, ['token' => $token, 'fingerprint' => $fingerprint], now()->addSeconds(60));
                $message = $number === null
                    ? 'Hay jugadores conectados. ¿Seguro que quieres continuar? Saldrán del servidor.'
                    : 'Hay '.$number.' '.($number === 1 ? 'jugador conectado' : 'jugadores conectados').'. ¿Seguro que quieres continuar? Saldrán del servidor.';
                return ['ok' => false, 'requiresForce' => true, 'forceToken' => $token, 'players' => $number, 'message' => $message];
            }
            $this->logAction($actor, $server, $action, $subject, 'error', $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            report($e);
            $this->logAction($actor, $server, $action, $subject, 'error', 'Error al procesar la acción.');
            return ['ok' => false, 'message' => 'No se pudo confirmar la orden. Comprueba el estado antes de repetirla.'];
        } finally {
            $lock->release();
        }
    }

    /** @return array<string,mixed> */
    public function saveDailyRestart(string $server, bool $enabled, string $time): array
    {
        abort_unless(static::canAccess() && $this->allowed('game-servers.stop'), 403);
        if (! in_array($server, ['academia','academia_vietnam','academia_ww2'], true)
            || ! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/D', $time)) {
            return ['ok' => false, 'message' => 'Servidor u hora no válidos.'];
        }
        $actor = $this->actor();
        $rateKey = 'sqa-mc-action:'.$actor['id'];
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return ['ok' => false, 'message' => 'Demasiadas acciones seguidas.'];
        }
        $lock = Cache::lock('sqa-mc-server-action:'.$server, 180);
        if (! $lock->get()) {
            return ['ok' => false, 'message' => 'Ya hay una orden en curso para esta academia.'];
        }
        RateLimiter::hit($rateKey, 60);
        try {
            $client = app(MissionControlClient::class);
            if (! $client->isConfigured() || ($client->health()['solo_lectura'] ?? true)) {
                throw new MissionControlException('Mission Control no está disponible para cambios.', 'sin_configurar');
            }
            $client->setDailyRestart($server, $enabled, $time, $actor);
            $this->logAction($actor, $server, 'reinicio_diario', $enabled ? $time : 'desactivado', 'aceptada');
            return ['ok' => true, 'message' => 'Reinicio diario actualizado en Windows.'];
        } catch (MissionControlException $e) {
            $this->logAction($actor, $server, 'reinicio_diario', $time, 'error', $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            report($e);
            return ['ok' => false, 'message' => 'No se pudo confirmar el reinicio diario. Comprueba el ajuste antes de repetir.'];
        } finally {
            $lock->release();
        }
    }

    private function allowed(string $permission): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->hasRole('admin') || $user->can($permission)));
    }

    /** @return array{id:int,nombre:string} */
    private function actor(): array
    {
        $user = auth()->user();
        return ['id' => (int) $user->id, 'nombre' => (string) ($user->nick ?: 'Administrador')];
    }

    private function logAction(array $actor, string $server, string $action, string $subject, string $result, ?string $detail = null): void
    {
        try {
            GameServerActionLog::query()->create([
                'user_id' => $actor['id'], 'actor' => $actor['nombre'], 'server_id' => $server,
                'action' => $action, 'subject' => mb_substr($subject, 0, 255), 'result' => $result,
                'detail' => $detail === null ? null : mb_substr($detail, 0, 500), 'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // A logging failure must not make an accepted server order look unexecuted.
            Log::error('Falló la auditoría de Mission Control.', [
                'user_id' => $actor['id'], 'server' => $server, 'action' => $action, 'result' => $result,
                'exception' => $e::class,
            ]);
        }
    }
}
