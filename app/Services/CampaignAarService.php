<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignAar;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\Faction;
use App\Models\SlotType;
use App\Models\User;
use App\Notifications\CampaignAarPendingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CampaignAarService
{
    public function ensureForFinalizedEvent(
        Event $event,
        bool $notifyCommander = true,
    ): ?CampaignAar {
        $event->loadMissing([
            'eventStatus',
            'activity.campaign',
            'activity.editor',
        ]);

        if (
            mb_strtoupper(trim((string) $event->eventStatus?->name)) !== 'FINALIZADO'
            || ! $event->activity?->campaign_id
        ) {
            return null;
        }

        $campaign = $event->activity->campaign;
        $commander = $this->commanderForEvent($event);

        $aar = DB::transaction(function () use ($event, $campaign, $commander): CampaignAar {
            $existing = CampaignAar::query()
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $updates = [];

                /*
                 * En AAR pendientes corregimos también un commander_user_id
                 * antiguo o vacío. El Mando global se obtiene del tipo de slot
                 * del ORBAT, no del nombre visible del puesto.
                 */
                if (
                    $commander
                    && (
                        $existing->status === 'pending'
                        || ! $existing->commander_user_id
                    )
                    && (int) $existing->commander_user_id !== (int) $commander->id
                ) {
                    $updates['commander_user_id'] = $commander->id;
                }

                /*
                 * Mientras el AAR siga pendiente, el snapshot puede
                 * refrescarse si el evento se reabre y vuelve a cerrarse.
                 * Una vez publicado, el ORBAT queda congelado como histórico.
                 */
                if ($existing->status === 'pending') {
                    $updates['orbat_snapshot'] = $this->buildOrbatSnapshot($event);
                }

                if ($updates !== []) {
                    $existing->forceFill($updates)->save();
                }

                return $existing;
            }

            return CampaignAar::query()->create([
                'campaign_id' => $campaign->id,
                'event_id' => $event->id,
                'commander_user_id' => $commander?->id,
                'status' => 'pending',
                'sections' => $this->defaultSections(),
                'orbat_snapshot' => $this->buildOrbatSnapshot($event),
            ]);
        });

        if (
            $notifyCommander
            && $aar->wasRecentlyCreated
            && $commander
        ) {
            Notification::send(
                $commander,
                new CampaignAarPendingNotification($aar),
            );
        }

        return $aar;
    }

    /**
     * Obtiene el Mando global real del evento.
     *
     * La fuente canónica del TIPO de puesto es el ORBAT del evento:
     * slot_type_id -> SlotType "Mando Global". El nombre visible del slot
     * puede ser "Teniente", "Capitán", "HQ", etc. Una vez localizada la
     * slot_key, resolvemos el usuario asignado desde event_slots.
     */
    public function commanderForEvent(Event $event): ?User
    {
        $event->ensureOrbatSlotKeys();

        $orbatSlots = collect($event->orbat['groups'] ?? [])
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->values();

        $slotTypeIds = $orbatSlots
            ->pluck('slot_type_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($slotTypeIds->isNotEmpty()) {
            $mandoGlobalTypeIds = SlotType::query()
                ->whereIn('id', $slotTypeIds)
                ->get(['id', 'name'])
                ->filter(
                    fn (SlotType $slotType): bool => $this->normalizeSlotTypeName($slotType->name) === 'MANDO GLOBAL',
                )
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values();

            if ($mandoGlobalTypeIds->isNotEmpty()) {
                $mandoGlobalSlotKeys = $orbatSlots
                    ->filter(
                        fn (array $slot): bool => $mandoGlobalTypeIds->contains((int) ($slot['slot_type_id'] ?? 0)),
                    )
                    ->pluck('slot_key')
                    ->filter(fn ($key): bool => filled($key))
                    ->map(fn ($key): string => (string) $key)
                    ->unique()
                    ->values();

                if ($mandoGlobalSlotKeys->isNotEmpty()) {
                    $assignment = EventSlot::query()
                        ->where('event_id', $event->id)
                        ->whereIn('slot_key', $mandoGlobalSlotKeys)
                        ->whereNotNull('user_id')
                        ->with('user')
                        ->orderBy('id')
                        ->first();

                    if ($assignment?->user) {
                        return $assignment->user;
                    }
                }
            }
        }

        /*
         * Compatibilidad defensiva con eventos antiguos cuyo ORBAT no pueda
         * resolver slot_key/slot_type_id correctamente. Sigue mirando el tipo
         * real del EventSlot, nunca el nombre visible del puesto.
         */
        return EventSlot::query()
            ->where('event_id', $event->id)
            ->whereNotNull('user_id')
            ->with(['slotType:id,name', 'user'])
            ->orderBy('id')
            ->get()
            ->first(
                fn (EventSlot $slot): bool => $this->normalizeSlotTypeName($slot->slotType?->name) === 'MANDO GLOBAL',
            )
            ?->user;
    }

    public function defaultSections(): array
    {
        return [
            $this->newSection('Objetivo principal'),
            $this->newSection('Objetivos secundarios'),
            $this->newSection('Objetivos de oportunidad'),
            $this->newSection('Medios empleados'),
            $this->newSection('Bajas y pérdidas'),
            $this->newSection('Reporte / Desarrollo de la operación'),
            $this->newSection('Feedback / Inteligencia para el siguiente operativo'),
        ];
    }

    public function normalizeSections(array $sections): array
    {
        return collect($sections)
            ->take(20)
            ->map(function (array $section): array {
                return [
                    'key' => filled($section['key'] ?? null)
                        ? (string) $section['key']
                        : (string) Str::ulid(),
                    'title' => trim((string) ($section['title'] ?? '')),
                    'content' => trim((string) ($section['content'] ?? '')),
                ];
            })
            ->filter(fn (array $section): bool => $section['title'] !== '')
            ->values()
            ->all();
    }

    public function sequenceNumber(Event $event): int
    {
        $campaignId = (int) $event->activity?->campaign_id;

        if ($campaignId < 1) {
            return 1;
        }

        $ids = Event::query()
            ->whereHas(
                'activity',
                fn ($query) => $query->where('campaign_id', $campaignId),
            )
            ->whereHas(
                'eventStatus',
                fn ($query) => $query->whereIn('name', ['ACTIVO', 'FINALIZADO']),
            )
            ->orderBy('date')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $position = $ids->search((int) $event->id);

        return $position === false ? 1 : $position + 1;
    }

    public function completedAndPendingCounts(Campaign $campaign): array
    {
        $rows = CampaignAar::query()
            ->where('campaign_id', $campaign->id)
            ->get(['status']);

        return [
            'published' => $rows->where('status', 'published')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
        ];
    }

    private function buildOrbatSnapshot(Event $event): array
    {
        $event->ensureOrbatSlotKeys();

        $groups = collect($event->orbat['groups'] ?? [])
            ->filter(fn (array $group): bool => (bool) ($group['visible'] ?? true));

        $slotTypeIds = $groups
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->pluck('slot_type_id')
            ->filter()
            ->unique()
            ->values();

        $factionIds = $groups
            ->pluck('faction_id')
            ->filter()
            ->unique()
            ->values();

        $slotTypes = SlotType::query()
            ->whereIn('id', $slotTypeIds)
            ->pluck('name', 'id');

        $factions = Faction::query()
            ->whereIn('id', $factionIds)
            ->pluck('name', 'id');

        $assignments = EventSlot::query()
            ->where('event_id', $event->id)
            ->with(['user', 'ally'])
            ->get()
            ->keyBy(fn (EventSlot $slot): string => (string) $slot->slot_key);

        return [
            'captured_at' => now()->toIso8601String(),
            'groups' => $groups
                ->map(function (array $group) use ($slotTypes, $factions, $assignments): array {
                    $factionId = (int) ($group['faction_id'] ?? 0);

                    return [
                        'name' => (string) ($group['name'] ?? 'Grupo'),
                        'faction' => (string) ($factions[$factionId] ?? ''),
                        'slots' => collect($group['slots'] ?? [])
                            ->filter(fn (array $slot): bool => (bool) ($slot['visible'] ?? true))
                            ->map(function (array $slot) use ($slotTypes, $assignments): array {
                                $assignment = $assignments->get((string) ($slot['slot_key'] ?? ''));
                                $slotTypeId = (int) ($slot['slot_type_id'] ?? 0);

                                return [
                                    'slot_name' => (string) ($slot['name'] ?? 'Slot'),
                                    'slot_type' => (string) ($slotTypes[$slotTypeId] ?? 'Sin tipo'),
                                    'assignee' => $assignment?->user?->nick
                                        ?? $assignment?->ally?->name
                                        ?? 'VACANTE',
                                    'is_ally' => $assignment?->ally_id !== null,
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    private function normalizeSlotTypeName(?string $name): string
    {
        return mb_strtoupper(trim((string) $name));
    }

    private function newSection(string $title): array
    {
        return [
            'key' => (string) Str::ulid(),
            'title' => $title,
            'content' => '',
        ];
    }
}
