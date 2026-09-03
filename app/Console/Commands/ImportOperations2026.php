<?php

namespace App\Console\Commands;

use App\Models\Addon;
use App\Models\Faction;
use App\Models\GameMap;
use App\Models\ActivityDay;
use App\Models\ActivityStatus;
use App\Models\ActivityType;
use App\Models\Platform;
use App\Models\User;
use App\Models\SlotType;
use App\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class ImportOperations2026 extends Command
{
    protected $signature = 'operations:import-2026
        {file=storage/app/imports/misiones_2026_final.json : JSON de operativos}
        {--roles=storage/app/imports/grupos_roles.json : JSON de equivalencias de roles}
        {--dry-run : Validar todo sin guardar cambios}';

    protected $description =
        'Importa los operativos de 2026 desde el JSON del foro antiguo';

    public function handle(): int
    {
        $operationsPath = $this->resolvePath(
            (string) $this->argument('file')
        );

        $rolesPath = $this->resolvePath(
            (string) $this->option('roles')
        );

        /*
        |--------------------------------------------------------------------------
        | Comprobar archivos
        |--------------------------------------------------------------------------
        */

        if (! is_file($operationsPath)) {
            $this->error(
                "No se encontró el JSON de operativos: {$operationsPath}"
            );

            return self::FAILURE;
        }

        if (! is_file($rolesPath)) {
            $this->error(
                "No se encontró el JSON de grupos de roles: {$rolesPath}"
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Leer JSON
        |--------------------------------------------------------------------------
        */

        try {
            $operationsPayload =
                $this->readJson($operationsPath);

            $rolesPayload =
                $this->readJson($rolesPath);
        } catch (Throwable $exception) {
            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Obtener lista de misiones
        |--------------------------------------------------------------------------
        |
        | Nuestro JSON tiene:
        |
        | {
        |     "resumen": {...},
        |     "misiones": [...]
        | }
        |
        */

        $missions =
            $operationsPayload['misiones']
            ?? null;

        if (
            ! is_array($missions)
            || ! array_is_list($missions)
        ) {
            $this->error(
                'El JSON de operativos no contiene un array "misiones" válido.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Validar grupos de roles
        |--------------------------------------------------------------------------
        */

        if (
            ! is_array($rolesPayload)
            || array_is_list($rolesPayload)
        ) {
            $this->error(
                'grupos_roles.json debe ser un objeto donde cada clave sea un tipo de slot.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Construir mapa de equivalencias
        |--------------------------------------------------------------------------
        |
        | Ejemplo:
        |
        | "Fusilero HAT"
        |       ↓
        | "fusilero hat"
        |       ↓
        | "AT-AA"
        |
        */

        $roleMap = [];

        $duplicateAliases = [];

        foreach (
            $rolesPayload as
            $slotTypeName => $aliases
        ) {
            if (
                ! is_string($slotTypeName)
                || trim($slotTypeName) === ''
            ) {
                $this->error(
                    'Existe un tipo de slot sin nombre válido en grupos_roles.json.'
                );

                return self::FAILURE;
            }

            if (
                ! is_array($aliases)
                || ! array_is_list($aliases)
            ) {
                $this->error(
                    "El grupo '{$slotTypeName}' debe contener un array de nombres."
                );

                return self::FAILURE;
            }

            foreach ($aliases as $alias) {
                if (
                    ! is_string($alias)
                    || trim($alias) === ''
                ) {
                    continue;
                }

                $normalized =
                    $this->normalizeRole(
                        $alias
                    );

                if (
                    isset($roleMap[$normalized])
                    && $roleMap[$normalized] !== $slotTypeName
                ) {
                    $duplicateAliases[] = sprintf(
                        '"%s" aparece en "%s" y "%s"',
                        $alias,
                        $roleMap[$normalized],
                        $slotTypeName
                    );

                    continue;
                }

                $roleMap[$normalized] =
                    $slotTypeName;
            }
        }

        if ($duplicateAliases !== []) {
            $this->error(
                'Hay nombres de rol asignados a más de un grupo:'
            );

            foreach ($duplicateAliases as $message) {
                $this->line(
                    ' - '.$message
                );
            }

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Tipos de slot existentes en BD
        |--------------------------------------------------------------------------
        |
        | Los indexamos por nombre normalizado para que:
        |
        | "Mando global"    == "Mando Global"
        | "Líder de Equipo" == "Lider de Equipo"
        |
        | No modificamos ni el JSON ni los nombres existentes en BD.
        |
        */

        $databaseSlotTypes =
            SlotType::query()
                ->get([
                    'id',
                    'name',
                ])
                ->keyBy(
                    fn (SlotType $slotType): string =>
                        $this->normalizeLookup(
                            $slotType->name
                        )
                );

        $missingSlotTypes = [];

        /*
        * Este array será además el que utilizaremos
        * después durante la importación real.
        *
        * Ejemplo:
        *
        * "Líder de Equipo" => 14
        * "Mando global"    => 1
        */

        $slotTypeIds = [];

        foreach (
            array_keys($rolesPayload)
            as $slotTypeName
        ) {
            $normalizedSlotType =
                $this->normalizeLookup(
                    $slotTypeName
                );

            $databaseSlotType =
                $databaseSlotTypes
                    ->get(
                        $normalizedSlotType
                    );

            if (! $databaseSlotType) {
                $missingSlotTypes[] =
                    $slotTypeName;

                continue;
            }

            $slotTypeIds[
                $slotTypeName
            ] = (int) $databaseSlotType->id;
        }

        if ($missingSlotTypes !== []) {
            $this->error(
                'Faltan tipos de slot en la base de datos:'
            );

            foreach ($missingSlotTypes as $name) {
                $this->line(
                    ' - '.$name
                );
            }

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Cargar catálogos de NewSlot
        |--------------------------------------------------------------------------
        */

        $operationType =
            ActivityType::query()
                ->get()
                ->first(
                    fn (ActivityType $type): bool =>
                        $this->normalizeLookup($type->name)
                        === $this->normalizeLookup('OPERACIÓN')
                );

        if (! $operationType) {
            $this->error(
                'No existe el tipo de operación "OPERACIÓN".'
            );

            return self::FAILURE;
        }


        /*
        |--------------------------------------------------------------------------
        | Estado de los operativos importados
        |--------------------------------------------------------------------------
        |
        | MUY IMPORTANTE:
        |
        | Todas las misiones importadas entran SIEMPRE como BORRADOR.
        |
        | Nunca se debe activar automáticamente una misión importada.
        | Primero debe ser revisada manualmente desde Filament.
        |
        */

        $operationStatus =
            ActivityStatus::query()
                ->get()
                ->first(
                    fn (ActivityStatus $status): bool =>
                        $this->normalizeLookup($status->name)
                        === $this->normalizeLookup('BORRADOR')
                );

        if (! $operationStatus) {
            $this->error(
                'No existe el estado de operación "BORRADOR".'
            );

            return self::FAILURE;
        }


        /*
        |--------------------------------------------------------------------------
        | Índices normalizados
        |--------------------------------------------------------------------------
        */

        $platformIndex =
            Platform::query()
                ->get()
                ->keyBy(
                    fn (Platform $platform): string =>
                        $this->normalizePlatform(
                            $platform->name
                        )
                );


        $dayIndex =
            ActivityDay::query()
                ->get()
                ->keyBy(
                    fn (ActivityDay $day): string =>
                        $this->normalizeLookup(
                            $day->name
                        )
                );


        $mapIndex =
            GameMap::query()
                ->get()
                ->groupBy(
                    fn (GameMap $map): string =>
                        $this->normalizeLookup(
                            $map->name
                        )
                );


        $userIndex =
            User::query()
                ->get(['id', 'nick'])
                ->keyBy(
                    fn (User $user): string =>
                        $this->normalizeLookup(
                            $user->nick
                        )
                );


        $factionIndex =
            Faction::query()
                ->get(['id', 'name'])
                ->keyBy(
                    fn (Faction $faction): string =>
                        $this->normalizeLookup(
                            $faction->name
                        )
                );


        $addonIndex =
            Addon::query()
                ->get(['id', 'name'])
                ->keyBy(
                    fn (Addon $addon): string =>
                        $this->normalizeAddon(
                            $addon->name
                        )
                );
        
        /*
        |--------------------------------------------------------------------------
        | Registros fallback
        |--------------------------------------------------------------------------
        |
        | Todo mapa/facción que no podamos resolver automáticamente
        | se marcará como Unkown para revisión manual posterior.
        |
        */

        $unknownMaps =
            $mapIndex->get(
                $this->normalizeLookup('Unkown'),
                collect()
            );

        if ($unknownMaps->isEmpty()) {
            $this->error(
                'No existe ningún mapa llamado "Unkown".'
            );

            return self::FAILURE;
        }


        $unknownFaction =
            $factionIndex->get(
                $this->normalizeLookup('Unkown')
            );

        if (! $unknownFaction) {
            $this->error(
                'No existe ninguna facción llamada "Unkown".'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Analizar todas las misiones
        |--------------------------------------------------------------------------
        */

        $missionCount = 0;

        $groupCount = 0;

        $slotCount = 0;


        /*
        |--------------------------------------------------------------------------
        | Errores generales
        |--------------------------------------------------------------------------
        */

        $unclassifiedRoles = [];

        $invalidMissions = [];


        /*
        |--------------------------------------------------------------------------
        | Dependencias no encontradas
        |--------------------------------------------------------------------------
        */

        $missingPlatforms = [];

        $missingEditors = [];

        $missingDays = [];


        /*
        |--------------------------------------------------------------------------
        | Elementos que usarán fallback
        |--------------------------------------------------------------------------
        */

        $mapsUsingUnknown = [];

        $factionsUsingUnknown = [];


        /*
        |--------------------------------------------------------------------------
        | Addons pendientes de revisión manual
        |--------------------------------------------------------------------------
        */

        $missionsWithMissingAddons = [];


        /*
        |--------------------------------------------------------------------------
        | Contadores de referencias
        |--------------------------------------------------------------------------
        */

        $platformReferences = 0;

        $mapReferences = 0;

        $editorReferences = 0;

        $factionReferences = 0;

        $addonReferences = 0;

        $dayReferences = 0;

        foreach (
            $missions as
            $missionIndex => $mission
        ) {
            if (! is_array($mission)) {
                $invalidMissions[] =
                    'Misión '.($missionIndex + 1)
                    .': no es un objeto válido.';

                continue;
            }

            $missionName =
                trim(
                    (string) (
                        $mission['nombre']
                        ?? ''
                    )
                );

            if ($missionName === '') {
                $invalidMissions[] =
                    'Misión '.($missionIndex + 1)
                    .': no tiene nombre.';

                continue;
            }

            $missionCount++;
            /*
            |--------------------------------------------------------------------------
            | Plataforma
            |--------------------------------------------------------------------------
            */

            $platformName =
                trim(
                    (string) (
                        $mission['plataforma']
                        ?? ''
                    )
                );

            $platform = null;

            if ($platformName !== '') {
                $platformReferences++;

                $platformKey =
                    $this->normalizePlatform(
                        $platformName
                    );

                $platform =
                    $platformIndex
                        ->get($platformKey);

                if (! $platform) {
                    $missingPlatforms[
                        $platformKey
                    ] = $platformName;
                }
            } else {
                $missingPlatforms[
                    'mission-'.$missionIndex
                ] =
                    "{$missionName}: plataforma vacía";
            }


            /*
            |--------------------------------------------------------------------------
            | Mapa
            |--------------------------------------------------------------------------
            |
            | Si el mapa:
            |
            | - no viene informado
            | - no existe
            | - existe pero no pertenece a la plataforma
            |
            | usamos "Unkown".
            |
            | Esto NO bloquea la importación.
            |
            */

            $mapName =
                trim(
                    (string) (
                        $mission['mapa']
                        ?? ''
                    )
                );

            $map = null;

            if ($mapName !== '') {
                $mapReferences++;

                $mapKey =
                    $this->normalizeLookup(
                        $mapName
                    );

                $mapCandidates =
                    $mapIndex->get(
                        $mapKey,
                        collect()
                    );

                if ($platform) {
                    $map =
                        $mapCandidates
                            ->first(
                                fn (GameMap $candidate): bool =>
                                    (int) $candidate->platform_id
                                    === (int) $platform->id
                            );
                } else {
                    $map =
                        $mapCandidates->first();
                }
            }


            /*
            * Si no encontramos el mapa,
            * utilizamos Unkown.
            */

            if (! $map) {

                /*
                * Primero intentamos localizar un Unkown
                * correspondiente a la misma plataforma.
                */

                if ($platform) {
                    $map =
                        $unknownMaps
                            ->first(
                                fn (GameMap $candidate): bool =>
                                    (int) $candidate->platform_id
                                    === (int) $platform->id
                            );
                }

                /*
                * Si solo existe un Unkown general,
                * utilizamos ese.
                */

                if (! $map) {
                    $map =
                        $unknownMaps->first();
                }

                $mapsUsingUnknown[] =
                    $missionName
                    .' → '
                    .(
                        $mapName !== ''
                            ? $mapName
                            : '[MAPA VACÍO]'
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Editor
            |--------------------------------------------------------------------------
            */

            $editorName =
                trim(
                    (string) (
                        $mission['editor']
                        ?? ''
                    )
                );

            if ($editorName !== '') {
                $editorReferences++;

                $editorKey =
                    $this->normalizeLookup(
                        $editorName
                    );

                if (
                    ! $userIndex->has(
                        $editorKey
                    )
                ) {
                    $missingEditors[
                        $editorKey
                    ] = $editorName;
                }
            } else {
                $missingEditors[
                    'mission-'.$missionIndex
                ] =
                    "{$missionName}: editor vacío";
            }


            /*
            |--------------------------------------------------------------------------
            | Días
            |--------------------------------------------------------------------------
            */

            $missionDays =
                $mission['dias']
                ?? [];

            if (! is_array($missionDays)) {
                $invalidMissions[] =
                    "{$missionName}: dias no es un array.";

                $missionDays = [];
            }

            foreach ($missionDays as $dayName) {
                $dayName =
                    trim(
                        (string) $dayName
                    );

                if ($dayName === '') {
                    continue;
                }

                $dayReferences++;

                $dayKey =
                    $this->normalizeLookup(
                        $dayName
                    );

                if (! $dayIndex->has($dayKey)) {
                    $missingDays[
                        $dayKey
                    ] = $dayName;
                }
            }

            $orbat =
                $mission['orbat']
                ?? [];

            if (! is_array($orbat)) {
                $invalidMissions[] =
                    "{$missionName}: ORBAT no válido.";

                continue;
            }

            foreach ($orbat as $group) {
                if (! is_array($group)) {
                    continue;
                }

                $groupCount++;

                /*
                |--------------------------------------------------------------------------
                | Facción del grupo
                |--------------------------------------------------------------------------
                |
                | Si no podemos resolver la facción utilizamos Unkown.
                |
                */

                $groupName =
                    trim(
                        (string) (
                            $group['grupo']
                            ?? 'Grupo sin nombre'
                        )
                    );

                $factionName =
                    trim(
                        (string) (
                            $group['faccion']
                            ?? ''
                        )
                    );

                $faction = null;

                if ($factionName !== '') {
                    $factionReferences++;

                    $factionKey =
                        $this->normalizeLookup(
                            $factionName
                        );

                    $faction =
                        $factionIndex->get(
                            $factionKey
                        );
                }

                if (! $faction) {
                    $faction =
                        $unknownFaction;

                    $factionsUsingUnknown[] =
                        $missionName
                        .' / '
                        .$groupName
                        .' → '
                        .(
                            $factionName !== ''
                                ? $factionName
                                : '[SIN FACCIÓN]'
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Slots del grupo
                |--------------------------------------------------------------------------
                |
                | El campo "indice" se ignora completamente.
                | Solo utilizamos "nombre".
                |
                */

                $slots =
                    $group['slots']
                    ?? [];

                if (! is_array($slots)) {
                    $invalidMissions[] =
                        "{$missionName} / {$groupName}: slots no es un array.";

                    continue;
                }

                foreach ($slots as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $originalRoleName =
                        trim(
                            (string) (
                                $slot['nombre']
                                ?? ''
                            )
                        );

                    if ($originalRoleName === '') {
                        continue;
                    }

                    $slotCount++;

                    $normalizedRole =
                        $this->normalizeRole(
                            $originalRoleName
                        );

                    if (! isset($roleMap[$normalizedRole])) {
                        $unclassifiedRoles[
                            $normalizedRole
                        ] = $originalRoleName;
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Addons
            |--------------------------------------------------------------------------
            |
            | Los addons inexistentes NO bloquean la importación.
            | Se omiten y se informa de la misión para revisión manual.
            |
            */

            $missionAddons =
                $mission['addons']
                ?? [];

            if (! is_array($missionAddons)) {
                $invalidMissions[] =
                    "{$missionName}: addons no es un array.";

                $missionAddons = [];
            }

            foreach ($missionAddons as $addonName) {
                $addonName =
                    trim(
                        (string) $addonName
                    );

                if ($addonName === '') {
                    continue;
                }

                $addonReferences++;

                $addonKey =
                    $this->normalizeAddon(
                        $addonName
                    );

                if (! $addonIndex->has($addonKey)) {
                    $missionsWithMissingAddons[
                        $missionName
                    ][] = $addonName;
                }
            }

            if (
                isset(
                    $missionsWithMissingAddons[
                        $missionName
                    ]
                )
            ) {
                $missionsWithMissingAddons[
                    $missionName
                ] = array_values(
                    array_unique(
                        $missionsWithMissingAddons[
                            $missionName
                        ]
                    )
                );
            }
            }
        /*
        |--------------------------------------------------------------------------
        | Resultado
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->table(
            [
                'Comprobación',
                'Resultado',
            ],
            [
                [
                    'Misiones',
                    $missionCount,
                ],
                [
                    'Grupos ORBAT',
                    $groupCount,
                ],
                [
                    'Slots',
                    $slotCount,
                ],
                [
                    'Tipos de slot BD',
                    $databaseSlotTypes->count(),
                ],
                [
                    'Tipos definidos en JSON',
                    count($rolesPayload),
                ],
                [
                    'Roles sin clasificar',
                    count($unclassifiedRoles),
                ],
                [
                    'Referencias plataforma',
                    $platformReferences,
                ],
                [
                    'Plataformas sin encontrar',
                    count($missingPlatforms),
                ],
                [
                    'Referencias mapas',
                    $mapReferences,
                ],
                [
                    'Mapas usando Unkown',
                    count($mapsUsingUnknown),
                ],
                [
                    'Referencias editores',
                    $editorReferences,
                ],
                [
                    'Editores sin encontrar',
                    count($missingEditors),
                ],
                [
                    'Referencias facciones',
                    $factionReferences,
                ],
                [
                    'Facciones usando Unkown',
                    count($factionsUsingUnknown),
                ],
                [
                    'Referencias addons',
                    $addonReferences,
                ],
                [
                    'Misiones con addons pendientes',
                    count($missionsWithMissingAddons),
                ],
                [
                    'Referencias días',
                    $dayReferences,
                ],
                [
                    'Días sin encontrar',
                    count($missingDays),
                ],
                [
                    'Misiones con errores',
                    count($invalidMissions),
                ],
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Mostrar errores de misiones
        |--------------------------------------------------------------------------
        */

        if ($invalidMissions !== []) {
            $this->newLine();

            $this->warn(
                'Problemas encontrados en las misiones:'
            );

            foreach ($invalidMissions as $message) {
                $this->line(
                    ' - '.$message
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Mostrar roles sin clasificar
        |--------------------------------------------------------------------------
        */

        if ($unclassifiedRoles !== []) {
            $this->newLine();

            $this->error(
                'ROLES ENCONTRADOS PERO NO CLASIFICADOS:'
            );

            foreach (
                $unclassifiedRoles
                as $role
            ) {
                $this->line(
                    ' - '.$role
                );
            }
        }
        $this->printMissing(
            'PLATAFORMAS NO ENCONTRADAS',
            $missingPlatforms
        );

        $this->printMissing(
            'EDITORES NO ENCONTRADOS',
            $missingEditors
        );

        $this->printWarning(
            'MAPAS QUE USARÁN "Unkown"',
            $mapsUsingUnknown
        );

        $this->printWarning(
            'FACCIONES QUE USARÁN "Unkown"',
            $factionsUsingUnknown
        );

        $this->printMissing(
            'DÍAS NO ENCONTRADOS',
            $missingDays
        );

        if ($missionsWithMissingAddons !== []) {
            $this->newLine();

            $this->warn(
                'MISIONES CON ADDONS PENDIENTES:'
            );

            foreach (
                $missionsWithMissingAddons
                as $missionName => $addons
            ) {
                $this->line(
                    ' - '.$missionName
                );

                foreach ($addons as $addon) {
                    $this->line(
                        '     · '.$addon
                    );
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Fallar si algo no cuadra
        |--------------------------------------------------------------------------
        */

        if (
            $invalidMissions !== []
            || $unclassifiedRoles !== []
            || $missingPlatforms !== []
            || $missingEditors !== []
            || $missingDays !== []
        ) {
            $this->newLine();

            $this->error(
                'La validación ha fallado. No se ha importado nada.'
            );

            return self::FAILURE;
        }

        /*
        |--------------------------------------------------------------------------
        | Validación correcta
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->info(
            '✓ JSON de operativos válido.'
        );

        $this->info(
            '✓ JSON de grupos de roles válido.'
        );

        $this->info(
            '✓ Todos los roles del ORBAT tienen un SlotType.'
        );

        $this->info(
            '✓ El campo "indice" del ORBAT se ignora completamente.'
        );

        /*
        |--------------------------------------------------------------------------
        | Dry run
        |--------------------------------------------------------------------------
        */

        if ($this->option('dry-run')) {
            $this->newLine();

            $this->info(
                'DRY RUN terminado. No se guardaron cambios.'
            );

            return self::SUCCESS;
        }


        /*
        |--------------------------------------------------------------------------
        | IMPORTACIÓN REAL
        |--------------------------------------------------------------------------
        |
        | A partir de aquí sí escribimos.
        |
        | Toda la importación se ejecuta dentro de UNA transacción.
        | Si falla una misión, se revierte absolutamente todo.
        |
        */

        $this->newLine();

        $this->warn(
            'Comenzando importación real...'
        );

        $this->line(
            'Todas las operaciones se crearán como BORRADOR.'
        );

        $this->newLine();


        $createdOperations = 0;

        $skippedOperations = 0;

        $importedGroups = 0;

        $importedSlots = 0;


        try {

            DB::beginTransaction();

            foreach ($missions as $mission) {

                $missionName =
                    trim(
                        (string) (
                            $mission['nombre']
                            ?? ''
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | Evitar duplicados
                |--------------------------------------------------------------------------
                |
                | Si ya existe una operación con ese nombre,
                | incluso eliminada con SoftDeletes,
                | NO la tocamos.
                |
                */

                $alreadyExists =
                    Activity::withTrashed()
                        ->where(
                            'name',
                            $missionName
                        )
                        ->exists();

                if ($alreadyExists) {
                    $this->warn(
                        "↷ OMITIDA: {$missionName} (ya existe)"
                    );

                    $skippedOperations++;

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Plataforma
                |--------------------------------------------------------------------------
                */

                $platformName =
                    trim(
                        (string) (
                            $mission['plataforma']
                            ?? ''
                        )
                    );

                $platform =
                    $platformIndex->get(
                        $this->normalizePlatform(
                            $platformName
                        )
                    );

                if (! $platform) {
                    throw new RuntimeException(
                        "{$missionName}: no se pudo resolver la plataforma."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Mapa
                |--------------------------------------------------------------------------
                */

                $mapName =
                    trim(
                        (string) (
                            $mission['mapa']
                            ?? ''
                        )
                    );

                $map = null;

                if ($mapName !== '') {

                    $mapCandidates =
                        $mapIndex->get(
                            $this->normalizeLookup(
                                $mapName
                            ),
                            collect()
                        );

                    $map =
                        $mapCandidates
                            ->first(
                                fn (GameMap $candidate): bool =>
                                    (int) $candidate->platform_id
                                    === (int) $platform->id
                            );
                }


                /*
                * Mapa inexistente o vacío:
                * usamos Unkown.
                */

                if (! $map) {

                    $map =
                        $unknownMaps
                            ->first(
                                fn (GameMap $candidate): bool =>
                                    (int) $candidate->platform_id
                                    === (int) $platform->id
                            );

                    if (! $map) {
                        $map =
                            $unknownMaps->first();
                    }
                }

                if (! $map) {
                    throw new RuntimeException(
                        "{$missionName}: no se pudo resolver ni siquiera el mapa Unkown."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Editor
                |--------------------------------------------------------------------------
                */

                $editorName =
                    trim(
                        (string) (
                            $mission['editor']
                            ?? ''
                        )
                    );

                $editor =
                    $userIndex->get(
                        $this->normalizeLookup(
                            $editorName
                        )
                    );

                if (! $editor) {
                    throw new RuntimeException(
                        "{$missionName}: editor '{$editorName}' no encontrado."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Briefing
                |--------------------------------------------------------------------------
                */

                $descriptionSections = [];

                foreach (
                    $mission['briefing'] ?? []
                    as $section
                ) {
                    if (! is_array($section)) {
                        continue;
                    }

                    $title =
                        trim(
                            (string) (
                                $section['titulo']
                                ?? ''
                            )
                        );

                    $content =
                        trim(
                            (string) (
                                $section['contenido']
                                ?? ''
                            )
                        );

                    if (
                        $title === ''
                        && $content === ''
                    ) {
                        continue;
                    }

                    /*
                    * El editor actual trabaja con HTML.
                    *
                    * Escapamos el texto antiguo y convertimos
                    * los saltos de línea a <br>.
                    */

                    $descriptionSections[] = [
                        'title' =>
                            $title !== ''
                                ? $title
                                : 'Descripción',

                        'content' =>
                            nl2br(
                                e($content),
                                false
                            ),
                    ];
                }


                /*
                |--------------------------------------------------------------------------
                | ORBAT
                |--------------------------------------------------------------------------
                */

                $orbatGroups = [];

                foreach (
                    $mission['orbat'] ?? []
                    as $group
                ) {
                    if (! is_array($group)) {
                        continue;
                    }

                    /*
                    * IMPORTANTE:
                    *
                    * "grupo" es:
                    *
                    * UNIFORM 1-1
                    * ALPHA 2-2
                    * BRAVO 3-1
                    * etc.
                    */

                    $groupName =
                        trim(
                            (string) (
                                $group['grupo']
                                ?? 'Grupo sin nombre'
                            )
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Facción
                    |--------------------------------------------------------------------------
                    */

                    $factionName =
                        trim(
                            (string) (
                                $group['faccion']
                                ?? ''
                            )
                        );

                    $faction = null;

                    if ($factionName !== '') {
                        $faction =
                            $factionIndex->get(
                                $this->normalizeLookup(
                                    $factionName
                                )
                            );
                    }

                    if (! $faction) {
                        $faction =
                            $unknownFaction;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Slots
                    |--------------------------------------------------------------------------
                    */

                    $orbatSlots = [];

                    foreach (
                        $group['slots'] ?? []
                        as $slot
                    ) {
                        if (! is_array($slot)) {
                            continue;
                        }

                        /*
                        * ÚNICO dato que usamos del slot antiguo.
                        *
                        * El índice se ignora completamente.
                        */

                        $slotName =
                            trim(
                                (string) (
                                    $slot['nombre']
                                    ?? ''
                                )
                            );

                        if ($slotName === '') {
                            continue;
                        }

                        $normalizedRole =
                            $this->normalizeRole(
                                $slotName
                            );

                        $canonicalSlotType =
                            $roleMap[
                                $normalizedRole
                            ]
                            ?? null;

                        if (! $canonicalSlotType) {
                            throw new RuntimeException(
                                "{$missionName} / {$groupName}: "
                                ."rol '{$slotName}' sin clasificación."
                            );
                        }

                        $slotTypeId =
                            $slotTypeIds[
                                $canonicalSlotType
                            ]
                            ?? null;

                        if (! $slotTypeId) {
                            throw new RuntimeException(
                                "{$missionName} / {$groupName}: "
                                ."no se encontró el SlotType "
                                ."'{$canonicalSlotType}'."
                            );
                        }

                        $orbatSlots[] = [
                            'slot_type_id' =>
                                (int) $slotTypeId,

                            'name' =>
                                $slotName,

                            'visible' =>
                                true,
                        ];

                        $importedSlots++;
                    }


                    $orbatGroups[] = [
                        'name' =>
                            $groupName,

                        'faction_id' =>
                            (int) $faction->id,

                        'visible' =>
                            true,

                        'slots' =>
                            $orbatSlots,
                    ];

                    $importedGroups++;
                }


                /*
                |--------------------------------------------------------------------------
                | Addons
                |--------------------------------------------------------------------------
                |
                | Solo añadimos addons que existen.
                |
                | Los que faltaron en el dry-run:
                |
                | - simplemente se omiten
                | - ya sabemos qué misiones debemos revisar
                |
                */

                $addonIds = [];

                foreach (
                    $mission['addons'] ?? []
                    as $addonName
                ) {
                    $addonName =
                        trim(
                            (string) $addonName
                        );

                    if ($addonName === '') {
                        continue;
                    }

                    $addon =
                        $addonIndex->get(
                            $this->normalizeAddon(
                                $addonName
                            )
                        );

                    if (! $addon) {
                        continue;
                    }

                    $addonIds[] =
                        (int) $addon->id;
                }

                $addonIds =
                    array_values(
                        array_unique(
                            $addonIds
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | Radios
                |--------------------------------------------------------------------------
                */

                $radioNetworks = [];

                foreach (
                    $mission['canales_radio'] ?? []
                    as $radio
                ) {
                    if (! is_array($radio)) {
                        continue;
                    }

                    $radioNetworks[] =
                        $this->buildRadioNetwork(
                            $radio
                        );
                }


                /*
                |--------------------------------------------------------------------------
                | Fecha original
                |--------------------------------------------------------------------------
                */

                $createdAt = now();

                if (
                    filled(
                        $mission['fecha_creacion']
                        ?? null
                    )
                ) {
                    try {
                        $createdAt =
                            Carbon::parse(
                                $mission[
                                    'fecha_creacion'
                                ]
                            );
                    } catch (Throwable) {
                        /*
                        * Si una fecha concreta estuviese corrupta,
                        * usamos la fecha actual.
                        */
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Crear Activity
                |--------------------------------------------------------------------------
                */

                $operation =
                    new Activity([
                        'operation_type_id' =>
                            (int) $operationType->id,

                        /*
                        * SIEMPRE BORRADOR.
                        */
                        'operation_status_id' =>
                            (int) $operationStatus->id,

                        'name' =>
                            $missionName,

                        'description' => [
                            'sections' =>
                                $descriptionSections,
                        ],

                        'radio' => [
                            'networks' =>
                                $radioNetworks,
                        ],

                        'orbat' => [
                            'groups' =>
                                $orbatGroups,
                        ],

                        'addons' => [
                            'addon_ids' =>
                                $addonIds,
                        ],

                        'ocap' =>
                            (bool) (
                                $mission['ocap']
                                ?? false
                            ),

                        'jip' =>
                            (bool) (
                                $mission['jip']
                                ?? false
                            ),

                        /*
                        * El JSON final no contiene respawn.
                        * Lo dejamos inicialmente a false para
                        * revisión manual.
                        */
                        'respawn' =>
                            false,

                        'platform_id' =>
                            (int) $platform->id,

                        'map_id' =>
                            (int) $map->id,

                        'editor_id' =>
                            (int) $editor->id,

                        /*
                        * Para el histórico usamos el propio
                        * editor también como creador/modificador.
                        */
                        'created_by' =>
                            (int) $editor->id,

                        'updated_by' =>
                            (int) $editor->id,
                    ]);


                /*
                * Preservamos fecha de creación del foro.
                */

                $operation->created_at =
                    $createdAt;

                $operation->updated_at =
                    $createdAt;


                /*
                * saveQuietly evita disparar efectos secundarios
                * innecesarios durante una migración histórica.
                */

                $operation->saveQuietly();


                /*
                |--------------------------------------------------------------------------
                | Días
                |--------------------------------------------------------------------------
                */

                $dayIds = [];

                foreach (
                    $mission['dias'] ?? []
                    as $dayName
                ) {
                    $day =
                        $dayIndex->get(
                            $this->normalizeLookup(
                                (string) $dayName
                            )
                        );

                    if ($day) {
                        $dayIds[] =
                            (int) $day->id;
                    }
                }

                $operation
                    ->days()
                    ->sync(
                        array_values(
                            array_unique(
                                $dayIds
                            )
                        )
                    );


                $createdOperations++;

                $this->info(
                    sprintf(
                        '✓ %s | %d grupos | %d slots',
                        $missionName,
                        count($orbatGroups),
                        collect($orbatGroups)
                            ->sum(
                                fn (array $group): int =>
                                    count(
                                        $group['slots']
                                        ?? []
                                    )
                            )
                    )
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Todo correcto
            |--------------------------------------------------------------------------
            */

            DB::commit();

        } catch (Throwable $exception) {

            DB::rollBack();

            $this->newLine();

            $this->error(
                'ERROR DURANTE LA IMPORTACIÓN.'
            );

            $this->error(
                $exception->getMessage()
            );

            $this->newLine();

            $this->warn(
                'Se ha hecho ROLLBACK. No se ha guardado ninguna de las operaciones de esta ejecución.'
            );

            return self::FAILURE;
        }


        /*
        |--------------------------------------------------------------------------
        | Resumen
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->table(
            [
                'Importación',
                'Resultado',
            ],
            [
                [
                    'Operaciones creadas',
                    $createdOperations,
                ],
                [
                    'Operaciones omitidas',
                    $skippedOperations,
                ],
                [
                    'Grupos ORBAT importados',
                    $importedGroups,
                ],
                [
                    'Slots importados',
                    $importedSlots,
                ],
                [
                    'Estado',
                    'BORRADOR',
                ],
            ]
        );

        $this->newLine();

        $this->info(
            'IMPORTACIÓN TERMINADA CORRECTAMENTE.'
        );

        if ($missionsWithMissingAddons !== []) {

            $this->newLine();

            $this->warn(
                'Recuerda revisar manualmente los addons pendientes indicados anteriormente.'
            );
        }

        return self::SUCCESS;
    }
    /*
    |--------------------------------------------------------------------------
    | Leer JSON
    |--------------------------------------------------------------------------
    */

    private function readJson(
        string $path
    ): array {
        try {
            $contents =
                file_get_contents(
                    $path
                );

            if ($contents === false) {
                throw new RuntimeException(
                    "No se pudo leer: {$path}"
                );
            }

            $decoded =
                json_decode(
                    $contents,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            if (! is_array($decoded)) {
                throw new RuntimeException(
                    "El JSON no contiene una estructura válida: {$path}"
                );
            }

            return $decoded;
        } catch (JsonException $exception) {
            throw new RuntimeException(
                "JSON inválido en {$path}: "
                .$exception->getMessage(),
                previous: $exception
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Normalizar nombre de rol
    |--------------------------------------------------------------------------
    |
    | Esto hace equivalentes:
    |
    | Fusilero Automático
    | fusilero automático
    | FUSILERO AUTOMATICO
    |
    */

    private function normalizeRole(
        string $role
    ): string {
        $role =
            Str::of($role)
                ->trim()
                ->lower()
                ->ascii()
                ->replaceMatches(
                    '/\s+/',
                    ' '
                )
                ->toString();

        return $role;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolver ruta
    |--------------------------------------------------------------------------
    */

    private function resolvePath(
        string $file
    ): string {
        $isWindowsAbsolute =
            preg_match(
                '/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2})/',
                $file
            ) === 1;

        if (
            $isWindowsAbsolute
            || str_starts_with(
                $file,
                DIRECTORY_SEPARATOR
            )
        ) {
            return $file;
        }

        return base_path($file);
    }
    /*
    |--------------------------------------------------------------------------
    | Normalización genérica
    |--------------------------------------------------------------------------
    */

    private function normalizeLookup(
        string $value
    ): string {
        return Str::of($value)
            ->trim()
            ->lower()
            ->ascii()
            ->replace(
                ['_', '-'],
                ' '
            )
            ->replaceMatches(
                '/\s+/',
                ' '
            )
            ->toString();
    }


    /*
    |--------------------------------------------------------------------------
    | Normalización de addons
    |--------------------------------------------------------------------------
    |
    | Conservamos caracteres como:
    |
    | @
    | -
    | _
    |
    | porque forman parte real del nombre del addon.
    |
    */

    private function normalizeAddon(
        string $value
    ): string {
        return Str::of($value)
            ->trim()
            ->lower()
            ->ascii()
            ->replaceMatches(
                '/\s+/',
                ' '
            )
            ->toString();
    }


    /*
    |--------------------------------------------------------------------------
    | Imprimir elementos ausentes
    |--------------------------------------------------------------------------
    */

    private function printMissing(
        string $title,
        array $items
    ): void {
        if ($items === []) {
            return;
        }

        $this->newLine();

        $this->error(
            $title.':'
        );

        foreach ($items as $item) {
            $this->line(
                ' - '.$item
            );
        }
    }

    private function printWarning(
        string $title,
        array $items
    ): void {
        if ($items === []) {
            return;
        }

        $this->newLine();

        $this->warn(
            $title.':'
        );

        foreach ($items as $item) {
            $this->line(
                ' - '.$item
            );
        }
    }
    /*
    |--------------------------------------------------------------------------
    | Normalización de plataforma
    |--------------------------------------------------------------------------
    |
    | En los JSON antiguos aparece:
    |
    | Arma Reforger
    |
    | Pero en NewSlot la plataforma se llama:
    |
    | Reforger
    |
    */

    private function normalizePlatform(
        string $value
    ): string {
        $normalized =
            $this->normalizeLookup(
                $value
            );

        return match ($normalized) {
            'arma reforger' => 'reforger',

            default => $normalized,
        };
    }
    /*
    |--------------------------------------------------------------------------
    | Convertir canal de radio antiguo
    |--------------------------------------------------------------------------
    |
    | Ejemplo antiguo:
    |
    | {
    |     "canal": "GLOBAL TIERRA",
    |     "configuracion": "Frecuencia 30.00 MHz AN/PRC-77"
    | }
    |
    */

    private function buildRadioNetwork(
        array $radio
    ): array {
        $name =
            trim(
                (string) (
                    $radio['canal']
                    ?? 'Red sin nombre'
                )
            );

        $rawConfiguration =
            trim(
                (string) (
                    $radio['configuracion']
                    ?? ''
                )
            );

        $frequency = null;

        $radioModel = '';

        $notes = '';


        /*
        * Intentamos interpretar:
        *
        * Frecuencia 30.00 MHz AN/PRC-77
        */

        if (
            preg_match(
                '/Frecuencia\s+([0-9]+(?:[.,][0-9]+)?)\s*MHz(?:\s+(.+))?/iu',
                $rawConfiguration,
                $matches
            )
        ) {
            $frequency =
                str_replace(
                    ',',
                    '.',
                    $matches[1]
                );

            $radioModel =
                trim(
                    (string) (
                        $matches[2]
                        ?? ''
                    )
                );
        } else {
            /*
            * Si no sabemos interpretar la configuración,
            * conservamos el texto completo en Notas.
            */

            $notes =
                $rawConfiguration;
        }


        $configuration = [];

        if ($frequency !== null) {
            $configuration[
                'frequency'
            ] = $frequency;
        }


        return [
            'name' =>
                $name,

            'radio_model_name' =>
                $radioModel,

            'configuration' =>
                $configuration,

            'notes' =>
                $notes,

            'visible' =>
                true,
        ];
    }
}