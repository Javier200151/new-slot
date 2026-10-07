<?php

namespace App\Services\MemberProcedures;

use App\Models\ContactSubmission;
use App\Models\MemberProcedure;
use App\Models\MemberProcedureSetting;
use App\Models\MemberProcedureStep;
use App\Models\Promo;
use App\Models\RecruitmentPeriod;
use App\Models\SqaGroupUser;
use App\Models\Status;
use App\Models\User;
use App\Services\UserMetopaAssignmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class MemberProcedureEngine
{
    public function __construct(
        private readonly MemberProcedureRegistry $registry,
        private readonly MemberProcedureEligibility $eligibility,
        private readonly ProcedureNotificationService $notifications,
        private readonly ArmaSquadsService $armaSquads,
        private readonly GoogleSheetsService $googleSheets,
        private readonly DiscordService $discord,
        private readonly UserMetopaAssignmentService $metopas,
    ) {
    }

    public function start(User $user, string $type, array $input = [], ?int $actorId = null): MemberProcedure
    {
        $actorId ??= Auth::id();
        if (! $actorId) {
            throw new LogicException('El procedimiento necesita un administrador identificado.');
        }

        $this->eligibility->assertEligible($user, $type);
        $definition = $this->registry->definition($type);

        $procedure = DB::transaction(function () use ($user, $type, $input, $actorId, $definition): MemberProcedure {
            $active = MemberProcedure::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR])
                ->lockForUpdate()
                ->first();

            if ($active) {
                throw new LogicException('El usuario ya tiene un procedimiento abierto: ' . $active->typeLabel() . '.');
            }

            $procedure = MemberProcedure::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'status' => MemberProcedure::STATUS_IN_PROGRESS,
                'input' => $input,
                'started_by_user_id' => $actorId,
                'started_at' => now(),
            ]);

            foreach ($definition['steps'] as $position => $stepDefinition) {
                $kind = (string) $stepDefinition['kind'];
                $status = match ($kind) {
                    MemberProcedureStep::KIND_MANUAL => MemberProcedureStep::STATUS_MANUAL,
                    MemberProcedureStep::KIND_WAITING => MemberProcedureStep::STATUS_WAITING,
                    default => MemberProcedureStep::STATUS_PENDING,
                };

                if (($stepDefinition['key'] ?? null) === 'ban_if_required' && ! (bool) ($input['ban_required'] ?? false)) {
                    $status = MemberProcedureStep::STATUS_SKIPPED;
                }

                $procedure->steps()->create([
                    'step_key' => $stepDefinition['key'],
                    'label' => $stepDefinition['label'],
                    'kind' => $kind,
                    'status' => $status,
                    'position' => $position + 1,
                    'required' => (bool) ($stepDefinition['required'] ?? true),
                    'meta' => [
                        'depends_on' => array_values($stepDefinition['depends_on'] ?? []),
                        'instructions' => $stepDefinition['instructions'] ?? null,
                    ],
                    'result' => $status === MemberProcedureStep::STATUS_SKIPPED
                        ? ['reason' => 'El administrador indicó que este cese no requiere bloqueo/ban.']
                        : null,
                    'completed_at' => $status === MemberProcedureStep::STATUS_SKIPPED ? now() : null,
                ]);
            }

            return $procedure;
        });

        return $this->runPending($procedure);
    }

    public function syncWaitingStepsForUser(int $userId): void
    {
        MemberProcedure::query()
            ->where('user_id', $userId)
            ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR])
            ->whereHas('steps', fn ($query) => $query->where('status', MemberProcedureStep::STATUS_WAITING))
            ->get()
            ->each(fn (MemberProcedure $procedure) => $this->runPending($procedure));
    }

    public function runPending(MemberProcedure $procedure): MemberProcedure
    {
        $procedure->refresh()->load(['user.status', 'steps']);

        if (in_array($procedure->status, [MemberProcedure::STATUS_COMPLETED, MemberProcedure::STATUS_CANCELLED], true)) {
            return $procedure;
        }

        foreach ($procedure->steps as $step) {
            if ($step->isFinished() || $step->kind === MemberProcedureStep::KIND_MANUAL) {
                continue;
            }

            if (! $this->dependenciesCompleted($procedure, $step)) {
                continue;
            }

            if ($step->kind === MemberProcedureStep::KIND_WAITING) {
                $this->executeStep($procedure, $step, true);
                continue;
            }

            if (in_array($step->status, [MemberProcedureStep::STATUS_PENDING, MemberProcedureStep::STATUS_ERROR, MemberProcedureStep::STATUS_MANUAL], true)) {
                $this->executeStep($procedure, $step, false);
            }
        }

        return $this->refreshProcedureStatus($procedure);
    }

    public function completeManualStep(MemberProcedureStep $step, ?int $actorId = null, ?string $note = null): MemberProcedure
    {
        $actorId ??= Auth::id();
        if (! $actorId) {
            throw new LogicException('No se ha podido identificar al administrador.');
        }

        $step->refresh();
        if ($step->isFinished()) {
            return $this->refreshProcedureStatus($step->procedure);
        }

        $procedure = $step->procedure()->with('steps')->firstOrFail();
        if (! $this->dependenciesCompleted($procedure, $step)) {
            throw new LogicException('Completa primero los pasos anteriores de los que depende esta tarea.');
        }

        if (! in_array($step->status, [MemberProcedureStep::STATUS_MANUAL, MemberProcedureStep::STATUS_ERROR], true)) {
            throw new LogicException('Este paso no puede completarse manualmente en su estado actual.');
        }

        $result = $step->result ?? [];
        if (filled($note)) {
            $result['manual_note'] = trim((string) $note);
        }

        $step->update([
            'status' => MemberProcedureStep::STATUS_COMPLETED,
            'result' => $result,
            'last_error' => null,
            'completed_by_user_id' => $actorId,
            'completed_at' => now(),
        ]);

        return $this->runPending($procedure);
    }

    public function retryStep(MemberProcedureStep $step): MemberProcedure
    {
        $step->refresh();
        if ($step->kind === MemberProcedureStep::KIND_MANUAL) {
            throw new LogicException('Los pasos manuales deben marcarse como completados cuando se realicen.');
        }

        $step->update([
            'status' => $step->kind === MemberProcedureStep::KIND_WAITING
                ? MemberProcedureStep::STATUS_WAITING
                : MemberProcedureStep::STATUS_PENDING,
            'last_error' => null,
        ]);

        return $this->runPending($step->procedure);
    }

    public function cancel(MemberProcedure $procedure, string $reason, ?int $actorId = null): MemberProcedure
    {
        $actorId ??= Auth::id();
        if (! $actorId) {
            throw new LogicException('No se ha podido identificar al administrador.');
        }

        if ($procedure->status === MemberProcedure::STATUS_COMPLETED) {
            throw new LogicException('Un procedimiento completado no puede cancelarse.');
        }

        $procedure->update([
            'status' => MemberProcedure::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => trim($reason),
        ]);

        return $procedure->refresh();
    }

    private function executeStep(MemberProcedure $procedure, MemberProcedureStep $step, bool $waiting): void
    {
        $step->update([
            'status' => MemberProcedureStep::STATUS_RUNNING,
            'attempts' => ((int) $step->attempts) + 1,
            'last_error' => null,
        ]);

        try {
            $result = $this->handle($procedure->fresh(['user.status']), $step->fresh());
            $status = (string) ($result['status'] ?? MemberProcedureStep::STATUS_COMPLETED);

            if ($waiting && $status === MemberProcedureStep::STATUS_PENDING) {
                $status = MemberProcedureStep::STATUS_WAITING;
            }

            $step->update([
                'status' => $status,
                'result' => (array) ($result['result'] ?? []),
                'last_error' => $result['message'] ?? null,
                'completed_by_user_id' => $status === MemberProcedureStep::STATUS_COMPLETED ? Auth::id() : null,
                'completed_at' => $status === MemberProcedureStep::STATUS_COMPLETED ? now() : null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $step->update([
                'status' => MemberProcedureStep::STATUS_ERROR,
                'last_error' => $exception->getMessage(),
                'completed_at' => null,
                'completed_by_user_id' => null,
            ]);
        }
    }

    /** @return array{status:string,result?:array<string,mixed>,message?:string} */
    private function handle(MemberProcedure $procedure, MemberProcedureStep $step): array
    {
        $user = $procedure->user;
        $setting = MemberProcedureSetting::current();

        return match ($step->step_key) {
            'linked_application' => $this->linkedApplication($user),
            'status_recruit' => $this->setStatus($user, 'RECLUTA'),
            'tutor_area' => $this->tutorArea($user),
            'discord_recruit' => $this->discordRoleSync($procedure, $user, $setting, 'recruit'),
            'treasury_signal' => $this->notifyTreasury($procedure, $step, $setting, 'Nueva señal de reclutamiento', $user->nick . ' ha iniciado su reclutamiento. Revisar el pago de la señal de 6 €.'),
            'tutor_coordinator_notice' => $this->notifyTutorCoordinator($procedure, $step, $setting),
            'tutor_assignment' => $this->waitForTutor($user),

            'complete_validation' => $this->completeValidation($user),
            'assign_promotion' => $this->assignPromotion($procedure, $user),
            'status_active' => $procedure->type === MemberProcedure::TYPE_RECRUITMENT_COMPLETE
                ? $this->activatePromotedRecruit($procedure, $user)
                : $this->setStatus($user, 'ACTIVO'),
            'armasquads_upsert' => $this->armaSquadsUpsert($user, $setting),
            'google_sheets_transfer' => $this->googleSheetsTransfer($user, $setting),
            'google_sheets_status_sync' => $this->googleSheetsStatusSync($user, $setting),
            'purge_recruitment_personal' => $this->purgeRecruitmentPersonalData($user),
            'alpha_metopa' => $this->assignAlphaMetopa($user, $setting),
            'discord_alpha' => $this->discordRoleSync($procedure, $user, $setting, 'alpha'),
            'treasury_member_notice' => $this->notifyTreasury($procedure, $step, $setting, 'Alta de nuevo miembro', $user->nick . ' ha completado su reclutamiento y ha pasado a ACTIVO.'),

            'not_promoted_validation' => $this->notPromotedValidation($user),
            'treasury_not_promoted_notice' => $this->notifyTreasury($procedure, $step, $setting, 'Recluta no promocionado', $user->nick . ' finaliza su reclutamiento sin promocionar a miembro.'),
            'discord_not_promoted' => $this->discordRoleSync($procedure, $user, $setting, 'remove'),
            'status_not_promoted' => $this->setStatus($user, 'NO PROMOCIONADO'),

            'reactivation_validation' => $this->requireStatus($user, 'RESERVA'),
            'treasury_reactivation_notice' => $this->notifyTreasury($procedure, $step, $setting, 'Reactivación de miembro', $user->nick . ' inicia su reactivación desde RESERVA.'),
            'discord_reactivation' => $this->discordRoleSync($procedure, $user, $setting, 'alpha'),

            'reserve_validation' => $this->requireStatus($user, 'ACTIVO'),
            'treasury_reserve_notice' => $this->notifyTreasury($procedure, $step, $setting, 'Paso a reserva', $user->nick . ' pasa a RESERVA.'),
            'discord_reserve' => $this->discordRoleSync($procedure, $user, $setting, 'reserve'),
            'status_reserve' => $this->setStatus($user, 'RESERVA'),

            'departure_validation' => $this->departureValidation($user),
            'treasury_departure_notice' => $this->notifyTreasury(
                $procedure,
                $step,
                $setting,
                $procedure->type === MemberProcedure::TYPE_DISMISSAL ? 'Cese de miembro' : 'Baja de miembro',
                $user->nick . ($procedure->type === MemberProcedure::TYPE_DISMISSAL ? ' está siendo tramitado como CESADO.' : ' está siendo tramitado como BAJA.'),
            ),
            'armasquads_delete' => $this->armaSquadsDelete($user, $setting),
            'discord_departure' => $this->discordDeparture($procedure, $user, $setting),
            'status_departed' => $this->setStatus($user, 'BAJA'),
            'status_dismissed' => $this->setStatus($user, 'CESADO'),

            default => throw new LogicException('No existe automatización para el paso ' . $step->step_key . '.'),
        };
    }

    private function dependenciesCompleted(MemberProcedure $procedure, MemberProcedureStep $step): bool
    {
        $dependencies = (array) (($step->meta ?? [])['depends_on'] ?? []);
        if ($dependencies === []) {
            return true;
        }

        $statuses = $procedure->steps()
            ->whereIn('step_key', $dependencies)
            ->pluck('status', 'step_key');

        foreach ($dependencies as $dependency) {
            if (! in_array($statuses[$dependency] ?? null, [MemberProcedureStep::STATUS_COMPLETED, MemberProcedureStep::STATUS_SKIPPED], true)) {
                return false;
            }
        }

        return true;
    }

    private function refreshProcedureStatus(MemberProcedure $procedure): MemberProcedure
    {
        $procedure->refresh()->load('steps');

        if ($procedure->status === MemberProcedure::STATUS_CANCELLED) {
            return $procedure;
        }

        $required = $procedure->steps->where('required', true);
        $hasError = $required->contains(fn (MemberProcedureStep $step): bool => $step->status === MemberProcedureStep::STATUS_ERROR);
        $allFinished = $required->every(fn (MemberProcedureStep $step): bool => $step->isFinished());

        $procedure->update([
            'status' => $allFinished
                ? MemberProcedure::STATUS_COMPLETED
                : ($hasError ? MemberProcedure::STATUS_ERROR : MemberProcedure::STATUS_IN_PROGRESS),
            'completed_at' => $allFinished ? ($procedure->completed_at ?? now()) : null,
        ]);

        return $procedure->refresh(['steps', 'user']);
    }

    private function linkedApplication(User $user): array
    {
        $submission = $this->recruitmentSubmission($user);
        if (! $submission) {
            throw new LogicException('No existe una solicitud de alistamiento aprobada vinculada a este usuario. Vincúlala por email o manualmente antes de continuar.');
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['submission_id' => $submission->id]];
    }

    private function completeValidation(User $user): array
    {
        $this->requireStatus($user, 'RECLUTA');
        $submission = $this->recruitmentSubmission($user);
        if (! $submission) {
            throw new LogicException('El recluta no tiene una solicitud de alistamiento aprobada vinculada.');
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['submission_id' => $submission->id]];
    }

    private function tutorArea(User $user): array
    {
        $period = RecruitmentPeriod::query()->where('open_user_id', $user->id)->first();
        if (! $period) {
            throw new LogicException('No se creó el periodo de reclutamiento en el Área de tutores.');
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['recruitment_period_id' => $period->id]];
    }

    private function waitForTutor(User $user): array
    {
        $period = RecruitmentPeriod::query()->where('open_user_id', $user->id)->first();
        if ($period?->tutor_id) {
            return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['tutor_id' => (int) $period->tutor_id]];
        }

        return ['status' => MemberProcedureStep::STATUS_WAITING, 'message' => 'El recluta todavía no tiene tutor asignado.'];
    }

    private function assignPromotion(MemberProcedure $procedure, User $user): array
    {
        $promoId = (int) (($procedure->input ?? [])['promo_id'] ?? 0);
        if (! $promoId || ! Promo::query()->whereKey($promoId)->exists()) {
            throw new LogicException('Selecciona una promoción válida al iniciar el procedimiento.');
        }

        if ((int) $user->promo_id !== $promoId) {
            $user->forceFill(['promo_id' => $promoId])->save();
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['promo_id' => $promoId]];
    }

    private function activatePromotedRecruit(MemberProcedure $procedure, User $user): array
    {
        $statusResult = $this->setStatus($user, 'ACTIVO');

        // La promoción se asigna antes del cambio de estado, pero la volvemos a
        // verificar después de RECLUTA -> ACTIVO. Esto hace el flujo resistente
        // a observers/reglas de firma que puedan ejecutarse durante la transición.
        $freshUser = $user->fresh();
        $promotionResult = $this->assignPromotion($procedure, $freshUser);

        $freshUser->refresh();
        $expectedPromoId = (int) (($procedure->input ?? [])['promo_id'] ?? 0);
        if ((int) $freshUser->promo_id !== $expectedPromoId) {
            throw new LogicException('La promoción seleccionada no quedó guardada en el usuario después de pasar a ACTIVO.');
        }

        return [
            'status' => MemberProcedureStep::STATUS_COMPLETED,
            'result' => array_merge(
                (array) ($statusResult['result'] ?? []),
                (array) ($promotionResult['result'] ?? []),
            ),
        ];
    }

    private function notPromotedValidation(User $user): array
    {
        $this->requireStatus($user, 'RECLUTA');

        $period = RecruitmentPeriod::query()
            ->where('open_user_id', $user->id)
            ->whereNull('ended_at')
            ->first();

        if (! $period) {
            throw new LogicException('El recluta no tiene un periodo de reclutamiento abierto en el Área de tutores.');
        }

        return [
            'status' => MemberProcedureStep::STATUS_COMPLETED,
            'result' => ['recruitment_period_id' => (int) $period->id],
        ];
    }

    private function setStatus(User $user, string $name): array
    {
        $statusId = (int) Status::withTrashed()->whereRaw('UPPER(name) = ?', [mb_strtoupper($name)])->value('id');
        if (! $statusId) {
            throw new LogicException('No existe el estado ' . $name . ' en NewSlot.');
        }

        if ((int) $user->status_id !== $statusId) {
            $user->forceFill(['status_id' => $statusId])->save();
            $user->refresh();
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['status_id' => $statusId, 'status' => $name]];
    }

    private function requireStatus(User $user, string $expected): array
    {
        $user->loadMissing('status');
        $current = mb_strtoupper(trim((string) $user->status?->name));
        if ($current !== mb_strtoupper($expected)) {
            throw new LogicException('El usuario debe estar en estado ' . $expected . ' y actualmente está en ' . ($current ?: 'sin estado') . '.');
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['status' => $current]];
    }

    private function departureValidation(User $user): array
    {
        $user->loadMissing('status');
        $current = mb_strtoupper(trim((string) $user->status?->name));
        if (in_array($current, ['BAJA', 'CESADO'], true)) {
            throw new LogicException('El usuario ya está en estado ' . $current . '.');
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['previous_status' => $current]];
    }

    private function notifyTreasury(MemberProcedure $procedure, MemberProcedureStep $step, MemberProcedureSetting $setting, string $title, string $body): array
    {
        if (! $setting->treasury_group_id) {
            throw new LogicException('Configura primero qué Grupo SQA representa a Tesorería.');
        }

        $notification = $this->notifications->toGroup($procedure, $step, (int) $setting->treasury_group_id, $title, $body);

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['notification_id' => $notification->id]];
    }

    private function notifyTutorCoordinator(MemberProcedure $procedure, MemberProcedureStep $step, MemberProcedureSetting $setting): array
    {
        if (! $setting->tutors_group_id) {
            throw new LogicException('Configura primero el Grupo SQA que representa a Tutores.');
        }

        $coordinator = SqaGroupUser::query()
            ->where('sqa_group_id', $setting->tutors_group_id)
            ->where('coordinator', true)
            ->whereNull('deleted_at')
            ->first();

        if (! $coordinator) {
            throw new LogicException('El grupo de Tutores no tiene coordinador asignado.');
        }

        $notification = $this->notifications->toUser(
            $procedure,
            $step,
            (int) $coordinator->user_id,
            'Nuevo recluta disponible',
            $procedure->user->nick . ' ha iniciado el reclutamiento y está disponible para asignación de tutor.',
        );

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['notification_id' => $notification->id, 'coordinator_user_id' => (int) $coordinator->user_id]];
    }

    private function discordRoleSync(MemberProcedure $procedure, User $user, MemberProcedureSetting $setting, string $mode): array
    {
        if (! $this->discord->isConfigured($setting)) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'Discord todavía no está configurado. Realiza el cambio de rol manualmente y marca este paso como completado.',
            ];
        }

        if (! preg_match('/^\d{17,20}$/', trim((string) $user->discord_id))) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'El usuario no tiene un Discord ID numérico válido en su ficha. Añádelo o realiza este paso manualmente.',
            ];
        }

        $reason = 'NewSlot · ' . $procedure->typeLabel() . ' · ' . $user->nick;
        $result = match ($mode) {
            'recruit' => $this->discord->setRecruit($user, $setting, $reason),
            'alpha' => $this->discord->setAlpha($user, $setting, $reason),
            'reserve' => $this->discord->setReserve($user, $setting, $reason),
            'remove' => $this->discord->removeManagedRoles($user, $setting, $reason),
            default => throw new LogicException('Modo de sincronización de Discord no reconocido.'),
        };

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => $result];
    }

    private function discordDeparture(MemberProcedure $procedure, User $user, MemberProcedureSetting $setting): array
    {
        if (! $this->discord->isConfigured($setting)) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'Discord todavía no está configurado. Retira los roles o aplica el baneo manualmente y marca este paso como completado.',
            ];
        }

        if (! preg_match('/^\d{17,20}$/', trim((string) $user->discord_id))) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'El usuario no tiene un Discord ID numérico válido en su ficha. Gestiona su salida de Discord manualmente.',
            ];
        }

        $reason = 'NewSlot · ' . $procedure->typeLabel() . ' · ' . $user->nick;
        $banRequired = $procedure->type === MemberProcedure::TYPE_DISMISSAL
            && (bool) (($procedure->input ?? [])['ban_required'] ?? false);

        $result = $banRequired
            ? $this->discord->ban($user, $setting, $reason)
            : $this->discord->removeManagedRoles($user, $setting, $reason);

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => $result];
    }

    private function armaSquadsUpsert(User $user, MemberProcedureSetting $setting): array
    {
        if (! $this->armaSquads->isConfigured($setting)) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'ArmaSquads todavía no está configurado. Puedes realizar el alta manualmente y marcar este paso como completado.',
            ];
        }

        if (blank($user->steam_id)) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'Falta el SteamID64 del usuario. Añádelo o realiza el alta manualmente.',
            ];
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => $this->armaSquads->upsert($user, $setting)];
    }

    private function armaSquadsDelete(User $user, MemberProcedureSetting $setting): array
    {
        if (! $this->armaSquads->isConfigured($setting)) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'ArmaSquads todavía no está configurado. Retira al usuario manualmente y marca el paso como completado.',
            ];
        }

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => $this->armaSquads->delete($user, $setting)];
    }

    private function googleSheetsTransfer(User $user, MemberProcedureSetting $setting): array
    {
        if (! $this->googleSheets->isConfigured($setting)) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'Google Sheets todavía no está configurado. Usa la fila manual de contingencia y marca el paso como completado solo después de verificarla.',
            ];
        }

        $submission = $this->recruitmentSubmission($user);
        if (! $submission) {
            throw new LogicException('No se encontró la solicitud de alistamiento vinculada para transferir sus datos.');
        }

        return [
            'status' => MemberProcedureStep::STATUS_COMPLETED,
            'result' => $this->googleSheets->transferRecruitment($user, $submission, $setting),
        ];
    }

    private function googleSheetsStatusSync(User $user, MemberProcedureSetting $setting): array
    {
        if (! $this->googleSheets->isConfigured($setting)) {
            return [
                'status' => MemberProcedureStep::STATUS_MANUAL,
                'message' => 'Google Sheets todavía no está configurado. Actualiza la fila manualmente y marca este paso como completado.',
            ];
        }

        return [
            'status' => MemberProcedureStep::STATUS_COMPLETED,
            'result' => $this->googleSheets->syncOperational($user, $setting),
        ];
    }

    private function assignAlphaMetopa(User $user, MemberProcedureSetting $setting): array
    {
        if (! $setting->alpha_metopa_id) {
            throw new LogicException('Configura la metopa que corresponde a ALPHA.');
        }

        $result = $this->metopas->assign(
            userId: (int) $user->id,
            metopaId: (int) $setting->alpha_metopa_id,
            assignedAt: $user->member_at ?? now(),
            updateExisting: false,
        );

        return ['status' => MemberProcedureStep::STATUS_COMPLETED, 'result' => ['assignment' => $result, 'metopa_id' => (int) $setting->alpha_metopa_id]];
    }

    private function purgeRecruitmentPersonalData(User $user): array
    {
        $submission = $this->recruitmentSubmission($user);
        if (! $submission) {
            throw new LogicException('No se encontró la solicitud vinculada que debe limpiarse.');
        }

        $submission->forceFill([
            'full_name' => null,
            'birth_date' => null,
            'residence' => null,
            'phone_whatsapp' => null,
            'email' => 'purged-' . $submission->id . '@newslot.invalid',
        ])->save();

        return [
            'status' => MemberProcedureStep::STATUS_COMPLETED,
            'result' => ['submission_id' => $submission->id, 'purged' => ['full_name', 'birth_date', 'residence', 'phone_whatsapp', 'email']],
        ];
    }

    private function recruitmentSubmission(User $user): ?ContactSubmission
    {
        return ContactSubmission::query()
            ->where('is_recruitment', true)
            ->where('recruitment_review_status', ContactSubmission::REVIEW_APPROVED)
            ->where('recruitment_matched_user_id', $user->id)
            ->latest('id')
            ->first();
    }
}
