<?php

namespace App\Services\MissionControl;

use App\Models\Event;
use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\TelegramService;
use App\Support\ActivityPbo;
use App\Support\ActivityAddons;
use App\Support\PublicActivityPbo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-only notifier. Does not start, stop or modify any Arma server.
 * A successful Telegram response is recorded before advancing to another notice.
 */
final class MissionControlTelegramMonitor
{
    private const TZ = 'Europe/Madrid';

    /** @var array<string,string> */
    private array $templates = [];

    public function __construct(
        private readonly MissionControlClient $missionControl,
        private readonly TelegramService $telegram,
    ) {}

    /** Explicit operator-requested test; the scheduler never calls this. */
    public function sendTest(): void
    {
        $setting = MemberProcedureSetting::current();
        $chat = trim((string) $setting->telegram_server_chat_id);
        if (! $setting->telegram_server_notifications_enabled || ! $this->telegram->isConfigured()
            || ! preg_match('/\A-\d{5,20}\z/', $chat)
            || $chat === trim((string) $setting->telegram_network_chat_id)) {
            throw new \RuntimeException('Activa los avisos y configura un chat de servidores independiente del grupo general.');
        }
        $this->telegram->sendMessage($chat, MissionControlTelegramTemplates::render('test', [], (array) $setting->telegram_server_templates));
    }

    /** @return array<string,int> */
    public function run(): array
    {
        $counts = ['sent' => 0, 'skipped' => 0, 'errors' => 0];
        $setting = MemberProcedureSetting::current();
        $this->templates = (array) ($setting->telegram_server_templates ?? []);
        $chat = trim((string) $setting->telegram_server_chat_id);
        if (! $setting->telegram_server_notifications_enabled || $chat === '' || ! $this->telegram->isConfigured()) {
            return $counts;
        }
        // Never send server notices to the members' chat, even after a configuration mistake.
        if (! preg_match('/\A-\d{5,20}\z/', $chat) || $chat === trim((string) $setting->telegram_network_chat_id)) {
            Log::warning('Mission Control Telegram: el grupo de servidores debe tener su propio chat_id numérico.');
            $counts['errors']++;
            return $counts;
        }
        if (! $this->missionControl->isConfigured()) {
            Log::warning('Mission Control Telegram: agente no configurado; avisos suspendidos.');
            $counts['errors']++;
            return $counts;
        }

        // Shared cache drivers provide a distributed lock; the durable DB unique key
        // additionally protects deliveries if two scheduler instances overlap.
        $lock = Cache::lock('sqa:mission-control-telegram:'.hash('sha256', $chat), 180);
        if (! $lock->get()) {
            $counts['skipped']++;
            return $counts;
        }
        try {
            // Production group must never receive simulated server notices.
            // A temporary connection failure is not a simulation: scheduled checks
            // will still report "unknown" rather than silently disappearing.
            try {
                if (($this->missionControl->health()['modo'] ?? null) === 'simulation') {
                    $counts['skipped']++;
                    return $counts;
                }
            } catch (Throwable) {}
            $this->forwardNotices($chat, $counts);
            $lead = max(30, min(1440, (int) ($setting->telegram_server_notice_minutes ?: 120)));
            $this->checkScheduledEvents($chat, $lead, $counts);
        } finally {
            $lock->release();
        }
        return $counts;
    }

    /** @param array<string,int> $counts */
    private function forwardNotices(string $chat, array &$counts): void
    {
        $cursor = DB::table('mission_control_telegram_cursors')->where('chat_id', $chat)->first();
        try {
            $notices = $this->missionControl->noticesSince((int) ($cursor->last_notice_id ?? 0));
        } catch (Throwable $e) {
            Log::warning('Mission Control Telegram: no se pudieron consultar los avisos.', ['type' => get_class($e)]);
            $counts['errors']++;
            return;
        }
        if (! $cursor) {
            // Never replay up to 200 old operational notices when first enabled.
            $max = 0;
            foreach ($notices as $notice) {
                if (is_array($notice)) { $max = max($max, (int) ($notice['id'] ?? 0)); }
            }
            DB::table('mission_control_telegram_cursors')->insertOrIgnore([
                'chat_id' => $chat, 'last_notice_id' => $max,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return;
        }

        $current = (int) $cursor->last_notice_id;
        usort($notices, static fn ($a, $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        foreach ($notices as $notice) {
            if (! is_array($notice)) { continue; }
            $id = (int) ($notice['id'] ?? 0);
            if ($id <= $current) { continue; }
            $message = trim((string) ($notice['mensaje'] ?? ''));
            $when = null;
            try { $when = CarbonImmutable::parse((string) ($notice['fecha'] ?? ''), self::TZ); } catch (Throwable) {}
            // Empty and stale notices are intentionally acknowledged without sending.
            $stale = $when && $when->lessThan(CarbonImmutable::now(self::TZ)->subHours(12));
            if ($message !== '' && ! $stale) {
                try {
                    $type = trim((string) ($notice['tipo'] ?? ''));
                    $text = $this->message('agent_notice', ['tipo' => $type ?: 'aviso', 'aviso' => $message]);
                    $this->deliver($chat, 'agent:'.$id, $text, null, $type ?: 'notice', $counts);
                } catch (Throwable $e) {
                    // Do not advance: the same notice is retried on the next tick.
                    Log::warning('Mission Control Telegram: no pudo enviar un aviso.', ['id' => $id, 'type' => get_class($e)]);
                    $counts['errors']++;
                    break;
                }
            }
            DB::table('mission_control_telegram_cursors')->where('chat_id', $chat)
                ->update(['last_notice_id' => $id, 'updated_at' => now()]);
            $current = $id;
        }
    }

    /** @param array<string,int> $counts */
    private function checkScheduledEvents(string $chat, int $leadMinutes, array &$counts): void
    {
        $now = CarbonImmutable::now(self::TZ);
        $events = Event::query()
            ->with(['eventStatus', 'activity.activityType', 'activity.campaign', 'activity.pboExport'])
            ->whereBetween('date', [$now->subMinutes(32), $now->addMinutes($leadMinutes + 2)])
            ->whereHas('eventStatus', fn ($q) => $q->where('name', 'ACTIVO'))
            ->whereHas('activity.activityType', fn ($q) => $q->whereIn('name', ['OPERACIÓN', 'OPERACION', 'OPERACIONES', 'OPERATIVO', 'OPERATIVOS']))
            ->orderBy('date')->limit(80)->get();
        if ($events->isEmpty()) { return; }

        // Load expensive catalogs only when at least one readiness checkpoint is due.
        $dueReadiness = [];
        $dueLaunch = [];
        foreach ($events as $event) {
            $start = CarbonImmutable::instance($event->date)->setTimezone(self::TZ);
            $key = $this->eventKey($event, $start);
            $checkpoint = self::checkpointAt($now, $start, $leadMinutes);
            if ($checkpoint !== null && ! $this->hasReady($chat, $event->id, $key)) {
                $msgKey = 'event:'.$key.':readiness:'.$checkpoint;
                if (! $this->wasSent($chat, $msgKey)) {
                    $dueReadiness[] = [$event, $start, $key, $checkpoint, $msgKey];
                }
            }
            if ($now->greaterThanOrEqualTo($start->subMinutes(15)) && $now->lessThanOrEqualTo($start->addMinutes(30))) {
                $dueLaunch[] = [$event, $start, $key];
            }
        }

        $auto = null;
        $autoError = null;
        if ($dueLaunch !== [] || $dueReadiness !== []) {
            try { $auto = $this->missionControl->automaticLaunch(); }
            catch (Throwable $e) { $autoError = 'Sin conexión con el estado del arranque automático.'; }
        }

        if ($dueReadiness !== []) {
            $missions = null;
            $campaigns = null;
            $installedMods = null;
            try {
                $missions = $this->missionControl->missions();
                $campaigns = $this->missionControl->campaigns();
                $installedMods = $this->missionControl->mods();
            } catch (Throwable) { /* Unknown must never be reported as ready. */ }
            foreach ($dueReadiness as [$event, $start, $key, $checkpoint, $msgKey]) {
                try {
                    [$lines, $status] = $this->readiness($event, $missions, $campaigns, $installedMods);
                    $heading = $status === 'ready' ? 'PBO y addons preparados' : ($status === 'unknown' ? 'No se pudo comprobar todo' : 'Falta el PBO o algún addon');
                    $automaticStatus = 'sin verificar';
                    if (is_array($auto)) {
                        if (! ($auto['activo'] ?? false)) {
                            $automaticStatus = 'desactivado';
                        } else {
                            $before = (int) ($auto['minutos_antes'] ?? 15);
                            $automaticStatus = 'activado ('.$before.' min antes)';
                            $entry = collect($auto['eventos'] ?? [])->first(fn ($e) => (int) ($e['evento_id'] ?? 0) === (int) $event->id);
                            if (is_array($entry) && ($entry['automatico'] ?? true) === false) {
                                $automaticStatus = 'excluido para este evento (configuración global: activada)';
                            }
                        }
                    }
                    $next = $status === 'ready'
                        ? 'Los archivos están preparados; no es necesario repetir esta comprobación.'
                        : ($checkpoint > 30
                            ? 'Se repetirá la comprobación en 30 minutos (hasta T−30).'
                            : 'Última comprobación previa. Revisad los archivos antes del arranque.');
                    $text = $this->message('files_'.$status, [
                        'antelacion' => $checkpoint,
                        'estado' => $heading,
                        'evento' => $this->safeName($event->name),
                        'fecha' => $start->format('d/m/Y H:i'),
                        'detalles' => implode("\n", $lines),
                        'automatico' => $automaticStatus,
                        'proxima' => $next,
                        'url' => url('/eventos/'.$event->id),
                    ]);
                    $this->deliver($chat, $msgKey, $text, $event->id, $status, $counts);
                } catch (Throwable $e) {
                    Log::warning('Mission Control Telegram: falló la comprobación de archivos.', ['event_id' => $event->id, 'type' => get_class($e)]);
                    $counts['errors']++;
                }
            }
        }

        if ($dueLaunch !== []) {
            $servers = null;
            try { $servers = $this->missionControl->servers(); }
            catch (Throwable) { $autoError ??= 'No se pudo consultar el estado de Operativos.'; }
            foreach ($dueLaunch as [$event, $start, $key]) {
                try {
                    $base = 'event:'.$key.':launch';
                    $first = $base.':initial';
                    $final = $base.':final';
                    $hasInitial = $this->wasSent($chat, $first);
                    $hasFinal = $this->wasSent($chat, $final);
                    if ($hasFinal) { continue; }
                    if ($hasInitial) {
                        $initialResult = DB::table('mission_control_telegram_deliveries')
                            ->where('chat_id', $chat)->where('message_key', $first)->value('result');
                        if (in_array($initialResult, ['ready', 'manual', 'failed'], true)) { continue; }
                    }
                    [$result, $line] = $this->launchResult($event, $auto, $servers, $autoError);
                    if (! $hasInitial) {
                        $type = match ($result) {
                            'ready' => 'launch_ready', 'manual' => 'launch_manual',
                            'failed' => 'launch_failed', default => 'launch_pending',
                        };
                        $text = $this->message($type, [
                            'evento' => $this->safeName($event->name),
                            'fecha' => $start->format('d/m/Y H:i'),
                            'resultado' => $line, 'url' => url('/eventos/'.$event->id),
                        ]);
                        $this->deliver($chat, $first, $text, $event->id, $result, $counts);
                    } elseif ($result !== 'pending' || $now->greaterThanOrEqualTo($start->addMinutes(30))) {
                        if ($result === 'pending') {
                            $result = 'unknown';
                            $line = 'No se ha podido confirmar un arranque correcto. Revisad Mission Control.';
                        }
                        $type = match ($result) {
                            'ready' => 'launch_ready', 'manual' => 'launch_manual',
                            'failed' => 'launch_failed', default => 'launch_pending',
                        };
                        $text = $this->message($type, [
                            'evento' => $this->safeName($event->name),
                            'fecha' => $start->format('d/m/Y H:i'),
                            'resultado' => $line, 'url' => url('/eventos/'.$event->id),
                        ]);
                        $this->deliver($chat, $final, $text, $event->id, $result, $counts);
                    }
                } catch (Throwable $e) {
                    Log::warning('Mission Control Telegram: falló la comprobación del arranque.', ['event_id' => $event->id, 'type' => get_class($e)]);
                    $counts['errors']++;
                }
            }
        }
    }

    /** @return array{list<string>,string} */
    private function readiness(Event $event, ?array $missions, ?array $campaigns, ?array $installedMods): array
    {
        $activity = $event->activity;
        if (! $activity) { return [['[FALTA] No se encontró la actividad vinculada.'], 'missing']; }

        // Both automatic and manual launches use the *activity's* addon selection.
        $addonIds = ActivityAddons::ids($activity);
        $addonNames = ActivityAddons::names($activity);
        $deletedIds = ActivityAddons::missingIds($activity);
        $addonsReady = $addonIds !== [] && $deletedIds === [];
        $addonLines = [];
        if ($addonIds === []) {
            $addonLines[] = '[FALTA] La actividad no tiene addons seleccionados.';
        } elseif ($deletedIds !== []) {
            $addonLines[] = '[FALTA] Hay '.count($deletedIds).' addon(s) eliminados del catálogo.';
        }
        if ($installedMods === null) {
            $addonLines[] = '[SIN VERIFICAR] No se pudo consultar los addons instalados en Windows.';
        } else {
            $installed = array_fill_keys(array_map('mb_strtolower', array_filter($installedMods, 'is_string')), true);
            $missing = array_values(array_filter($addonNames, static fn (string $name): bool => ! isset($installed[mb_strtolower($name)])));
            if ($missing !== []) {
                $addonsReady = false;
                $addonLines[] = '[FALTA] Addons no instalados: '.implode(', ', array_map(fn (string $name): string => $this->safeName($name), $missing));
            } elseif ($addonsReady) {
                $addonLines[] = '[OK] '.count($addonNames).' addons de la actividad instalados.';
            }
        }
        $addonUnknown = $installedMods === null;

        if ($activity->campaign_id) {
            if ($campaigns === null) {
                return [array_merge(['[SIN VERIFICAR] No se pudo consultar la campaña en Windows.'], $addonLines), 'unknown'];
            }
            $found = collect($campaigns)->first(fn ($c) => (int) ($c['web_id'] ?? 0) === (int) $activity->campaign_id);
            if (! is_array($found)) {
                return [array_merge(['[FALTA] Campaña no configurada en Mission Control.'], $addonLines), 'missing'];
            }
            $pbo = ! empty($found['pbo_existe']);
            $progress = ! empty($found['progreso']);
            $lines = array_merge([
                ($pbo ? '[OK]' : '[FALTA]').' PBO de campaña en Windows',
                ($progress ? '[OK]' : '[FALTA]').' Progreso de campaña disponible',
            ], $addonLines);
            return [$lines, ($pbo && $progress && $addonsReady) ? ($addonUnknown ? 'unknown' : 'ready') : 'missing'];
        }

        $meta = PublicActivityPbo::forActivity($activity);
        $pboName = $meta['pbo_nombre'];
        $export = $activity->pboExport;
        $synced = $export && $export->status === 'synced' && $meta['pbo_sincronizado_windows'];
        if (! $pboName || ! ActivityPbo::validFilename($pboName)) {
            return [array_merge(['[FALTA] Falta un PBO válido en la actividad.'], $addonLines), 'missing'];
        }
        if ($missions === null) {
            return [array_merge(['[SIN VERIFICAR] No se pudo verificar el PBO con Mission Control.', 'PBO: '.$this->safeName($pboName)], $addonLines), 'unknown'];
        }
        $remote = collect($missions)->first(fn ($m) => is_array($m) && mb_strtolower((string) ($m['nombre'] ?? '')) === mb_strtolower($pboName));
        $pboListed = is_array($remote) && (int) ($remote['tamano'] ?? 0) > 0;
        $pboSize = (int) ($activity->pbo_size_bytes ?? 0);
        $sizeOk = $pboListed && ($pboSize <= 0 || (int) $remote['tamano'] === $pboSize);
        $folder = rtrim(trim((string) config('pbo_sync.destination', '/mnt/squadalpha-pbos')), '/');
        if ($folder === '' || ! is_dir($folder) || ! is_readable($folder)) {
            return [array_merge(['[SIN VERIFICAR] No se puede comprobar el PBO en la carpeta SMB.'], $addonLines), 'unknown'];
        }
        // The filename is validated before composing a path; never accept a remote path.
        $pboPath = $folder.'/'.$pboName;
        $pboPresent = is_file($pboPath) && is_readable($pboPath) && filesize($pboPath) > 0;
        $pboHashOk = $pboPresent && is_string($export?->pbo_sha256)
            && hash_equals(strtolower($export->pbo_sha256), (string) hash_file('sha256', $pboPath));
        $pboReady = $synced && $sizeOk && $pboHashOk;
        $lines = array_merge([
            ($pboReady ? '[OK] PBO verificado: ' : '[FALTA] PBO no sincronizado o no coincide: ').$this->safeName($pboName),
        ], $addonLines);
        return [$lines, ($pboReady && $addonsReady) ? ($addonUnknown ? 'unknown' : 'ready') : 'missing'];
    }

    /** @return array{string,string} */
    private function launchResult(Event $event, ?array $auto, ?array $servers, ?string $error): array
    {
        // First inspect the live server: a manual launch is successful even if
        // automatic launch is globally OFF or this event has been excluded.
        $server = is_array($servers) ? collect($servers)->first(fn ($s) => ($s['id'] ?? null) === 'operativos') : null;
        $item = is_array($auto)
            ? collect($auto['eventos'] ?? [])->first(fn ($e) => (int) ($e['evento_id'] ?? 0) === (int) $event->id)
            : null;
        if (is_array($server) && ($server['estado'] ?? '') === 'en_marcha'
            && $this->serverMatchesEvent($server, $event)) {
            $who = mb_strtolower((string) ($server['lanzado_por']['nombre'] ?? ''));
            $manual = ($item['estado'] ?? null) === 'ya_en_marcha'
                || str_contains($who, '(rdp)')
                || (is_array($auto) && ! ($auto['activo'] ?? false))
                || (is_array($item) && ($item['automatico'] ?? true) === false);
            return $manual
                ? ['manual', 'El operativo ya estaba arrancado manualmente con el evento correcto.']
                : ['ready', 'El servidor está en marcha con el evento correcto.'];
        }
        if (! is_array($auto) || ! is_array($servers)) {
            return ['pending', $error ?: 'No se ha podido consultar el resultado de Windows.'];
        }
        if (! ($auto['activo'] ?? false)) {
            return ['pending', 'El lanzamiento automático está desactivado. Todavía no se ha detectado el evento arrancado manualmente.'];
        }
        if (! empty($auto['error_web'])) {
            return ['pending', 'Mission Control no ha podido leer el calendario: '.$this->safeName($auto['error_web'])];
        }
        if (! is_array($item)) {
            return ['pending', 'El evento aún no aparece en el calendario automático del servidor.'];
        }
        if (($item['automatico'] ?? true) === false) {
            return ['pending', 'Este evento está excluido del lanzamiento automático. Pendiente de arranque manual.'];
        }
        $state = (string) ($item['estado'] ?? 'pendiente');
        if (! is_array($server)) {
            return ['pending', 'No se ha podido comprobar el estado de Operativos.'];
        }
        if (in_array($state, ['fallido', 'no_lanzado'], true)) {
            $why = trim((string) ($item['problema'] ?? $server['ultimo_error'] ?? ''));
            return ['failed', 'Mission Control informa de que el arranque ha fallado.'.($why !== '' ? ' '.$this->safeName($why) : '')];
        }
        if (($server['estado'] ?? '') === 'en_marcha') {
            return ['pending', 'Operativos está en marcha, pero con otro evento.'];
        }
        return ['pending', 'Windows todavía no confirma el arranque (estado: '.$this->safeName($state).').'];
    }

    /** Exact event identity takes precedence; a mismatched ID must never pass via file name. */
    private function serverMatchesEvent(array $server, Event $event): bool
    {
        $mission = is_array($server['mision'] ?? null) ? $server['mision'] : [];
        $runningId = (int) ($mission['evento']['id'] ?? 0);
        if ($runningId > 0) {
            return $runningId === (int) $event->id;
        }
        $runningActivity = (int) ($mission['actividad']['id'] ?? 0);
        if ($runningActivity > 0 && $runningActivity === (int) $event->activity_id) {
            return true;
        }
        // The RDP panel may launch by file name without an event/actividad ID.
        if (! $event->activity) { return false; }
        $remotePbo = mb_strtolower(trim((string) ($mission['pbo'] ?? '')));
        $localPbo = (string) (PublicActivityPbo::forActivity($event->activity)['pbo_nombre'] ?? '');
        return $remotePbo !== '' && $localPbo !== '' && $remotePbo === mb_strtolower($localPbo);
    }

    /** @param array<string,scalar> $variables */
    private function message(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_string($value)) {
                $variables[$key] = MissionControlTelegramTemplates::plain($value);
            }
        }
        return MissionControlTelegramTemplates::render($template, $variables, $this->templates);
    }

    public static function checkpointAt(CarbonImmutable $now, CarbonImmutable $start, int $lead): ?int
    {
        $lead = max(30, min(1440, $lead));
        $slots = [$lead];
        for ($minutes = $lead - 30; $minutes > 30; $minutes -= 30) { $slots[] = $minutes; }
        $slots[] = 30;
        $latest = null;
        foreach (array_unique($slots) as $minutes) {
            if ($now->greaterThanOrEqualTo($start->subMinutes($minutes)) && $now->lessThan($start->subMinutes(15))) {
                $latest = $minutes;
            }
        }
        return $latest;
    }

    private function eventKey(Event $event, CarbonImmutable $start): string
    {
        return $event->id.':'.$start->utc()->format('YmdHi');
    }

    private function hasReady(string $chat, int $eventId, string $key): bool
    {
        return DB::table('mission_control_telegram_deliveries')
            ->where('chat_id', $chat)->where('event_id', $eventId)
            ->where('message_key', 'like', 'event:'.$key.':readiness:%')
            ->where('result', 'ready')->exists();
    }

    private function wasSent(string $chat, string $key): bool
    {
        return DB::table('mission_control_telegram_deliveries')
            ->where('chat_id', $chat)->where('message_key', $key)->exists();
    }

    /** @param array<string,int> $counts */
    private function deliver(string $chat, string $key, string $text, ?int $eventId, string $result, array &$counts): void
    {
        if ($this->wasSent($chat, $key)) { $counts['skipped']++; return; }
        // Telegram has no idempotency key. If the process crashes between send and
        // DB insert a duplicate is possible; no false 'sent' entry is ever recorded.
        $this->telegram->sendMessage($chat, mb_substr($text, 0, 4000));
        DB::table('mission_control_telegram_deliveries')->insertOrIgnore([
            'chat_id' => $chat, 'message_key' => $key, 'event_id' => $eventId,
            'result' => $result, 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $counts['sent']++;
    }

    private function safeName(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));
        return mb_substr(preg_replace('/[\r\n\t]+/u', ' ', $value) ?? '', 0, 210);
    }
}
