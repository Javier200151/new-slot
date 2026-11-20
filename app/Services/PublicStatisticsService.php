<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Recuentos públicos a partir de asignaciones existentes e historial de movimientos. */
class PublicStatisticsService
{
    public const PERIODS = [
        '1m' => 'Último mes',
        '3m' => 'Últimos 3 meses',
        '6m' => 'Últimos 6 meses',
        '12m' => 'Últimos 12 meses',
        'year' => 'Este año',
        'all' => 'Histórico completo',
        'custom' => 'Fechas personalizadas',
    ];

    public const FOCUSES = [
        'role' => 'Tipo de slot',
        'activity_type' => 'Tipo de actividad',
        'editor' => 'Editor',
        'user' => 'Jugador',
    ];

    public const METRICS = [
        'participations' => 'Participaciones en eventos',
        'events' => 'Eventos celebrados',
        'missions' => 'Eventos distintos (por actividad)',
    ];

    public const GROUPS = [
        'user' => 'Jugador',
        'role' => 'Tipo de slot',
        'month' => 'Mes',
        'editor' => 'Editor',
        'platform' => 'Plataforma',
        'activity_type' => 'Tipo de actividad',
        'map' => 'Mapa',
        'campaign' => 'Campaña',
    ];

    public static function allowedGroups(string $metric): array
    {
        return match ($metric) {
            'events', 'missions' => ['editor', 'month', 'platform', 'activity_type', 'map', 'campaign'],
            default => array_keys(self::GROUPS),
        };
    }

    public function normalized(array $raw): array
    {
        // Enlaces existentes sin «focus» siguen abriendo la categoría apropiada.
        $focus = array_key_exists($raw['focus'] ?? '', self::FOCUSES)
            ? $raw['focus']
            : (($raw['user'] ?? 0) > 0
                ? 'user'
                : (($raw['role'] ?? 0) > 0 || ($raw['group'] ?? null) === 'role'
                    ? 'role'
                    : (($raw['activity_type'] ?? 0) > 0 || ($raw['group'] ?? null) === 'activity_type'
                        ? 'activity_type'
                        : (($raw['editor'] ?? 0) > 0 || ($raw['group'] ?? null) === 'editor'
                            || ($raw['metric'] ?? null) === 'missions'
                            ? 'editor' : 'user'))));
        $defaultMetric = match ($focus) {
            'activity_type' => 'events',
            'editor' => 'missions',
            default => 'participations',
        };
        $metric = array_key_exists($raw['metric'] ?? '', self::METRICS)
            ? $raw['metric'] : $defaultMetric;
        $defaultGroup = match (true) {
            $metric !== 'participations' && $focus === 'activity_type' => 'activity_type',
            $metric !== 'participations' => 'editor',
            $focus === 'editor' => 'editor',
            $focus === 'activity_type' => 'user',
            default => 'user',
        };
        $group = in_array(($raw['group'] ?? null), self::allowedGroups($metric), true)
            ? $raw['group'] : $defaultGroup;
        $requestedTop = (int) ($raw['top'] ?? 10);

        return [
            'focus' => $focus,
            'metric' => $metric,
            'group' => $group,
            'period' => array_key_exists($raw['period'] ?? '', self::PERIODS) ? $raw['period'] : '12m',
            'from' => $raw['from'] ?? null,
            'to' => $raw['to'] ?? null,
            'platform' => (int) ($raw['platform'] ?? 0),
            'activity_type' => (int) ($raw['activity_type'] ?? 0),
            'map' => (int) ($raw['map'] ?? 0),
            'campaign' => (int) ($raw['campaign'] ?? 0),
            'role' => (int) ($raw['role'] ?? 0),
            'user' => (int) ($raw['user'] ?? 0),
            'editor' => (int) ($raw['editor'] ?? 0),
            'status' => (int) ($raw['status'] ?? 0),
            'top' => in_array($requestedTop, [5, 10, 20, 50, 100], true) ? $requestedTop : 10,
            'sort' => ($raw['sort'] ?? '') === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** Solo un registro por persona y evento. La asignación actual prevalece sobre el historial. */
    protected function assignments(): Builder
    {
        $current = DB::table('event_slots as s')
            ->select(['s.event_id', 's.user_id', 's.slot_type_id'])
            ->whereNotNull('s.user_id');

        $history = DB::table('event_slot_history as h')
            ->selectRaw('h.event_id, h.user_id, h.to_slot_type_id AS slot_type_id')
            ->whereNotNull('h.event_id')
            ->whereNotNull('h.user_id')
            ->whereIn('h.action', ['assigned', 'moved'])
            ->whereNotNull('h.to_slot_key')
            ->whereRaw('h.id = (SELECT h2.id FROM event_slot_history h2 WHERE h2.event_id = h.event_id AND h2.user_id = h.user_id ORDER BY h2.created_at DESC, h2.id DESC LIMIT 1)')
            ->whereNotExists(function (Builder $q): void {
                $q->selectRaw('1')->from('event_slots as active_slot')
                    ->whereColumn('active_slot.event_id', 'h.event_id')
                    ->whereColumn('active_slot.user_id', 'h.user_id');
            });

        return DB::query()->fromSub($current->unionAll($history), 'participation');
    }

    protected function dateScope(Builder $query, array $filters): void
    {
        $today = CarbonImmutable::now();
        switch ($filters['period']) {
            case '1m': $query->where('e.date', '>=', $today->subMonth()->startOfDay()); break;
            case '3m': $query->where('e.date', '>=', $today->subMonths(3)->startOfDay()); break;
            case '6m': $query->where('e.date', '>=', $today->subMonths(6)->startOfDay()); break;
            case '12m': $query->where('e.date', '>=', $today->subYear()->startOfDay()); break;
            case 'year': $query->where('e.date', '>=', $today->startOfYear()); break;
            case 'custom':
                if (! empty($filters['from'])) {
                    $query->where('e.date', '>=', $filters['from'].' 00:00:00');
                }
                if (! empty($filters['to'])) {
                    $query->where('e.date', '<=', $filters['to'].' 23:59:59');
                }
                break;
        }
    }

    protected function events(array $filters): Builder
    {
        $query = DB::table('events as e')
            ->join('activities as a', 'a.id', '=', 'e.activity_id')
            ->join('event_status as es', 'es.id', '=', 'e.event_status_id')
            ->leftJoin('users as editor', 'editor.id', '=', 'a.editor_id')
            ->leftJoin('platforms as p', 'p.id', '=', 'a.platform_id')
            ->leftJoin('activity_types as atype', 'atype.id', '=', 'a.activity_type_id')
            ->leftJoin('maps as game_map', 'game_map.id', '=', 'a.map_id')
            ->leftJoin('campaign as camp', 'camp.id', '=', 'a.campaign_id')
            ->whereNull('e.deleted_at')
            ->whereNull('a.deleted_at')
            ->where('e.date', '<=', now())
            ->whereRaw("UPPER(TRIM(es.name)) IN ('ACTIVO', 'FINALIZADO')");

        $this->dateScope($query, $filters);
        foreach (['platform' => 'a.platform_id', 'activity_type' => 'a.activity_type_id', 'editor' => 'a.editor_id', 'map' => 'a.map_id', 'campaign' => 'a.campaign_id'] as $key => $column) {
            if (($filters[$key] ?? 0) > 0) {
                $query->where($column, $filters[$key]);
            }
        }
        return $query;
    }

    protected function participantQuery(array $filters): Builder
    {
        $query = $this->events($filters)
            ->joinSub($this->assignments(), 'part', fn ($join) => $join->on('part.event_id', '=', 'e.id'))
            ->join('users as u', 'u.id', '=', 'part.user_id')
            ->leftJoin('slot_types as st', 'st.id', '=', 'part.slot_type_id')
            // Los usuarios eliminados no pueden identificarse por un ranking público.
            ->whereNull('u.deleted_at')
            ->whereNotNull('u.promo_id');

        if (($filters['user'] ?? 0) > 0) $query->where('u.id', $filters['user']);
        if (($filters['status'] ?? 0) > 0) $query->where('u.status_id', $filters['status']);
        if (($filters['role'] ?? 0) > 0) $query->where('part.slot_type_id', $filters['role']);

        return $query;
    }

    /** Los filtros por jugador, tipo de slot o estado también pueden acotar eventos y actividades. */
    protected function eventQuery(array $filters): Builder
    {
        $query = $this->events($filters);
        if (($filters['user'] ?? 0) > 0 || ($filters['role'] ?? 0) > 0 || ($filters['status'] ?? 0) > 0) {
            $query->whereExists(function (Builder $exists) use ($filters): void {
                $exists->selectRaw('1')
                    ->fromSub($this->assignments(), 'part_filter')
                    ->join('users as member_filter', 'member_filter.id', '=', 'part_filter.user_id')
                    ->whereColumn('part_filter.event_id', 'e.id')
                    ->whereNull('member_filter.deleted_at')
                    ->whereNotNull('member_filter.promo_id');
                if ($filters['user'] > 0) $exists->where('part_filter.user_id', $filters['user']);
                if ($filters['role'] > 0) $exists->where('part_filter.slot_type_id', $filters['role']);
                if ($filters['status'] > 0) $exists->where('member_filter.status_id', $filters['status']);
            });
        }
        return $query;
    }

    /** @return array<int, array{label:string,total:int,id:int|null,url:string|null}> */
    public function ranking(array $filters): array
    {
        $metric = $filters['metric'];
        $query = $metric === 'participations'
            ? $this->participantQuery($filters)
            : $this->eventQuery($filters);

        [$labelExpr, $idExpr] = match ($filters['group']) {
            'user' => ['u.nick', 'u.id'],
            'role' => ["COALESCE(st.name, 'Sin tipo de slot')", 'part.slot_type_id'],
            'month' => ["DATE_FORMAT(e.date, '%Y-%m')", "DATE_FORMAT(e.date, '%Y-%m')"],
            'editor' => ["CASE WHEN editor.deleted_at IS NULL AND editor.promo_id IS NOT NULL THEN editor.nick ELSE 'Sin editor público' END", "CASE WHEN editor.deleted_at IS NULL AND editor.promo_id IS NOT NULL THEN editor.id ELSE 0 END"],
            'platform' => ["COALESCE(p.name, 'Sin plataforma')", 'a.platform_id'],
            'map' => ["COALESCE(game_map.name, 'Sin mapa')", 'a.map_id'],
            'campaign' => ["COALESCE(camp.name, 'Sin campaña')", 'a.campaign_id'],
            default => ["COALESCE(atype.name, 'Sin tipo')", 'a.activity_type_id'],
        };
        $distinct = $metric === 'missions' ? 'a.id' : ($metric === 'events' ? 'e.id' : "CONCAT(part.user_id, ':', e.id)");

        $query->selectRaw("{$labelExpr} AS group_label, {$idExpr} AS group_id, COUNT(DISTINCT {$distinct}) AS total")
            ->groupByRaw("{$labelExpr}, {$idExpr}");

        if ($filters['group'] === 'month') {
            // Los meses se ordenan cronológicamente y se muestran los más recientes.
            $query->orderBy('group_label', 'desc');
        } else {
            $query->orderBy('total', $filters['sort'])->orderBy('group_label');
        }
        $query->limit($filters['top']);

        $results = $query->get();
        if ($filters['group'] === 'month') {
            $results = $results->reverse()->values();
        }

        return $results->map(function ($row) use ($filters): array {
            $id = is_numeric($row->group_id) ? (int) $row->group_id : null;
            $url = null;
            if (in_array($filters['group'], ['user', 'editor'], true) && $id > 0) {
                $url = route('users.show', ['user' => (string) $row->group_label]);
            } elseif ($filters['metric'] === 'participations' && $filters['group'] === 'role' && $id > 0) {
                // Cada tipo de slot abre su propio ranking de jugadores, conservando
                // el periodo y el resto de filtros activos del explorador.
                $next = array_filter([
                    'focus' => 'role',
                    'metric' => 'participations',
                    'group' => 'user',
                    'period' => $filters['period'],
                    'from' => $filters['period'] === 'custom' ? $filters['from'] : null,
                    'to' => $filters['period'] === 'custom' ? $filters['to'] : null,
                    'platform' => $filters['platform'],
                    'activity_type' => $filters['activity_type'],
                    'map' => $filters['map'],
                    'campaign' => $filters['campaign'],
                    'role' => $id,
                    'user' => $filters['user'],
                    'editor' => $filters['editor'],
                    'status' => $filters['status'],
                    'top' => $filters['top'],
                    'sort' => 'desc',
                ], static fn ($value): bool => $value !== null && $value !== 0 && $value !== '');
                $url = route('statistics.index', $next);
            }
            return [
                'label' => (string) ($row->group_label ?: 'Sin identificar'),
                'total' => (int) $row->total,
                'id' => $id,
                'url' => $url,
            ];
        })->all();
    }

    /** Estadísticas históricas individuales; los filtros solo afectan al desglose inferior. */
    public function profile(User $user, array $filters): array
    {
        $base = $this->normalized(['period' => 'all', 'user' => $user->getKey(), 'top' => 100]);
        $total = (int) $this->participantQuery($base)->distinct()->count('e.id');
        $lastYear = (int) $this->participantQuery(array_replace($base, ['period' => '12m']))
            ->distinct()->count('e.id');
        $roleRows = $this->participantQuery($base)
            ->whereNotNull('part.slot_type_id')
            ->selectRaw('st.name AS label, COUNT(DISTINCT e.id) AS total')
            ->groupBy('part.slot_type_id', 'st.name')
            ->orderByDesc('total')->orderBy('st.name')->get();

        $filtered = array_replace($base, [
            'period' => $filters['period'],
            'from' => $filters['from'] ?? null,
            'to' => $filters['to'] ?? null,
            'platform' => $filters['platform'],
            'activity_type' => $filters['activity_type'],
            'map' => $filters['map'],
            'campaign' => $filters['campaign'],
            'role' => $filters['role'],
        ]);

        $byRole = $this->participantQuery($filtered)
            ->selectRaw("COALESCE(st.name, 'Sin tipo de slot') AS label, COUNT(DISTINCT e.id) AS total")
            ->groupBy('part.slot_type_id', 'st.name')
            ->orderByDesc('total')->orderBy('label')->limit(8)->get();

        $recentEvents = $this->participantQuery($filtered)
            ->select(['e.id', 'e.name', 'e.date', 'a.name as activity_name', 'st.name as role_name'])
            ->orderByDesc('e.date')->orderByDesc('e.id')->limit(12)->get();

        return [
            'total' => $total,
            'last_year' => $lastYear,
            'favorite_role' => $roleRows->first()?->label,
            'favorite_role_count' => (int) ($roleRows->first()?->total ?? 0),
            'different_roles' => $roleRows->count(),
            'filtered_total' => (int) $this->participantQuery($filtered)->distinct()->count('e.id'),
            'by_role' => $byRole,
            'recent_events' => $recentEvents,
        ];
    }
}
