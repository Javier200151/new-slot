<?php

namespace App\Services\MemberProcedures;

use App\Models\MemberProcedure;
use App\Models\MemberProcedureSetting;
use App\Models\MemberProcedureStep;
use App\Models\MemberProcedureStepDefinition;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Schema;

class MemberProcedureRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function definitions(): array
    {
        $defaults = $this->defaultDefinitions();

        $container = Container::getInstance();
        if (! $container->bound('db.schema') || ! Schema::hasTable('member_procedure_step_definitions')) {
            return $defaults;
        }

        $setting = MemberProcedureSetting::query()->first();
        if (! $setting) {
            return $defaults;
        }

        $stored = MemberProcedureStepDefinition::query()
            ->where('member_procedure_setting_id', $setting->id)
            ->orderBy('procedure_type')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('procedure_type');

        if ($stored->isEmpty()) {
            return $defaults;
        }

        $definitions = [];
        foreach ($defaults as $type => $default) {
            $records = $stored->get($type);
            if (! $records || $records->isEmpty()) {
                $definitions[$type] = $default;
                continue;
            }

            $steps = $records
                ->filter(fn (MemberProcedureStepDefinition $record): bool => $record->kind !== MemberProcedureStep::KIND_MANUAL || $record->is_enabled)
                ->map(fn (MemberProcedureStepDefinition $record): array => [
                    'key' => $record->step_key,
                    'label' => $record->label,
                    'kind' => $record->kind,
                    'required' => (bool) $record->required,
                    'depends_on' => array_values($record->depends_on ?? []),
                    'instructions' => $record->instructions,
                ])
                ->values()
                ->all();

            $definitions[$type] = [
                'label' => $default['label'],
                'steps' => $steps,
            ];
        }

        return $definitions;
    }

    /** @return array<string, array<string, mixed>> */
    public function defaultDefinitions(): array
    {
        return [
            MemberProcedure::TYPE_RECRUITMENT_START => [
                'label' => 'Inicio de reclutamiento',
                'steps' => [
                    $this->auto('linked_application', 'Comprobar cuenta y solicitud de alistamiento vinculadas', [], 'Verifica que la solicitud aprobada está asociada a una cuenta de Squad ALPHA. La asociación puede haberse realizado automáticamente por email o manualmente desde alistamiento.'),
                    $this->auto('status_recruit', 'Cambiar estado a RECLUTA', ['linked_application'], 'Cambia el estado del usuario a RECLUTA. Este cambio debe disparar la lógica existente del área de tutores y quedar registrado en el historial de estados.'),
                    $this->auto('treasury_player_sync', 'Registrar al recluta en Tesorería', ['status_recruit'], 'Añade el nickname, estado RECLUTA y fecha de alta en la primera fila libre al final de Jugadores. No modifica saldo de arranque, remanente ni fórmulas. Si el nickname está duplicado, el paso falla para revisión manual y puede reintentarse.'),
                    $this->auto('tutor_area', 'Crear/validar el proceso en el Área de tutores', ['status_recruit'], 'Comprueba que existe el periodo de reclutamiento del usuario en el Área de tutores y que puede ser recogido por un tutor.'),
                    $this->auto('discord_recruit', 'Actualizar Discord a RECLUTA', ['status_recruit'], 'Asigna RECLUTA, retira ALPHA/RESERVA y normaliza el apodo al nick de Squad ALPHA sin la etiqueta ALPHA. Requiere Discord ID numérico en la ficha del usuario.'),
                    $this->auto('treasury_signal', 'Notificar a Tesorería la señal de 6 €', ['status_recruit'], 'Genera un aviso para el grupo configurado como Tesorería indicando que debe comprobarse el pago de la señal de 6 € del nuevo recluta.'),
                    $this->auto('tutor_coordinator_notice', 'Notificar al coordinador de tutores la disponibilidad del nuevo recluta', ['tutor_area'], 'Avisa al grupo/configuración de tutores de que hay un nuevo recluta disponible para ser asignado.'),
                    $this->auto('telegram_recruit_update', 'Publicar actualización del nuevo recluta en Telegram', ['status_recruit'], 'Publica en = ALPHA FORCE NETWORK = la entrada del nuevo recluta usando la plantilla configurable de Telegram y uno de los cierres aleatorios configurados.'),
                    $this->manual('whatsapp_recruit_group', 'Añadir al grupo de WhatsApp de reclutas', ['status_recruit'], 'Añade al recluta al grupo de WhatsApp destinado a reclutas. Marca este paso como completado cuando confirmes que ya está dentro.'),
                    $this->manual('ts3_recruit', 'Asignar en TS3 el rol RECLUTA', ['status_recruit'], 'En TeamSpeak 3, asigna manualmente al usuario el grupo o rol de RECLUTA y marca este paso como completado cuando esté confirmado.'),
                    $this->waiting('tutor_assignment', 'Esperar a que un tutor se asigne al recluta', ['tutor_area'], 'Este paso se completa automáticamente cuando el Área de tutores detecta que un tutor ha recogido al recluta.'),
                ],
            ],
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => [
                'label' => 'Completar reclutamiento',
                'steps' => [
                    $this->auto('complete_validation', 'Comprobar que el recluta y su solicitud están listos', [], 'Verifica que el usuario sigue en estado RECLUTA y que conserva una solicitud de alistamiento aprobada y vinculada.'),
                    $this->auto('assign_promotion', 'Asignar la promoción', ['complete_validation'], 'Asigna al usuario la promoción seleccionada al iniciar el procedimiento de Alta de calavera.'),
                    $this->auto('status_active', 'Cambiar estado a ACTIVO y fijar Miembro desde', ['assign_promotion'], 'Cambia el estado a ACTIVO. Si es la primera transición RECLUTA → ACTIVO, fija Miembro desde / FECHA CALAVERA, que será el día 0 para veteranías.'),
                    $this->auto('treasury_player_sync', 'Actualizar Tesorería a Miembro y calcular importe pendiente', ['status_active'], 'Cambia el estado de Jugadores a Miembro y calcula el importe que debe abonar según la señal realmente registrada, los meses consumidos y el trimestre que corresponda.'),
                    $this->auto('armasquads_upsert', 'Añadir o actualizar el miembro en ArmaSquads', ['status_active'], 'Sincroniza con ArmaSquads usando SteamID64 como PlayerID/uuid y el nick de Squad ALPHA como username. Si ya existe, actualiza los datos necesarios sin duplicarlo.'),
                    $this->auto('google_sheets_transfer', 'Registrar y verificar los datos personales en Google Sheets', ['status_active'], 'Copia la información del formulario a la pestaña General buscando por ID Web = ID del usuario. INGRESO es la primera entrada en RECLUTA y FECHA CALAVERA es Miembro desde. También sincroniza PROMOCIÓN y las fechas previstas de BRONCE, PLATA y ORO. El sistema verifica la fila antes de continuar.'),
                    $this->auto('purge_recruitment_personal', 'Eliminar del formulario los datos personales ya transferidos', ['google_sheets_transfer'], 'Solo después de que Google Sheets confirme la escritura, elimina del formulario los datos personales definidos para purga. El email de la cuenta de usuario no se elimina.'),
                    $this->manual('leave_recruit_groups', 'Sacar al usuario de los grupos de reclutas', ['status_active'], 'Retira al nuevo miembro de los grupos exclusivos de reclutas que no se gestionen automáticamente.'),
                    $this->auto('alpha_metopa', 'Asignar la metopa de miembro ALPHA', ['status_active'], 'Entrega automáticamente la metopa configurada como ALPHA, evitando duplicados si el usuario ya la tuviera.'),
                    $this->manual('ts3_alpha', 'Cambiar en TS3 el rol RECLUTA por ALPHA', ['status_active'], 'En TeamSpeak 3, retira el grupo/rol de RECLUTA y asigna el correspondiente a ALPHA.'),
                    $this->auto('discord_alpha', 'Cambiar Discord a ALPHA y actualizar apodo', ['status_active'], 'Retira RECLUTA/RESERVA, asigna ALPHA y cambia el apodo del servidor al formato configurado, por defecto [=ALPHA=] Nick.'),
                    $this->auto('telegram_groups_email', 'Enviar email de bienvenida con accesos de Telegram', ['status_active'], 'Envía automáticamente al nuevo miembro el correo de bienvenida con los enlaces configurados para ALPHA Cantina, ALPHA Oficial y = ALPHA FORCE NETWORK =.'),
                    $this->auto('treasury_member_notice', 'Notificar a Tesorería el alta como miembro', ['status_active'], 'Genera un aviso para el grupo configurado como Tesorería indicando que el recluta ha pasado a miembro ACTIVO.'),
                    $this->auto('google_sheets_status_sync', 'Sincronizar ACTIVO, promoción e ingreso en Google Sheets', ['google_sheets_transfer', 'status_active'], 'Actualiza la fila existente de Google Sheets con estado ACTIVO, promoción, fechas de ingreso/calavera y planificación de veteranías.'),
                ],
            ],
            MemberProcedure::TYPE_NOT_PROMOTED => [
                'label' => 'No promocionado',
                'steps' => [
                    $this->auto('not_promoted_validation', 'Comprobar que el usuario sigue siendo RECLUTA', [], 'Valida que el usuario está en estado RECLUTA y que existe un periodo de reclutamiento abierto antes de tramitarlo como NO PROMOCIONADO.'),
                    $this->auto('treasury_not_promoted_notice', 'Notificar a Tesorería que el recluta no promociona', ['not_promoted_validation'], 'Genera un aviso para el grupo configurado como Tesorería indicando que el recluta finaliza su proceso sin promocionar a miembro.'),
                    $this->auto('discord_not_promoted', 'Retirar acceso de RECLUTA en Discord', ['not_promoted_validation'], 'Retira los roles RECLUTA, ALPHA y RESERVA gestionados por Squad ALPHA y elimina la etiqueta ALPHA del apodo si el usuario sigue en el servidor.'),
                    $this->manual('whatsapp_not_promoted', 'Sacar del grupo de WhatsApp de reclutas', ['not_promoted_validation'], 'Retira al usuario del grupo de WhatsApp de reclutas y marca el paso cuando esté confirmado.'),
                    $this->manual('ts3_not_promoted', 'Retirar el rol/grupo de RECLUTA en TeamSpeak 3', ['not_promoted_validation'], 'Retira en TeamSpeak 3 el grupo o rol de RECLUTA y cualquier acceso temporal asociado.'),
                    $this->manual('not_promoted_email', 'Enviar la comunicación de no promoción', ['not_promoted_validation'], 'Envía al recluta la comunicación correspondiente al cierre de su reclutamiento sin promoción y marca el paso cuando el envío esté confirmado.'),
                    $this->auto('status_not_promoted', 'Cambiar estado a NO PROMOCIONADO', ['not_promoted_validation'], 'Cambia el estado del usuario de RECLUTA a NO PROMOCIONADO. Al salir de RECLUTA, el Área de tutores cierra automáticamente el periodo con resultado NO PROMOCIONADO.'),
                    $this->auto('treasury_player_sync', 'Actualizar Tesorería a Cesado', ['status_not_promoted'], 'En la hoja Jugadores, cualquier estado distinto de ACTIVO/RECLUTA/RESERVA se representa como Cesado.'),
                    $this->auto('telegram_recruit_update', 'Publicar actualización del recluta no promocionado en Telegram', ['status_not_promoted'], 'Publica en = ALPHA FORCE NETWORK = que el recluta ha finalizado el proceso como NO PROMOCIONADO usando la plantilla configurable de Telegram.'),
                ],
            ],
            MemberProcedure::TYPE_REACTIVATION => [
                'label' => 'Reactivación desde reserva',
                'steps' => [
                    $this->auto('reactivation_validation', 'Comprobar que el miembro está en RESERVA', [], 'Valida que el procedimiento se ejecuta sobre un usuario cuyo estado actual es RESERVA.'),
                    $this->auto('treasury_reactivation_notice', 'Notificar a Tesorería la reactivación', ['reactivation_validation'], 'Avisa a Tesorería de que el miembro vuelve de RESERVA a la actividad.'),
                    $this->auto('discord_reactivation', 'Cambiar Discord RESERVA → ALPHA y actualizar apodo', ['reactivation_validation'], 'Retira RESERVA/RECLUTA, asigna ALPHA y aplica al apodo la etiqueta ALPHA configurada.'),
                    $this->manual('ts3_reactivation', 'Cambiar en TS3 RESERVA por ALPHA', ['reactivation_validation'], 'En TeamSpeak 3, sustituye el grupo de RESERVA por el de ALPHA.'),
                    $this->auto('status_active', 'Cambiar estado a ACTIVO', ['reactivation_validation'], 'Cambia el estado interno del usuario de RESERVA a ACTIVO y registra el cambio en su historial.'),
                    $this->auto('treasury_player_sync', 'Actualizar Tesorería a Miembro y calcular reincorporación', ['status_active'], 'Cambia Jugadores a Miembro y calcula solo los meses que correspondan desde la reincorporación, respetando los meses ya marcados como Reserva y el cobro anticipado del trimestre siguiente.'),
                    $this->auto('telegram_groups_email', 'Enviar email de bienvenida de vuelta con accesos de Telegram', ['status_active'], 'Envía automáticamente al miembro reactivado el correo de bienvenida de vuelta con los enlaces configurados para ALPHA Cantina, ALPHA Oficial y = ALPHA FORCE NETWORK =.'),
                    $this->auto('google_sheets_status_sync', 'Actualizar el estado en Google Sheets', ['status_active'], 'Actualiza en Google Sheets el estado del miembro y recalcula los datos derivados de tiempo en reserva/veteranías.'),
                ],
            ],
            MemberProcedure::TYPE_RESERVE => [
                'label' => 'Paso a reserva',
                'steps' => [
                    $this->auto('reserve_validation', 'Comprobar que el miembro está ACTIVO', [], 'Valida que solo un miembro actualmente ACTIVO pueda iniciar el procedimiento de paso a RESERVA.'),
                    $this->auto('treasury_reserve_notice', 'Notificar a Tesorería el paso a reserva', ['reserve_validation'], 'Avisa al grupo configurado como Tesorería del paso del miembro a RESERVA.'),
                    $this->auto('discord_reserve', 'Cambiar Discord ALPHA → RESERVA y actualizar apodo', ['reserve_validation'], 'Retira ALPHA/RECLUTA, asigna RESERVA y normaliza el apodo al nick de Squad ALPHA sin la etiqueta ALPHA.'),
                    $this->manual('telegram_leave_official', 'Sacar de ALPHA Oficial y = ALPHA FORCE NETWORK =', ['reserve_validation'], 'Retira manualmente al miembro de ALPHA Oficial y = ALPHA FORCE NETWORK = al pasar a RESERVA. Debe permanecer en ALPHA Cantina.'),
                    $this->manual('ts3_reserve', 'Cambiar en TS3 ALPHA por RESERVA', ['reserve_validation'], 'En TeamSpeak 3, retira el grupo ALPHA y asigna el grupo de RESERVA.'),
                    $this->auto('status_reserve', 'Cambiar estado a RESERVA', ['reserve_validation'], 'Cambia el estado interno de ACTIVO a RESERVA y registra la fecha para el cálculo del tiempo efectivo de veteranía.'),
                    $this->auto('treasury_player_sync', 'Actualizar Tesorería a Reserva', ['status_reserve'], 'Actualiza el estado de la fila del usuario en Jugadores a Reserva sin tocar saldos ni movimientos.'),
                    $this->auto('google_sheets_status_sync', 'Actualizar el estado y días de reserva en Google Sheets', ['status_reserve'], 'Actualiza la fila del miembro en Google Sheets con su nuevo estado y las fechas de veteranía recalculadas según el tiempo acumulado en reserva.'),
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

    public function seedStoredDefinitions(?int $settingId = null): void
    {
        $container = Container::getInstance();
        if (! $container->bound('db.schema') || ! Schema::hasTable('member_procedure_step_definitions')) {
            return;
        }

        $settingId ??= (int) MemberProcedureSetting::current()->id;
        foreach ($this->defaultDefinitions() as $type => $definition) {
            foreach ($definition['steps'] as $index => $step) {
                MemberProcedureStepDefinition::query()->firstOrCreate(
                    [
                        'member_procedure_setting_id' => $settingId,
                        'procedure_type' => $type,
                        'step_key' => $step['key'],
                    ],
                    [
                        'label' => $step['label'],
                        'instructions' => $step['instructions'] ?? null,
                        'kind' => $step['kind'],
                        'position' => $index + 1,
                        'required' => (bool) ($step['required'] ?? true),
                        'is_enabled' => true,
                        'is_system' => true,
                        'depends_on' => array_values($step['depends_on'] ?? []),
                    ],
                );
            }
        }
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
            $this->auto('departure_validation', $dismissal ? 'Comprobar requisitos del cese' : 'Comprobar requisitos de la baja', [], $dismissal ? 'Comprueba que el usuario puede tramitarse como CESE y que no está ya en BAJA/CESADO.' : 'Comprueba que el usuario puede tramitarse como BAJA y que no está ya en BAJA/CESADO.'),
            $this->auto('treasury_departure_notice', $dismissal ? 'Notificar a Tesorería el cese' : 'Notificar a Tesorería la baja', ['departure_validation'], $dismissal ? 'Avisa a Tesorería del cese para que realice las gestiones económicas/administrativas correspondientes.' : 'Avisa a Tesorería de la baja para que realice las gestiones económicas/administrativas correspondientes.'),
            $this->auto('armasquads_delete', 'Retirar de ArmaSquads', ['departure_validation'], 'Elimina al usuario del Squad de ArmaSquads utilizando su SteamID64. Si ya no existe, el paso se considera idempotente.'),
            $this->auto('discord_departure', 'Retirar roles/acceso de Discord', ['departure_validation'], $dismissal ? 'En un cese, retira los roles gestionados por Squad ALPHA o banea automáticamente al usuario si se marcó la opción de baneo al iniciar el procedimiento.' : 'Retira los roles RECLUTA, ALPHA y RESERVA gestionados por Squad ALPHA y normaliza el apodo al nick de Squad ALPHA si el usuario permanece en el servidor.'),
            $this->manual('telegram_leave_all', 'Sacar de ALPHA Cantina, Oficial y Network', ['departure_validation'], 'Retira manualmente al usuario de ALPHA Cantina, ALPHA Oficial y = ALPHA FORCE NETWORK =.'),
            $this->manual('whatsapp_leave_all', 'Sacar de los grupos de WhatsApp', ['departure_validation'], 'Retira al usuario de los grupos oficiales de WhatsApp que correspondan.'),
            $this->manual('ts3_departure', 'Retirar grupos/roles de TeamSpeak 3', ['departure_validation'], 'Retira del usuario los grupos y roles de TeamSpeak 3 asociados a Squad Alpha.'),
        ];

        if ($dismissal) {
            $steps[] = $this->manual('ban_if_required', 'Aplicar los bloqueos/baneos pendientes en otros servicios', ['departure_validation'], 'Discord se gestiona automáticamente. Si el cese requiere bloqueo, aplica aquí los baneos que sigan pendientes en otros servicios. Si no se solicitó ban, este paso se omite automáticamente.');
        }

        $statusKey = $dismissal ? 'status_dismissed' : 'status_departed';
        $steps[] = $this->auto($statusKey, $dismissal ? 'Cambiar estado a CESADO' : 'Cambiar estado a BAJA', ['departure_validation'], $dismissal ? 'Cambia el estado del usuario a CESADO y registra el cambio en su historial.' : 'Cambia el estado del usuario a BAJA y registra el cambio en su historial.');
        $steps[] = $this->auto('treasury_player_sync', 'Actualizar Tesorería a Cesado', [$statusKey], 'En la hoja Jugadores, BAJA, CESADO y cualquier otro estado distinto de ACTIVO/RECLUTA/RESERVA se representan como Cesado.');
        $steps[] = $this->auto('google_sheets_status_sync', 'Actualizar el estado en Google Sheets', [$statusKey], 'Actualiza la fila del miembro en Google Sheets con el nuevo estado sin modificar columnas ajenas al sistema.');

        if ($dismissal) {
            $steps[] = $this->manual('dismissal_email', 'Enviar el correo de cese', ['status_dismissed'], 'Envía al usuario el correo de cese utilizando la plantilla vigente y marca el paso cuando el envío esté confirmado.');
        }

        return $steps;
    }

    private function auto(string $key, string $label, array $dependsOn = [], ?string $instructions = null): array
    {
        return $this->step($key, $label, MemberProcedureStep::KIND_AUTOMATIC, $dependsOn, $instructions);
    }

    private function manual(string $key, string $label, array $dependsOn = [], ?string $instructions = null): array
    {
        return $this->step($key, $label, MemberProcedureStep::KIND_MANUAL, $dependsOn, $instructions);
    }

    private function waiting(string $key, string $label, array $dependsOn = [], ?string $instructions = null): array
    {
        return $this->step($key, $label, MemberProcedureStep::KIND_WAITING, $dependsOn, $instructions);
    }

    private function step(string $key, string $label, string $kind, array $dependsOn, ?string $instructions): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'kind' => $kind,
            'required' => true,
            'depends_on' => $dependsOn,
            'instructions' => $instructions,
        ];
    }
}
