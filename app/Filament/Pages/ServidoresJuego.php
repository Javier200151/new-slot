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
        return $result;
    }

    /** @return array<string,mixed> */
    public function controlServer(string $server, string $action, string $selection = '', bool $stopAcademies = false, bool $force = false): array
    {
        abort_unless(static::canAccess(), 403);
        $isLaunch = $action === 'lanzar' || $action === 'arrancar';
        abort_unless($this->allowed($isLaunch ? 'game-servers.launch' : 'game-servers.stop'), 403);
        if (! in_array($action, ['lanzar','arrancar','parar','reiniciar'], true)
            || ! in_array($server, ['operativos','academia','academia_vietnam','academia_ww2'], true)) {
            return ['ok' => false, 'message' => 'Acción o servidor no válido.'];
        }
        if (($action === 'lanzar' && $server !== 'operativos') || ($action === 'arrancar' && $server === 'operativos')) {
            return ['ok' => false, 'message' => 'Acción no disponible para este servidor.'];
        }
        $actor = $this->actor();
        $subject = $server === 'operativos' && $action === 'lanzar' ? mb_substr($selection, 0, 80) : $server;
        $rateKey = 'sqa-mc-action:'.$actor['id'];
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return ['ok' => false, 'message' => 'Demasiadas acciones seguidas. Inténtalo después.'];
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
            if ($action === 'lanzar') {
                if (($serverInfo['estado'] ?? '') !== 'detenido') {
                    throw new MissionControlException('Operativos no está detenido.', 'servidor_ocupado');
                }
                $payload = app(MissionControlDashboard::class)->launchPayload($selection, $stopAcademies, $actor['id'], $actor['nombre']);
                $checkPayload = $payload;
                unset($checkPayload['actor']);
                $preflight = $client->checkLaunch($checkPayload);
                if (! ($preflight['ok'] ?? false)) {
                    $missing = implode(', ', (array) ($preflight['addons']['faltan'] ?? []));
                    throw new MissionControlException('No se puede lanzar: '.($missing !== '' ? 'faltan addons: '.$missing : 'revisa la disponibilidad del PBO, campaña y servidor.'), 'comprobacion_fallida');
                }
                $client->launch($payload);
            } elseif ($action === 'arrancar') {
                if (($serverInfo['tipo'] ?? '') !== 'academia' || ($serverInfo['estado'] ?? '') !== 'detenido') {
                    throw new MissionControlException('La academia no está detenida.', 'servidor_ocupado');
                }
                $client->command($server, 'arrancar', ['actor' => $actor]);
            } else {
                $status = (string) ($serverInfo['estado'] ?? '');
                if (! in_array($status, ['en_marcha', 'caido', 'arrancando'], true)) {
                    throw new MissionControlException('El servidor no permite esta acción en su estado actual.', 'estado_invalido');
                }
                $players = (int) ($serverInfo['jugadores']['actuales'] ?? 0);
                if ($players > 0 && ! $force) {
                    return ['ok' => false, 'requiresForce' => true, 'players' => $players,
                        'message' => 'Hay '.$players.' jugadores conectados. ¿Confirmas '.($action === 'parar' ? 'la parada' : 'el reinicio').' forzado?'];
                }
                $client->command($server, $action, ['actor' => $actor, 'forzar' => $force]);
            }
            $this->logAction($actor, $server, $action, $subject, 'aceptada');
            return ['ok' => true, 'message' => 'Orden enviada correctamente. El estado se actualizará en breve.'];
        } catch (MissionControlException $e) {
            // If Windows explicitly asks for confirmation, preserve that warning in the UI.
            if ($e->errorCode === 'hay_jugadores' && ! $force) {
                return ['ok' => false, 'requiresForce' => true, 'message' => 'Hay jugadores conectados. ¿Forzar la operación?'];
            }
            $this->logAction($actor, $server, $action, $subject, 'error', $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            report($e);
            $this->logAction($actor, $server, $action, $subject, 'error', 'Error al procesar la acción.');
            return ['ok' => false, 'message' => 'No se ha podido completar la acción. Consulta el registro de Laravel.'];
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
            return ['ok' => false, 'message' => 'No se pudo guardar el reinicio diario.'];
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
