<?php

namespace App\Services;

use App\Models\Event;
use App\Models\SlotType;
use App\Models\SlotTypeQuickName;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseMetopaAwardService
{
    public function students(Event $event): Collection
    {
        $event->loadMissing([
            'eventStatus',
            'activity.operationType',
            'activity.metopa',
            'slots.user',
        ]);

        $studentQuickNameIds = SlotTypeQuickName::query()
            ->where('is_course_student', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $studentSlotTypeIds = SlotType::query()
            ->get(['id', 'name'])
            ->filter(
                fn (SlotType $slotType): bool =>
                    Str::of($slotType->name)
                        ->ascii()
                        ->lower()
                        ->squish()
                        ->toString() === 'alumno'
            )
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $studentSlotKeys = collect($event->orbat['groups'] ?? [])
            ->filter(
                fn (array $group): bool =>
                    (bool) ($group['visible'] ?? true)
            )
            ->flatMap(
                fn (array $group) => collect($group['slots'] ?? [])
                    ->filter(
                        fn (array $slot): bool =>
                            (bool) ($slot['visible'] ?? true)
                            && $this->isStudentSlot(
                                $slot,
                                $studentQuickNameIds,
                                $studentSlotTypeIds,
                            )
                    )
                    ->pluck('slot_key')
            )
            ->filter()
            ->map(fn ($slotKey): string => (string) $slotKey)
            ->unique()
            ->values();

        if ($studentSlotKeys->isEmpty()) {
            return collect();
        }

        return $event->slots
            ->filter(
                fn ($slot): bool =>
                    $slot->user_id !== null
                    && $studentSlotKeys->contains((string) $slot->slot_key)
            )
            ->map(fn ($slot) => $slot->user)
            ->filter(fn ($user): bool => $user instanceof User)
            ->unique('id')
            ->sortBy('nick', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function canManageAssignments(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole('admin')
            || (
                $user->can('user-metopas.create')
                && $user->can('user-metopas.update')
            );
    }

    public function canAward(Event $event): bool
    {
        $event->loadMissing([
            'eventStatus',
            'activity.operationType',
            'activity.metopa',
        ]);

        return $event->eventStatus?->name === 'FINALIZADO'
            && ($event->activity?->operationType?->awardsMetopa() ?? false)
            && $event->activity?->metopa_id !== null;
    }

    public function canAwardForUser(Event $event, ?User $user): bool
    {
        return $this->canManageAssignments($user)
            && $this->canAward($event);
    }

    /**
     * Entrega la metopa del curso a los destinatarios seleccionados.
     *
     * Los alumnos detectados en el ORBAT se usan como selección inicial en
     * Filament, pero el instructor puede añadir o quitar usuarios antes de
     * confirmar.
     */
    public function award(Event $event, array $userIds): array
    {
        if (! $this->canManageAssignments(Auth::user())) {
            throw new AuthorizationException(
                'No tienes permisos para asignar metopas.'
            );
        }

        if (! $this->canAward($event)) {
            throw ValidationException::withMessages([
                'event' =>
                    'Este evento no está preparado para entregar una metopa de curso.',
            ]);
        }

        $normalizedUserIds = collect($userIds)
            ->map(fn ($userId): int => (int) $userId)
            ->filter(fn (int $userId): bool => $userId > 0)
            ->unique()
            ->values();

        if ($normalizedUserIds->isEmpty()) {
            throw ValidationException::withMessages([
                'user_ids' =>
                    'Selecciona al menos un usuario para entregar la metopa.',
            ]);
        }

        $recipients = User::query()
            ->whereIn('id', $normalizedUserIds->all())
            ->orderBy('nick')
            ->get();

        if ($recipients->count() !== $normalizedUserIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' =>
                    'Uno o varios usuarios seleccionados ya no están disponibles.',
            ]);
        }

        $metopaId = (int) $event->activity->metopa_id;
        $assignedAt = $event->end_date
            ?? $event->date
            ?? now();

        $results = [
            'created' => 0,
            'restored' => 0,
            'updated' => 0,
            'already_exists' => 0,
        ];

        $assignmentService = app(UserMetopaAssignmentService::class);

        foreach ($recipients as $recipient) {
            $result = $assignmentService->assign(
                userId: (int) $recipient->id,
                metopaId: $metopaId,
                assignedAt: $assignedAt,
                updateExisting: false,
                preserveAssignedAtOnRestore: true,
            );

            if (array_key_exists($result, $results)) {
                $results[$result]++;
            }
        }

        return [
            'students' => $recipients,
            'metopa' => $event->activity->metopa,
            'results' => $results,
        ];
    }

    private function isStudentSlot(
        array $slot,
        array $studentQuickNameIds,
        array $studentSlotTypeIds,
    ): bool {
        $quickNameId = (int) ($slot['slot_quick_name_id'] ?? 0);

        if (
            $quickNameId > 0
            && in_array($quickNameId, $studentQuickNameIds, true)
        ) {
            return true;
        }

        $slotTypeId = (int) ($slot['slot_type_id'] ?? 0);

        if (
            $slotTypeId > 0
            && in_array($slotTypeId, $studentSlotTypeIds, true)
        ) {
            return true;
        }

        return Str::of((string) ($slot['name'] ?? ''))
            ->ascii()
            ->lower()
            ->squish()
            ->toString() === 'alumno';
    }
}
