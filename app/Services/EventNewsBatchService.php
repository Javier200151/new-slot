<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Support\EventUnreadActivity;
use App\Support\EventNewsEligibility;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cada visita a Nuevos mensajes cierra la tanda de novedades pendientes.
 * Las tandas ya vistas permanecen 14 días; una publicación posterior crea
 * una tarjeta diferente incluso cuando pertenece al mismo evento.
 */
class EventNewsBatchService
{
    public function __construct(private readonly EventNewsService $news)
    {
    }

    public function hasUnread(User $user): bool
    {
        return $this->news->forUser($user)->contains(
            fn (array $update): bool => (bool) $update['unread'],
        );
    }

    /**
     * Devuelve el estado anterior a marcar las novedades como vistas.
     * Una segunda visita devolverá las mismas tarjetas atenuadas.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function open(User $user): Collection
    {
        return DB::transaction(function () use ($user): Collection {
            // Evita duplicar una tanda con dos pestañas simultáneas.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first(['id']);

            $cutoff = now()->subDays(EventNewsService::RETENTION_DAYS);
            // Los eventos finalizados se retiran exactamente 48 h después
            // del cambio de estado, aunque nadie haya visto sus novedades.
            DB::table('event_news_batches')
                ->where('user_id', $user->id)
                ->whereIn('event_id', function ($query): void {
                    $query->select('events.id')->from('events')
                        ->join('event_status', 'event_status.id', '=', 'events.event_status_id')
                        ->where('event_status.name', 'FINALIZADO')
                        ->where(function ($expired): void {
                            $expired->whereNull('events.event_news_finalized_at')
                                ->orWhere('events.event_news_finalized_at', '<=', now()->subHours(48));
                        });
                })
                ->delete();

            // El historial de tarjetas tiene una vida máxima de 14 días.
            DB::table('event_news_batches')
                ->where('user_id', $user->id)
                ->where('last_occurred_at', '<', $cutoff)
                ->delete();

            $current = $this->news->forUser($user);
            $events = $current->pluck('event')->keyBy('id');
            $batchRows = DB::table('event_news_batches')
                ->where('user_id', $user->id)
                ->where('last_occurred_at', '>=', $cutoff)
                ->orderByDesc('id')
                ->get();

            // Los eventos ACTIVO mantienen hasta 14 días de historial;
            // los que acaban de pasar a FINALIZADO conservan 48 horas.
            $missingIds = $batchRows->pluck('event_id')->unique()->diff($events->keys());
            if ($missingIds->isNotEmpty()) {
                $previousEvents = EventNewsEligibility::query(Event::query())
                    ->with(['eventStatus', 'activity.activityType', 'activity.platform'])
                    ->whereIn('id', $missingIds->all())->get();
                foreach ($previousEvents as $previousEvent) {
                    $events->put($previousEvent->id, $previousEvent);
                }
            }

            $seenKeys = [];
            foreach ($batchRows as $row) {
                $content = json_decode((string) $row->content, true) ?: [];
                foreach (array_merge($content['messages'] ?? [], $content['joins'] ?? []) as $item) {
                    if (isset($item['key'])) {
                        $seenKeys[(int) $row->event_id][(string) $item['key']] = true;
                    }
                }
            }

            $firstDisplay = [];
            $now = now();

            foreach ($current as $update) {
                /** @var Event $event */
                $event = $update['event'];
                $eventId = (int) $event->id;
                $pending = ['messages' => [], 'joins' => []];
                $previouslyViewed = ['messages' => [], 'joins' => []];

                foreach (['messages', 'joins'] as $kind) {
                    foreach ($update[$kind] as $item) {
                        $key = (string) ($item['key'] ?? '');
                        if ($key === '' || isset($seenKeys[$eventId][$key])) {
                            continue;
                        }

                        // Los elementos antiguos no se vuelven a presentar.
                        if (Carbon::parse($item['at'])->lt($cutoff)) {
                            continue;
                        }

                        $seenKeys[$eventId][$key] = true;
                        $target = $item['read'] ? 'previouslyViewed' : 'pending';
                        if ($target === 'pending') {
                            $pending[$kind][] = $item;
                        } else {
                            $previouslyViewed[$kind][] = $item;
                        }
                    }
                }

                foreach ([[$pending, true], [$previouslyViewed, false]] as [$content, $isNew]) {
                    if ($content['messages'] === [] && $content['joins'] === []) {
                        continue;
                    }

                    $times = collect(array_merge($content['messages'], $content['joins']))
                        ->pluck('at')->filter()->sort()->values();
                    $rowId = DB::table('event_news_batches')->insertGetId([
                        'user_id' => $user->id,
                        'event_id' => $eventId,
                        'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'first_occurred_at' => $times->first(),
                        'last_occurred_at' => $times->last(),
                        'viewed_at' => $isNew ? $now : ($now->copy()->subSecond()),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    // Mostrarlas como nuevas en ESTA visita aunque ya se
                    // haya registrado la lectura para la siguiente carga.
                    if ($isNew) {
                        $firstDisplay[$rowId] = true;
                    }
                }

                // Leer el resumen también limpia la estrella de /eventos.
                // Conservar la fotografía del ORBAT evita que las mismas
                // escuadras vuelvan a notificarse en la próxima visita.
                $key = ['user_id' => $user->id, 'event_id' => $eventId];
                $receipt = DB::table('event_view_receipts')->where($key)->first();
                $structure = $this->news->rememberedStructure($event, $receipt);
                DB::table('event_view_receipts')->updateOrInsert($key, [
                    'last_viewed_at' => $now,
                    'visible_slot_count' => $event->getOrbatSlotsCount(),
                    'occupied_slot_count' => $event->slots
                        ->filter(fn ($slot): bool => $slot->user_id !== null || $slot->ally_id !== null)->count(),
                    'orbat_slot_snapshot' => json_encode(EventUnreadActivity::slotSnapshot($event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'event_news_structure' => json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            // Se muestran las tandas persistidas, no una tarjeta por evento.
            // Así un evento con novedades posteriores genera otra tarjeta.
            $rows = DB::table('event_news_batches')
                ->where('user_id', $user->id)
                ->where('last_occurred_at', '>=', $cutoff)
                ->orderByDesc('last_occurred_at')
                ->get();

            return $rows->map(function ($row) use ($events, $cutoff, $firstDisplay): ?array {
                $event = $events->get($row->event_id);
                if (! $event) {
                    return null;
                }

                $content = json_decode((string) $row->content, true) ?: [];
                $finishedAt = mb_strtoupper(trim((string) $event->eventStatus?->name)) === 'FINALIZADO'
                    ? $event->event_news_finalized_at : null;
                $withinBounds = static fn (array $item): bool =>
                    Carbon::parse($item['at'])->greaterThanOrEqualTo($cutoff)
                    && ($finishedAt === null || Carbon::parse($item['at'])->lessThanOrEqualTo($finishedAt));
                $messages = array_values(array_filter($content['messages'] ?? [], $withinBounds));
                $joins = array_values(array_filter($content['joins'] ?? [], $withinBounds));

                if ($messages === [] && $joins === []) {
                    return null;
                }

                $unread = isset($firstDisplay[$row->id]);
                foreach ($messages as &$message) {
                    $message['read'] = ! $unread;
                }
                unset($message);
                foreach ($joins as &$join) {
                    $join['read'] = ! $unread;
                }
                unset($join);

                return [
                    'event' => $event,
                    'messages' => $messages,
                    'joins' => $joins,
                    'join_count' => count($joins),
                    'unread_join_count' => $unread ? count($joins) : 0,
                    'own_squad_join_count' => count(array_filter($joins,
                        fn (array $join): bool => ! empty($join['in_own_squad']))),
                    'unread' => $unread,
                    'latest' => $row->last_occurred_at,
                    'batch_id' => $row->id,
                ];
            })
                ->filter()
                ->sort(fn (array $a, array $b): int => ((int) $b['unread'] <=> (int) $a['unread'])
                    ?: strcmp($b['latest'], $a['latest']) ?: ((int) $b['batch_id'] <=> (int) $a['batch_id']))
                ->values();
        }, 3);
    }
}
