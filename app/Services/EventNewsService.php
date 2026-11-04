<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventSlotHistory;
use App\Models\User;
use App\Support\EventUnreadActivity;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventNewsService
{
    public const RETENTION_DAYS = 14;

    /**
     * Conservar el resumen de los cambios de estructura del ORBAT antes de que
     * la visita actual sustituya la fotografía anterior. Los comentarios y
     * las inscripciones se consultan desde sus propios registros históricos.
     *
     * @return array<int, array{text:string,at:string}>
     */
    public function rememberedStructure(Event $event, ?object $receipt): array
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $saved = $this->savedStructure($receipt)
            ->filter(fn (array $item): bool => Carbon::parse($item['at'])->greaterThanOrEqualTo($cutoff))
            ->all();

        $new = $this->structureChanges($event, $receipt);
        foreach ($new as $item) {
            $key = $item['text'] . '|' . $item['at'];
            $alreadyStored = collect($saved)->contains(fn (array $old): bool => $old['text'] . '|' . $old['at'] === $key);
            if (! $alreadyStored) {
                $saved[] = $item;
            }
        }

        return array_values($saved);
    }

    /**
     * @return array<int, array{text:string,at:string}>
     */
    public function structureChanges(Event $event, ?object $receipt): array
    {
        if (! $receipt || ! $event->updated_at || $event->updated_at->lt(now()->subDays(self::RETENTION_DAYS))) {
            return [];
        }

        $previous = $receipt->orbat_slot_snapshot
            ? (array) json_decode((string) $receipt->orbat_slot_snapshot, true)
            : [];
        $current = EventUnreadActivity::slotSnapshot($event);
        $messages = [];
        $at = $event->updated_at->toDateTimeString();
        $newKeys = $previous !== [] ? array_diff(array_keys($current), array_keys($previous)) : [];

        if ($newKeys !== []) {
            $newSlots = array_intersect_key($current, array_flip($newKeys));
            $previousGroups = array_unique(array_column($previous, 'group'));
            $newGroups = array_unique(array_diff(array_column($newSlots, 'group'), $previousGroups));

            foreach ($newGroups as $group) {
                $messages[] = ['text' => 'Se ha añadido la escuadra «' . $group . '» al ORBAT.', 'at' => $at];
            }

            $names = [];
            foreach ($newSlots as $slot) {
                if (! in_array($slot['group'], $newGroups, true)) {
                    $names[] = $slot['slot'] . ' (' . $slot['group'] . ')';
                }
            }
            if ($names !== []) {
                $messages[] = [
                    'text' => 'Se han añadido puestos al ORBAT: ' . implode(', ', array_slice($names, 0, 8))
                        . (count($names) > 8 ? ' y más.' : '.'),
                    'at' => $at,
                ];
            }
        } elseif ($event->getOrbatSlotsCount() > (int) $receipt->visible_slot_count) {
            $difference = $event->getOrbatSlotsCount() - (int) $receipt->visible_slot_count;
            $messages[] = ['text' => "Se han añadido {$difference} puestos o escuadras al ORBAT.", 'at' => $at];
        }

        return $messages;
    }

    /**
     * @return Collection<int, array{text:string,at:string}>
     */
    private function savedStructure(?object $receipt): Collection
    {
        $raw = $receipt?->event_news_structure
            ? json_decode((string) $receipt->event_news_structure, true)
            : [];

        return collect(is_array($raw) ? $raw : [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['text'] ?? null) && filled($item['at'] ?? null))
            ->map(fn (array $item): array => ['text' => (string) $item['text'], 'at' => (string) $item['at']])
            ->values();
    }

    /**
     * Muestra lo sucedido en las últimas dos semanas, tanto pendiente como leído.
     * La última visita indica el estado de cada novedad, no su visibilidad.
     *
     * @return Collection<int, array{event:Event,messages:array,joins:array,join_count:int,own_squad_join_count:int,unread:bool,latest:string}>
     */
    public function forUser(User $user): Collection
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $events = Event::query()
            ->whereHas('eventStatus', fn ($query) => $query->where('name', 'ACTIVO'))
            ->with(['eventStatus', 'activity.activityType', 'activity.platform', 'slots:id,event_id,slot_key,user_id,ally_id,ally_nickname,name,slot_group'])
            ->orderByDesc('date')
            ->get();

        if ($events->isEmpty()) {
            return collect();
        }

        $receipts = DB::table('event_view_receipts')
            ->where('user_id', $user->id)
            ->whereIn('event_id', $events->pluck('id')->all())
            ->get()->keyBy('event_id');

        return $events->map(function (Event $event) use ($receipts, $user, $cutoff): ?array {
            $receipt = $receipts->get($event->id);
            $since = $receipt?->last_viewed_at;
            $messages = [];
            $latest = '';

            // Persistidos al abrir un evento. No desaparecen al marcarlo leído.
            foreach ($this->savedStructure($receipt) as $item) {
                if (Carbon::parse($item['at'])->lt($cutoff)) {
                    continue;
                }
                $messages[] = [
                    'text' => $item['text'],
                    'read' => true,
                    'at' => $item['at'],
                ];
                $latest = max($latest, $item['at']);
            }

            // Novedades del ORBAT aún no visitadas, calculadas contra la
            // última fotografía. Se guardarán cuando se abra el evento.
            foreach ($this->structureChanges($event, $receipt) as $item) {
                if (collect($messages)->contains(fn (array $old): bool => $old['text'] === $item['text'] && $old['at'] === $item['at'])) {
                    continue;
                }
                $messages[] = ['text' => $item['text'], 'read' => false, 'at' => $item['at']];
                $latest = max($latest, $item['at']);
            }

            $comments = EventComment::query()
                ->where('event_id', $event->id)
                ->where('created_at', '>=', $cutoff)
                ->with(['user', 'parent.user'])
                ->orderByDesc('created_at')->orderByDesc('id')->limit(35)->get();

            foreach ($comments as $comment) {
                $author = $comment->user?->nick ?? 'Un miembro';
                $preview = Str::limit(trim(strip_tags(preg_replace('/\\[[^\\]]+\\]/u', '', (string) $comment->comment) ?? '')), 160);
                if ($comment->parent_id) {
                    $to = $comment->parent?->user?->nick ?? 'otro miembro';
                    $message = "{$author} ha respondido a {$to}: «{$preview}»";
                } else {
                    $message = "{$author} ha comentado: «{$preview}»";
                }

                $at = $comment->created_at->toDateTimeString();
                $messages[] = ['text' => $message, 'read' => $since !== null && $at <= $since, 'at' => $at];
                $latest = max($latest, $at);
            }

            // Las incorporaciones se basan en registros históricos reales,
            // no en una comparación de números que pudiera contar movimientos.
            $assignments = EventSlotHistory::query()
                ->where('event_id', $event->id)
                ->where('action', 'assigned')
                ->where('created_at', '>=', $cutoff)
                ->where(fn ($query) => $query->whereNotNull('user_id')->orWhereNotNull('ally_id'))
                ->with(['user', 'ally'])
                ->orderByDesc('created_at')->orderByDesc('id')->get();

            $currentUsers = $event->slots
                ->filter(fn ($slot) => $slot->user_id !== null)
                ->keyBy('user_id');
            $currentAllies = $event->slots
                ->filter(fn ($slot) => $slot->ally_id !== null)
                ->keyBy('id');
            $ownSquad = trim((string) ($currentUsers->get($user->id)?->slot_group ?? ''));
            $joined = [];

            foreach ($assignments as $assignment) {
                if ($assignment->user_id !== null) {
                    $key = 'user:' . $assignment->user_id;
                    $current = $currentUsers->get($assignment->user_id);
                    $name = $assignment->user?->nick ?? 'Miembro';
                } else {
                    $key = 'ally-slot:' . ($assignment->event_slot_id ?: $assignment->id);
                    $current = $currentAllies->get($assignment->event_slot_id);
                    $name = trim((string) ($current?->ally_nickname ?: $assignment->ally?->name ?: 'Aliado'));
                }

                // Las inscripciones retiradas no se presentan como vigentes.
                if (isset($joined[$key]) || ! $current) {
                    continue;
                }

                $group = trim((string) ($current->slot_group ?: $assignment->to_slot_group ?: ''));
                $at = $assignment->created_at->toDateTimeString();
                $joined[$key] = [
                    'member' => $name,
                    'slot' => trim((string) ($current->name ?: $assignment->to_slot_name ?: 'Puesto')),
                    'group' => $group,
                    'in_own_squad' => $ownSquad !== '' && $group !== ''
                        && mb_strtolower($ownSquad) === mb_strtolower($group),
                    'read' => $since !== null && $at <= $since,
                    'at' => $at,
                ];
                $latest = max($latest, $at);
            }

            if ($messages === [] && $joined === []) {
                return null;
            }

            // Pendientes en primer lugar, incluso dentro de la misma tarjeta.
            usort($messages, fn (array $a, array $b): int => ((int) $a['read'] <=> (int) $b['read'])
                ?: strcmp($b['at'], $a['at']));
            $joins = array_values($joined);
            usort($joins, fn (array $a, array $b): int => ((int) $a['read'] <=> (int) $b['read'])
                ?: strcmp($b['at'], $a['at']));

            $unread = collect($messages)->contains(fn (array $item): bool => ! $item['read'])
                || collect($joins)->contains(fn (array $item): bool => ! $item['read']);

            return [
                'event' => $event,
                'messages' => $messages,
                'joins' => $joins,
                'join_count' => count($joins),
                'unread_join_count' => count(array_filter($joins, fn (array $join): bool => ! $join['read'])),
                'own_squad_join_count' => count(array_filter($joins, fn (array $join): bool => $join['in_own_squad'])),
                'unread' => $unread,
                'latest' => $latest,
            ];
        })
            ->filter()
            ->sort(fn (array $a, array $b): int => ((int) $b['unread'] <=> (int) $a['unread'])
                ?: strcmp($b['latest'], $a['latest']))
            ->values();
    }
}
