<?php

namespace App\Services\MemberProcedures;

use App\Models\MemberProcedure;
use App\Models\MemberProcedureStep;

class MemberProcedureRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        return [
            MemberProcedure::TYPE_RECRUITMENT_START => [
                'label' => 'Inicio de reclutamiento',
                'steps' => [
                    $this->auto('linked_application', 'Comprobar cuenta y solicitud de alistamiento vinculadas'),
                    $this->auto('status_recruit', 'Cambiar estado a RECLUTA', ['linked_application']),
                    $this->auto('tutor_area', 'Crear/validar el proceso en el Área de tutores', ['status_recruit']),
                    $this->manual('discord_recruit', 'Actualizar los roles de Discord a RECLUTA', ['status_recruit']),
                    $this->auto('treasury_signal', 'Notificar a Tesorería la señal de 6 €', ['status_recruit']),
                    $this->manual('telegram_recruit_announcement', 'Notificar la entrada en el tablón de anuncios de Telegram', ['status_recruit']),
                    $this->auto('tutor_coordinator_notice', 'Notificar al coordinador de tutores la disponibilidad del nuevo recluta', ['tutor_area']),
                    $this->manual('whatsapp_recruit_group', 'Añadir al grupo de WhatsApp de reclutas', ['status_recruit']),
                    $this->waiting('tutor_assignment', 'Esperar a que un tutor se asigne al recluta', ['tutor_area']),
                ],
            ],
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => [
                'label' => 'Completar reclutamiento',
                'steps' => [
                    $this->auto('complete_validation', 'Comprobar que el recluta y su solicitud están listos'),
                    $this->auto('assign_promotion', 'Asignar la promoción', ['complete_validation']),
                    $this->auto('status_active', 'Cambiar estado a ACTIVO y fijar Miembro desde', ['assign_promotion']),
                    $this->auto('armasquads_upsert', 'Añadir o actualizar el miembro en ArmaSquads', ['status_active']),
                    array_merge($this->auto('google_sheets_transfer', 'Registrar y verificar los datos personales en Google Sheets', ['status_active']), [
                        'instructions' => 'Automático con Service Account. ID Web = ID del usuario. INGRESO = primera entrada en RECLUTA. FECHA CALAVERA = Miembro desde / primera transición RECLUTA → ACTIVO. Si falla, los datos personales NO se eliminan y puedes usar la fila manual de contingencia.',
                    ]),
                    $this->auto('purge_recruitment_personal', 'Eliminar del formulario los datos personales ya transferidos', ['google_sheets_transfer']),
                    $this->manual('leave_recruit_groups', 'Sacar al usuario de los grupos de reclutas', ['status_active']),
                    $this->auto('alpha_metopa', 'Asignar la metopa de miembro ALPHA', ['status_active']),
                    $this->manual('ts3_alpha', 'Cambiar en TS3 el rol RECLUTA por ALPHA', ['status_active']),
                    $this->manual('discord_alpha', 'Cambiar en Discord el rol RECLUTA por ALPHA', ['status_active']),
                    $this->manual('telegram_groups_email', 'Enviar por email los enlaces de los grupos oficiales de Telegram', ['status_active']),
                    $this->auto('treasury_member_notice', 'Notificar a Tesorería el alta como miembro', ['status_active']),
                    $this->auto('google_sheets_status_sync', 'Sincronizar ACTIVO, promoción e ingreso en Google Sheets', ['google_sheets_transfer', 'status_active']),
                ],
            ],
            MemberProcedure::TYPE_REACTIVATION => [
                'label' => 'Reactivación desde reserva',
                'steps' => [
                    $this->auto('reactivation_validation', 'Comprobar que el miembro está en RESERVA'),
                    $this->auto('treasury_reactivation_notice', 'Notificar a Tesorería la reactivación', ['reactivation_validation']),
                    $this->manual('discord_reactivation', 'Cambiar en Discord RESERVA por ACTIVO', ['reactivation_validation']),
                    $this->manual('telegram_groups_email', 'Reenviar por email los enlaces de Telegram', ['reactivation_validation']),
                    $this->manual('ts3_reactivation', 'Cambiar en TS3 RESERVA por ALPHA', ['reactivation_validation']),
                    $this->auto('status_active', 'Cambiar estado a ACTIVO', ['reactivation_validation']),
                    $this->auto('google_sheets_status_sync', 'Actualizar el estado en Google Sheets', ['status_active']),
                ],
            ],
            MemberProcedure::TYPE_RESERVE => [
                'label' => 'Paso a reserva',
                'steps' => [
                    $this->auto('reserve_validation', 'Comprobar que el miembro está ACTIVO'),
                    $this->auto('treasury_reserve_notice', 'Notificar a Tesorería el paso a reserva', ['reserve_validation']),
                    $this->manual('discord_reserve', 'Cambiar en Discord ALPHA por RESERVA', ['reserve_validation']),
                    $this->manual('telegram_leave_official', 'Sacar de los grupos oficiales de Telegram', ['reserve_validation']),
                    $this->manual('ts3_reserve', 'Cambiar en TS3 ALPHA por RESERVA', ['reserve_validation']),
                    $this->auto('status_reserve', 'Cambiar estado a RESERVA', ['reserve_validation']),
                    $this->auto('google_sheets_status_sync', 'Actualizar el estado y días de reserva en Google Sheets', ['status_reserve']),
                ],
            ],
            MemberProcedure::TYPE_DEPARTURE => [
                'label' => 'Baja',
                'steps' => $this->departureSteps(false),
            ],
            MemberProcedure::TYPE_DISMISSAL => [
                'label' => 'Cese',
                'steps' => $this->departureSteps(true),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function definition(string $type): array
    {
        $definition = $this->definitions()[$type] ?? null;
        if (! $definition) {
            throw new \InvalidArgumentException('Procedimiento no reconocido.');
        }

        return $definition;
    }

    /** @return list<array<string, mixed>> */
    private function departureSteps(bool $dismissal): array
    {
        $steps = [
            $this->auto('departure_validation', $dismissal ? 'Comprobar requisitos del cese' : 'Comprobar requisitos de la baja'),
            $this->auto('treasury_departure_notice', $dismissal ? 'Notificar a Tesorería el cese' : 'Notificar a Tesorería la baja', ['departure_validation']),
            $this->auto('armasquads_delete', 'Retirar de ArmaSquads', ['departure_validation']),
            $this->manual('discord_departure', 'Retirar roles/acceso de Discord', ['departure_validation']),
            $this->manual('telegram_leave_all', 'Sacar de los grupos de Telegram', ['departure_validation']),
            $this->manual('whatsapp_leave_all', 'Sacar de los grupos de WhatsApp', ['departure_validation']),
            $this->manual('ts3_departure', 'Retirar grupos/roles de TeamSpeak 3', ['departure_validation']),
        ];

        if ($dismissal) {
            $steps[] = $this->manual('ban_if_required', 'Aplicar los bloqueos/baneos indicados para el cese', ['departure_validation']);
        }

        $steps[] = $this->auto($dismissal ? 'status_dismissed' : 'status_departed', $dismissal ? 'Cambiar estado a CESADO' : 'Cambiar estado a BAJA', ['departure_validation']);
        $steps[] = $this->auto(
            'google_sheets_status_sync',
            'Actualizar el estado en Google Sheets',
            [$dismissal ? 'status_dismissed' : 'status_departed'],
        );

        if ($dismissal) {
            $steps[] = $this->manual('dismissal_email', 'Enviar el correo de cese', ['status_dismissed']);
        }

        return $steps;
    }

    private function auto(string $key, string $label, array $dependsOn = []): array
    {
        return $this->step($key, $label, MemberProcedureStep::KIND_AUTOMATIC, $dependsOn);
    }

    private function manual(string $key, string $label, array $dependsOn = []): array
    {
        return $this->step($key, $label, MemberProcedureStep::KIND_MANUAL, $dependsOn);
    }

    private function waiting(string $key, string $label, array $dependsOn = []): array
    {
        return $this->step($key, $label, MemberProcedureStep::KIND_WAITING, $dependsOn);
    }

    private function step(string $key, string $label, string $kind, array $dependsOn): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'kind' => $kind,
            'required' => true,
            'depends_on' => $dependsOn,
        ];
    }
}
