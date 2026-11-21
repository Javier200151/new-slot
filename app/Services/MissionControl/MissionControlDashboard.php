<?php

namespace App\Services\MissionControl;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Event;
use App\Models\GameServerActionLog;
use App\Support\ActivityPbo;
use App\Support\PublicActivityPbo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Builds the server-side mission picker and prepares whitelisted launch payloads. */
final class MissionControlDashboard
{
    public function __construct(private MissionControlClient $client) {}

    /** @return array<string,mixed> */
    public function snapshot(bool $refreshCatalog = false): array
    {
        $result = [
            'connected' => false,
            'enabled' => $this->client->enabled(),
            'readOnly' => true,
            'error' => $this->client->configError(),
            'servers' => [],
            'activities' => [],
            'log' => $this->recentLog(),
            'checkedAt' => now()->format('H:i:s'),
        ];
        if ($result['error']) {
            return $result;
        }
        try {
            $health = $this->client->health();
            $servers = $this->client->servers();
            $result['connected'] = true;
            $result['readOnly'] = (bool) ($health['solo_lectura'] ?? true);
            $result['mode'] = (string) ($health['modo'] ?? 'desconocido');
            $result['version'] = (string) ($health['version'] ?? '');
            // Return only known server IDs, never user-controlled endpoints.
            foreach ($servers as $row) {
                if (! is_array($row) || ! in_array($row['id'] ?? null, ['operativos','academia','academia_vietnam','academia_ww2'], true)) {
                    continue;
                }
                $result['servers'][] = $row;
            }
            if ($refreshCatalog) {
                Cache::forget('mission-control.catalog.v1');
            }
            $catalog = Cache::remember('mission-control.catalog.v1', now()->addSeconds(30), fn (): array => [
                'misiones' => $this->client->missions(),
                'campanas' => $this->client->campaigns(),
                'mods' => $this->client->mods(),
            ]);
            $result['activities'] = $this->picker($catalog['misiones'], $catalog['campanas'], $catalog['mods']);
        } catch (MissionControlException $e) {
            $result['error'] = $e->getMessage();
            $result['connected'] = false;
            $result['readOnly'] = true;
            $result['servers'] = [];
            $result['activities'] = [];
        }

        return $result;
    }

    /** @return list<array<string,mixed>> */
    private function recentLog(): array
    {
        return GameServerActionLog::query()->orderByDesc('id')->limit(20)->get()
            ->map(static fn (GameServerActionLog $row): array => [
                'at' => $row->created_at?->format('d/m H:i') ?? '',
                'text' => $row->actor.' · '.$row->action.' · '.($row->subject ?: $row->server_id).' ('.$row->result.')',
            ])->all();
    }

    /** @return list<array<string,mixed>> */
    private function picker(array $missions, array $campaigns, array $installedMods): array
    {
        $today = now()->startOfDay();
        $relations = ['activity.activityType', 'activity.map', 'activity.campaign', 'activity.pboExport', 'eventStatus'];
        $upcoming = Event::query()->with($relations)
            ->where('date', '>=', $today)->whereHas('eventStatus', fn ($q) => $q->where('name', 'ACTIVO'))
            ->orderBy('date')->limit(80)->get();
        $previous = Event::query()->with($relations)
            ->where('date', '<', now())->whereHas('eventStatus', fn ($q) => $q->whereIn('name', ['ACTIVO','FINALIZADO']))
            ->orderByDesc('date')->limit(200)->get()
            ->reject(fn (Event $event): bool => $upcoming->contains('id', $event->id))
            ->filter(fn (Event $event): bool => $this->isOperation($event->activity))
            ->take(3);

        $missionNames = collect($missions)->filter(fn ($m) => is_array($m) && is_string($m['nombre'] ?? null))
            ->keyBy(fn ($m) => mb_strtolower($m['nombre']))->all();
        $campaignMap = collect($campaigns)->filter(fn ($c) => is_array($c) && isset($c['web_id']))
            ->keyBy(fn ($c) => (int) $c['web_id'])->all();
        $mods = array_fill_keys(array_map('mb_strtolower', array_filter($installedMods, 'is_string')), true);
        $output = [];
        $linkedMissionNames = [];
        $coveredCampaigns = [];

        foreach ($upcoming->concat($previous) as $event) {
            $activity = $event->activity;
            if (! $activity) { continue; }
            $group = $previous->contains('id', $event->id) ? 'past'
                : ($event->date?->isToday() ? 'today' : 'upcoming');
            $item = $this->activityOption($activity, $event, $group, $missionNames, $campaignMap, $mods);
            if ($item) {
                $output[] = $item;
                if ($item['pbo']) { $linkedMissionNames[mb_strtolower($item['pbo'])] = true; }
                if ($activity->campaign_id) { $coveredCampaigns[(int) $activity->campaign_id] = true; }
            }
        }

        // Show stand-alone activity PBOs already present on Windows, even without calendar events.
        $loose = Activity::query()->with(['activityType','map','campaign','pboExport'])
            ->whereNotNull('pbo_path')->orderByDesc('id')->limit(150)->get();
        foreach ($loose as $activity) {
            $name = basename((string) $activity->pbo_path);
            if (! $this->isOperation($activity) || isset($linkedMissionNames[mb_strtolower($name)]) || ! isset($missionNames[mb_strtolower($name)])) {
                continue;
            }
            $item = $this->activityOption($activity, null, 'folder', $missionNames, $campaignMap, $mods);
            if ($item) {
                $output[] = $item;
                $linkedMissionNames[mb_strtolower($name)] = true;
            }
        }
        // List the remaining files as read-only. We have no authoritative addons for an unlinked file.
        foreach ($missions as $mission) {
            $filename = $mission['nombre'] ?? null;
            if (! is_string($filename) || ! ActivityPbo::validFilename($filename) || isset($linkedMissionNames[mb_strtolower($filename)])) {
                continue;
            }
            $output[] = [
                'id' => 'file:'.hash('sha256', $filename), 'group' => 'folder', 'name' => $filename,
                'whenLabel' => '', 'event' => 'Archivo de Windows sin actividad asociada',
                'fileLabel' => $filename, 'pbo' => $filename, 'html' => null,
                'map' => '—', 'slots' => '—', 'profile' => '—', 'addons' => 0,
                'sync' => 'partial', 'pboReady' => true, 'htmlReady' => false,
                'launchable' => false, 'reason' => 'Asocia este PBO a una actividad con addons para poder lanzarlo.',
                'mode' => 'Archivo de Windows', 'webLink' => null, 'serverMods' => [],
            ];
        }
        // Existing campaigns without a selected nearby event: use the campaign profile in Windows.
        foreach ($campaignMap as $id => $remote) {
            if (isset($coveredCampaigns[$id])) { continue; }
            $local = Campaign::query()->find($id);
            if (! $local) { continue; }
            $activity = Activity::query()->with(['activityType','map','campaign','pboExport'])
                ->where('campaign_id', $id)->orderByDesc('id')->first();
            if (! $activity || ! $this->isOperation($activity)) { continue; }
            $item = $this->activityOption($activity, null, 'campaign', $missionNames, $campaignMap, $mods);
            if ($item) { $output[] = $item; }
        }

        return $output;
    }

    private function isOperation(?Activity $activity): bool
    {
        if (! $activity) { return false; }
        $name = Str::upper(Str::ascii(trim((string) ($activity->activityType?->name ?? ''))));
        return in_array($name, ['OPERACION','OPERACIONES','OPERATIVO','OPERATIVOS'], true);
    }

    /** @return array<string,mixed>|null */
    private function activityOption(Activity $activity, ?Event $event, string $group, array $missionNames, array $campaignMap, array $installedMods): ?array
    {
        $public = PublicActivityPbo::forActivity($activity);
        $filename = $public['pbo_nombre'];
        $campaign = $activity->campaign_id ? ($campaignMap[(int) $activity->campaign_id] ?? null) : null;
        $isCampaign = $activity->campaign_id !== null;
        $hasPbo = $filename && isset($missionNames[mb_strtolower($filename)]);
        $pboReady = $hasPbo && $public['pbo_sincronizado_windows'];
        $htmlReady = (bool) $public['html_sincronizado_windows'];
        $addonIds = collect($activity->addons['addon_ids'] ?? [])->map(fn ($v) => (int) $v)->filter()->unique()->all();
        $addonNames = $addonIds ? \App\Models\Addon::query()->whereIn('id', $addonIds)->pluck('name')->all() : [];
        $missing = array_values(array_filter($addonNames, fn ($name) => ! isset($installedMods[mb_strtolower($name)])));
        $campaignReady = $campaign && ! empty($campaign['pbo_existe']) && ! empty($campaign['progreso'])
            && empty($campaign['faltan']) && empty($campaign['aviso']);
        $reason = $isCampaign
            ? ($campaignReady ? null : ((string) ($campaign['aviso'] ?? 'Campaña no preparada en SQA Mission Control.')))
            : (! $pboReady ? 'El PBO todavía no está confirmado en Windows.'
                : (! $addonIds ? 'La actividad necesita addons seleccionados en Filament.'
                    : ($missing ? 'Faltan addons instalados: '.implode(', ', array_slice($missing, 0, 3)) : null)));
        $dateLabel = $event?->date?->translatedFormat('D d/m H:i') ?? '';

        return [
            'id' => $event ? 'event:'.$event->id : 'activity:'.$activity->id,
            'group' => $group,
            'name' => $activity->name,
            'whenLabel' => $dateLabel,
            'event' => $event ? 'Evento · '.$dateLabel : 'Sin evento asociado',
            'eventId' => $event?->id,
            'fileLabel' => $filename ?: ((string) $activity->pbo ?: 'Sin PBO'),
            'pbo' => $isCampaign ? ($campaign['pbo'] ?? $filename) : $filename,
            'html' => $public['html_nombre'],
            'map' => $activity->map?->name ?: 'Sin mapa',
            'slots' => count(collect($event?->orbat['groups'] ?? $activity->orbat['groups'] ?? [])->flatMap(fn ($g) => $g['slots'] ?? [])),
            'profile' => ($isCampaign ? 'Campaña' : ($event?->multiclans ? 'Multiclán' : 'Estándar')).' · 3 HC',
            'addons' => $isCampaign ? count($campaign['addons'] ?? []) : count($addonNames),
            'serverMods' => $campaign['server_mods'] ?? [],
            'mode' => $isCampaign ? 'Campaña' : 'OPERACIÓN',
            'sync' => $isCampaign ? ($campaignReady ? 'ok' : 'missing') : ($pboReady && $htmlReady ? 'ok' : (($pboReady || $htmlReady) ? 'partial' : 'missing')),
            'pboReady' => (bool) ($isCampaign ? ($campaign['pbo_existe'] ?? false) : $pboReady),
            'htmlReady' => (bool) ($isCampaign ? ! empty($campaign['progreso']) : $htmlReady),
            'launchable' => $this->isOperation($activity) && ($isCampaign ? (bool) $campaignReady : ($pboReady && $addonIds && ! $missing)),
            'reason' => $reason,
            'webLink' => $event ? route('events.show', ['event' => $event->id]) : null,
            'saveInfo' => $isCampaign ? $this->campaignProgressLabel($activity->campaign?->name ?? ($campaign['nombre'] ?? null), $campaign['progreso'] ?? null) : null,
        ];
    }

    /**
     * Human-readable campaign progress metadata from Mission Control (ISO 8601 + KB).
     * Dates are displayed in the same timezone as Squad ALPHA, not as raw API values.
     * @param mixed $progress
     */
    private function campaignProgressLabel(?string $name, mixed $progress): ?string
    {
        if (! is_array($progress) || ! is_string($progress['fecha'] ?? null) || trim($progress['fecha']) === '') {
            return null;
        }

        try {
            $savedAt = Carbon::parse($progress['fecha'])->setTimezone('Europe/Madrid');
        } catch (\Throwable) {
            return null;
        }

        $today = Carbon::now('Europe/Madrid');
        if ($savedAt->isSameDay($today)) {
            $dateLabel = 'hoy '.$savedAt->format('H:i');
        } elseif ($savedAt->isSameDay($today->copy()->subDay())) {
            $dateLabel = 'ayer '.$savedAt->format('H:i');
        } else {
            $dateLabel = $savedAt->locale('es')->translatedFormat(
                $savedAt->year === $today->year ? 'D d/m H:i' : 'd/m/Y H:i'
            );
        }

        $title = trim((string) ($name ?: 'Sin nombre'));
        $label = 'Campaña: '.$title.' · progreso guardado '.$dateLabel;
        $sizeKb = $progress['tamano_kb'] ?? null;
        if (is_numeric($sizeKb) && (float) $sizeKb > 0) {
            $label .= ' ('.number_format((float) $sizeKb / 1024, 1, ',', '.').' MB)';
        }

        return $label;
    }

    /**
     * Compute a fresh payload from the database on every click. Browser selection is just an identifier.
     * @return array<string,mixed>
     */
    public function launchPayload(string $selection, bool $stopAcademies, int $actorId, string $actorName): array
    {
        if (! preg_match('/\A(?:event|activity):[1-9][0-9]{0,9}\z/D', $selection)) {
            throw new MissionControlException('Selecciona un evento o una actividad de la web.', 'seleccion_invalida');
        }
        [$kind, $number] = explode(':', $selection, 2);
        $event = $kind === 'event' ? Event::query()->with(['activity.activityType','activity.campaign','eventStatus'])->findOrFail((int) $number) : null;
        $activity = $event?->activity ?: Activity::query()->with(['activityType','campaign','pboExport'])->findOrFail((int) $number);
        if (! $this->isOperation($activity)) {
            throw new MissionControlException('Solo pueden lanzarse actividades de tipo OPERACIÓN.', 'tipo_invalido');
        }
        if ($event && ! in_array($event->eventStatus?->name, ['ACTIVO', 'FINALIZADO'], true)) {
            throw new MissionControlException('El evento no está publicado.', 'estado_invalido');
        }
        $payload = [
            'actividad' => ['id' => (int) $activity->id, 'nombre' => $activity->name],
            'parar_academias' => $stopAcademies,
            'actor' => ['id' => $actorId, 'nombre' => $actorName],
        ];
        if ($event) {
            $payload['evento'] = ['id' => (int) $event->id, 'nombre' => $event->name, 'fecha' => $event->date?->toIso8601String()];
        }
        if ($activity->campaign_id) {
            // Mission Control owns the campaign's mission, addons and saved ALiVE progress.
            $payload['campana'] = ['id' => (int) $activity->campaign_id, 'nombre' => (string) $activity->campaign?->name];
            return $payload;
        }
        $pbo = PublicActivityPbo::forActivity($activity);
        if (! $pbo['pbo_subido'] || ! $pbo['pbo_sincronizado_windows'] || ! $pbo['pbo_nombre']) {
            throw new MissionControlException('El PBO todavía no está sincronizado con Windows.', 'pbo_no_sincronizado');
        }
        $addonIds = collect($activity->addons['addon_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->all();
        if (! $addonIds) {
            throw new MissionControlException('Selecciona los addons de la actividad desde Filament.', 'faltan_addons');
        }
        $addonNames = \App\Models\Addon::query()->whereIn('id', $addonIds)->orderBy('name')->pluck('name')->all();
        if (count($addonNames) !== count($addonIds)) {
            throw new MissionControlException('Alguno de los addons configurados ya no existe.', 'faltan_addons');
        }
        $payload['pbo'] = ['nombre' => $pbo['pbo_nombre']];
        if ((int) $activity->pbo_size_bytes > 0) { $payload['pbo']['tamano'] = (int) $activity->pbo_size_bytes; }
        if (filled($activity->pboExport?->pbo_sha256)) { $payload['pbo']['sha256'] = $activity->pboExport->pbo_sha256; }
        $payload['addons'] = $addonNames;
        if ($event?->multiclans) { $payload['perfil'] = 'multiclanes'; }

        return $payload;
    }
}
