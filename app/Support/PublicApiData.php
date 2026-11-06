<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Campaign;
use App\Models\CommunityClip;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\SqaGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Public API field allowlists. No complete Eloquent model is ever serialized. */
final class PublicApiData
{
    public const EVENT_STATES = ['ACTIVO', 'FINALIZADO', 'CANCELADO'];

    public static function events(): Builder
    {
        return Event::query()->whereHas('activity')->whereHas('eventStatus', fn (Builder $q) =>
            $q->whereIn('name', self::EVENT_STATES));
    }

    public static function activities(): Builder
    {
        // A template/draft without a published event is not a public API activity.
        return Activity::query()->whereHas('events', fn (Builder $q) =>
            $q->whereHas('eventStatus', fn (Builder $s) => $s->whereIn('name', self::EVENT_STATES)));
    }

    public static function campaigns(): Builder
    {
        return Campaign::query()->whereHas('events', fn (Builder $q) =>
            $q->whereHas('eventStatus', fn (Builder $s) => $s->whereIn('name', self::EVENT_STATES)));
    }

    public static function users(): Builder
    {
        return User::query()->whereNotNull('promo_id');
    }

    public static function groups(): Builder
    {
        return SqaGroup::query()->where('show_in_organization', true);
    }

    public static function clips(): Builder
    {
        return CommunityClip::query()->where('is_visible', true)
            ->where(fn (Builder $q) => $q->whereNull('event_id')->orWhereHas('event', fn (Builder $e) =>
                $e->whereHas('eventStatus', fn (Builder $st) => $st->whereIn('name', self::EVENT_STATES))));
    }

    public static function filterEvents(Builder $query, array $f): Builder
    {
        if (isset($f['estado'])) {
            $query->whereHas('eventStatus', fn (Builder $q) => $q->where('name', $f['estado']));
        }
        if (isset($f['multiclan'])) $query->where('multiclans', (bool) $f['multiclan']);
        if (isset($f['actividad'])) $query->where('activity_id', (int) $f['actividad']);
        if (isset($f['tipo_actividad'])) $query->whereHas('activity', fn (Builder $q) => $q->where('activity_type_id', $f['tipo_actividad']));
        if (isset($f['campana'])) $query->whereHas('activity', fn (Builder $q) => $q->where('campaign_id', $f['campana']));
        if (isset($f['mapa'])) $query->whereHas('activity', fn (Builder $q) => $q->where('map_id', $f['mapa']));
        if (isset($f['plataforma'])) $query->whereHas('activity', fn (Builder $q) => $q->where('platform_id', $f['plataforma']));
        if (isset($f['futuros']) && (bool) $f['futuros']) $query->where('date', '>=', now());
        if (isset($f['desde'])) $query->whereDate('date', '>=', $f['desde']);
        if (isset($f['hasta'])) $query->whereDate('date', '<=', $f['hasta']);
        if (isset($f['q'])) $query->where('name', 'like', '%' . self::like($f['q']) . '%');
        return $query;
    }

    public static function eventRules(): array
    {
        return [
            'estado' => 'sometimes|in:ACTIVO,FINALIZADO,CANCELADO',
            'multiclan' => 'sometimes|in:0,1',
            'tipo_actividad' => 'sometimes|integer|min:1',
            'actividad' => 'sometimes|integer|min:1',
            'futuros' => 'sometimes|in:0,1',
            'desde' => 'sometimes|date_format:Y-m-d',
            'hasta' => 'sometimes|date_format:Y-m-d|after_or_equal:desde',
            'campana' => 'sometimes|integer|min:1',
            'mapa' => 'sometimes|integer|min:1',
            'plataforma' => 'sometimes|integer|min:1',
            'q' => 'sometimes|string|max:50',
            ...self::pageRules(),
        ];
    }

    public static function pageRules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'orden' => 'sometimes|in:fecha,nombre,nick,recientes',
            'direccion' => 'sometimes|in:asc,desc',
        ];
    }

    public static function pagination(Request $request, Builder $query, callable $serialize): JsonResponse
    {
        $size = (int) $request->input('per_page', 20);
        $size = max(1, min(100, $size));
        $page = $query->paginate($size);
        return response()->json([
            'data' => $page->getCollection()->map($serialize)->values()->all(),
            'meta' => ['pagina_actual' => $page->currentPage(), 'por_pagina' => $page->perPage(), 'total' => $page->total()],
            'links' => ['anterior' => $page->previousPageUrl(), 'siguiente' => $page->nextPageUrl()],
        ]);
    }

    public static function collection(iterable $items, callable $serialize): JsonResponse
    {
        return response()->json(['data' => collect($items)->map($serialize)->values()->all()]);
    }

    public static function one(array $item): JsonResponse
    {
        return response()->json(['data' => $item]);
    }

    public static function order(Builder $query, array $f, array $allowed, string $fallback, string $direction = 'desc'): Builder
    {
        $key = (string) ($f['orden'] ?? '');
        $column = $allowed[$key] ?? $fallback;
        $dir = (string) ($f['direccion'] ?? $direction);
        $query->orderBy($column, $dir)->orderBy('id', $dir);
        return $query;
    }

    private static function like(string $term): string
    {
        return addcslashes(trim($term), '%_\\');
    }

    public static function text(mixed $value, int $max = 4000): ?string
    {
        if ($value === null) return null;
        if (is_array($value)) return null; // Structured editor content must never be copied raw.
        $plain = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $plain === '' ? null : Str::limit($plain, $max, '');
    }

    public static function image(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') return null;
        if (preg_match('~^https://~i', $path)) return $path;
        if (str_starts_with($path, '//') || str_contains($path, '..') || str_starts_with($path, 'data:')) return null;
        if (str_starts_with($path, '/storage/')) return url($path);
        if (str_starts_with($path, 'storage/')) return url('/'.$path);
        return Storage::disk('public')->url($path);
    }

    public static function status(?object $status): ?array
    {
        return $status ? ['id' => (int) $status->id, 'nombre' => (string) $status->name] : null;
    }

    public static function named(?object $model): ?array
    {
        return $model ? ['id' => (int) $model->id, 'nombre' => (string) $model->name] : null;
    }

    public static function event(Event $event, bool $detailed = false): array
    {
        $event->loadMissing(['eventStatus', 'activity.activityType', 'activity.platform', 'activity.map', 'activity.campaign']);
        $activity = $event->activity;
        $row = [
            'id' => (int) $event->id,
            'nombre' => (string) $event->name,
            'fecha' => $event->date?->toIso8601String(),
            'fecha_fin' => $event->end_date?->toIso8601String(),
            'estado' => self::status($event->eventStatus),
            'multiclan' => (bool) $event->multiclans,
            'actividad' => $activity ? [
                'id' => (int) $activity->id,
                'nombre' => (string) $activity->name,
                'tipo' => self::named($activity->activityType),
            ] : null,
            'imagen_url' => self::image($activity?->image),
            'url' => url('/eventos/'.$event->id),
        ];
        if ($detailed) {
            $row['descripcion_publica'] = self::text($event->briefing_extra, 6000); // No private activity briefing.
            $row['campana'] = self::named($activity?->campaign);
            $row['mapa'] = self::named($activity?->map);
            $row['orbat_resumen'] = self::orbat($event)['resumen'];
        }
        return $row;
    }

    /** Reconstruct only the groups/slots that are actually visible on the public event page. */
    public static function orbat(Event $event): array
    {
        $event->loadMissing(['activity.activityType', 'slots.user', 'slots.ally']);
        if ($event->activity?->activityType && ! $event->activity->activityType->usesOrbat()) {
            return ['evento_id' => (int) $event->id, 'grupos_visibles' => [], 'resumen' => ['grupos' => 0, 'slots' => 0, 'ocupados' => 0, 'libres' => 0]];
        }
        $groups = collect($event->orbat['groups'] ?? [])
            ->filter(fn ($g) => is_array($g) && (bool) ($g['visible'] ?? true));
        $factionNames = \App\Models\Faction::query()->whereIn('id', $groups->pluck('faction_id')->filter()->all())->pluck('name', 'id');
        $typeIds = $groups->flatMap(fn ($g) => $g['slots'] ?? [])->pluck('slot_type_id')->filter()->unique();
        $typeNames = \App\Models\SlotType::query()->whereIn('id', $typeIds)->pluck('name', 'id');
        $assignments = $event->slots;
        $used = [];
        $occupied = 0;
        $total = 0;
        $serialized = [];
        foreach ($groups as $g) {
            $name = (string) ($g['name'] ?? '');
            $slots = [];
            foreach ($g['slots'] ?? [] as $slot) {
                if (! is_array($slot) || ! (bool) ($slot['visible'] ?? true)) continue;
                $total++;
                $key = trim((string) ($slot['slot_key'] ?? ''));
                $assignment = $assignments->first(function (EventSlot $s) use ($key, $slot, $g, $name, $used): bool {
                    if (isset($used[$s->id])) return false;
                    if ($key !== '') return (string) $s->slot_key === $key;
                    return (string) $s->slot_group === $name
                        && (string) $s->name === (string) ($slot['name'] ?? '')
                        && (int) $s->slot_type_id === (int) ($slot['slot_type_id'] ?? 0)
                        && (int) $s->faction_id === (int) ($g['faction_id'] ?? 0);
                });
                if ($assignment) $used[$assignment->id] = true;
                $nick = $assignment?->user?->nick
                    ?? ($assignment?->ally_id ? ($assignment?->ally_nickname ?: $assignment?->ally?->name) : null);
                if ($nick) $occupied++;
                $slots[] = [
                    'nombre' => (string) ($slot['name'] ?? ''),
                    'tipo_slot' => ! empty($slot['slot_type_id']) ? ['nombre' => $typeNames[$slot['slot_type_id']] ?? null] : null,
                    'ocupado' => $nick !== null,
                    'ocupante' => $nick !== null ? [
                        'nick' => (string) $nick,
                        'clan' => $assignment?->ally_id ? $assignment?->ally?->name : 'Squad ALPHA',
                    ] : null,
                ];
            }
            $serialized[] = [
                'nombre' => $name,
                'faccion' => ! empty($g['faction_id']) ? ['nombre' => $factionNames[$g['faction_id']] ?? null] : null,
                'slots' => $slots,
            ];
        }
        return [
            'evento_id' => (int) $event->id,
            'grupos_visibles' => $serialized,
            'resumen' => [
                'grupos' => count($serialized), 'slots' => $total,
                'ocupados' => $occupied, 'libres' => $total - $occupied,
            ],
        ];
    }

    /** Public signature links. Never generate or repair banners during API reads. */
    public static function signature(User $user): array
    {
        $nick = (string) $user->nick;
        // The signature generator writes the PNG using the member's exact nickname.
        // Refuse directory separators as a precaution when inspecting the local file.
        $safeNick = $nick !== '' && ! str_contains($nick, '/') && ! str_contains($nick, chr(92));
        $bannerPath = $safeNick ? storage_path('app/public/firmas/'.$nick.'.png') : null;

        return [
            'banner_url' => $bannerPath && is_file($bannerPath) && is_readable($bannerPath)
                ? url('/storage/firmas/'.rawurlencode($nick).'.png')
                : null,
            'completa_url' => $safeNick ? $user->getSignatureUrl() : null,
        ];
    }

    public static function member(User $user, bool $detailed = false): array
    {
        $user->loadMissing(['status', 'mainSqaGroup', 'promo']);
        $group = $user->mainSqaGroup;
        $row = [
            'nick' => (string) $user->nick,
            'steamid64' => $user->steam_id ? (string) $user->steam_id : null,
            'estado' => $user->status ? ['nombre' => (string) $user->status->name, 'color' => (string) $user->status->color] : null,
            'promocion' => $user->promo ? ['id' => (int) $user->promo->id, 'nombre' => (string) $user->promo->id] : null,
            'grupo_principal' => $group && $group->show_in_organization ? ['siglas' => $group->name, 'nombre' => $group->large_name ?: $group->name] : null,
            'avatar_url' => self::image($user->image),
            'firma' => self::signature($user),
            'url' => url('/usuarios/'.rawurlencode((string) $user->nick)),
        ];
        if ($detailed) {
            $user->loadMissing(['sqaGroups', 'metopas']);
            $row['nombre_mostrado'] = (string) $user->nick;
            $row['estado_publico'] = $user->status?->name;
            $row['grupos_publicos'] = $user->sqaGroups->filter(fn ($g) => (bool) $g->show_in_organization)->map(fn ($g) => ['siglas' => $g->name, 'nombre' => $g->large_name ?: $g->name])->values()->all();
            $row['miembro_desde'] = $user->member_at?->toDateString();
            $row['metopas_publicas'] = $user->metopas->map(fn ($m) => ['id' => $m->id, 'nombre' => $m->name, 'imagen_url' => self::image($m->image), 'url' => url('/metopas/'.rawurlencode((string) $m->name))])->values()->all();
        }
        return $row;
    }

    private static function activityDescription(Activity $activity, int $limit): ?string
    {
        // The editor stores JSON, not prose. Only expose explicit sections shown
        // on the public activity page; never serialize the full structured draft.
        $description = $activity->description;
        if (! is_array($description)) return null;
        $sections = $description['sections'] ?? [];
        if (! is_array($sections)) $sections = [];
        $parts = [];
        if ($sections === [] && is_string($description['content'] ?? null)) {
            $parts[] = $description['content'];
        }
        foreach ($sections as $section) {
            if (is_array($section) && is_string($section['content'] ?? null)) {
                $parts[] = $section['content'];
            }
        }
        return self::text(implode(' ', $parts), $limit);
    }

    public static function activity(Activity $activity, bool $detailed = false): array
    {
        $activity->loadMissing(['activityType','platform','map']);
        $row = [
            'id' => (int) $activity->id, 'nombre' => $activity->name,
            'resumen_publico' => self::activityDescription($activity, 350),
            'tipo' => self::named($activity->activityType),
            'plataforma' => self::named($activity->platform), 'mapa' => self::named($activity->map),
            'imagen_url' => self::image($activity->image), 'url' => url('/actividades/'.$activity->id),
        ];
        if ($detailed) {
            $row['descripcion_publica'] = self::activityDescription($activity, 5000);
            $row['facciones_publicas'] = $activity->enemyFactions()->orderBy('name')->pluck('name')->map(fn ($n) => ['nombre' => $n])->all();
        }
        return $row;
    }

    public static function aarSummary(?\App\Models\CampaignAar $aar): ?string
    {
        if (! $aar || ! $aar->isPublished() || ! $aar->is_visible) return null;
        foreach ((array) ($aar->sections ?? []) as $section) {
            if (is_array($section) && is_string($section['content'] ?? null)) {
                $text = self::text($section['content'], 750);
                if ($text !== null) return $text;
            }
        }
        return null;
    }

    public static function campaign(Campaign $campaign, bool $detailed = false): array
    {
        $row = [
            'id' => (int) $campaign->id, 'nombre' => (string) $campaign->name,
            'resumen_publico' => self::text($campaign->description, 350),
            'imagen_url' => null,
            'url' => url('/campanas/'.$campaign->id),
        ];
        if ($detailed) {
            $row['descripcion_publica'] = self::text($campaign->description, 6000);
            $row['eventos_total'] = self::events()->whereHas('activity', fn (Builder $q) => $q->where('campaign_id', $campaign->id))->count();
        }
        return $row;
    }

    public static function clip(CommunityClip $clip, bool $detailed = false): array
    {
        $clip->loadMissing('user');
        $row = [
            'id' => (int) $clip->id, 'titulo' => (string) ($clip->title ?: 'Clip'),
            'fuente' => $clip->platform, 'url_video' => $clip->url,
            'miniatura_url' => $clip->thumbnail_url, 'publicado_en' => $clip->clip_created_at?->toIso8601String(),
            'autor_nick' => $clip->user?->nick, 'evento_id' => $clip->event_id,
            'url' => url('/clips/'.$clip->id),
        ];
        if ($detailed) {
            $row['descripcion_publica'] = null;
            $row['protagonistas'] = $clip->protagonists()->whereNotNull('promo_id')->pluck('nick')->values()->all();
            $row['me_gusta'] = $clip->likes()->count();
            $row['comentarios_total'] = $clip->comments()->count();
        }
        return $row;
    }
}
