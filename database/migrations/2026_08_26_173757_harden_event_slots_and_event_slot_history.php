<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Comprobaciones previas
        |--------------------------------------------------------------------------
        |
        | No intentamos arreglar datos automáticamente.
        | Si ya existen incoherencias, detenemos la migración para
        | revisarlas conscientemente antes de crear restricciones.
        |
        */

        $duplicatedUsers = DB::table('event_slots')
            ->select(
                'event_id',
                'user_id'
            )
            ->whereNotNull('user_id')
            ->groupBy(
                'event_id',
                'user_id'
            )
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicatedUsers > 0) {
            throw new RuntimeException(
                'No se puede endurecer event_slots: '
                . 'existen usuarios asignados más de una vez '
                . 'dentro del mismo evento.'
            );
        }


        $invalidOccupants = DB::table('event_slots')
            ->whereNotNull('user_id')
            ->whereNotNull('ally_id')
            ->count();

        if ($invalidOccupants > 0) {
            throw new RuntimeException(
                'No se puede endurecer event_slots: '
                . 'existen slots que tienen simultáneamente '
                . 'user_id y ally_id.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Un usuario SQA solo puede ocupar un slot por evento
        |--------------------------------------------------------------------------
        |
        | MySQL permite múltiples NULL dentro de un UNIQUE, por lo que
        | los EventSlot vacíos continúan siendo perfectamente válidos.
        |
        */

        Schema::table(
            'event_slots',
            function (Blueprint $table): void {
                $table->unique(
                    [
                        'event_id',
                        'user_id',
                    ],
                    'event_slots_event_user_unique'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Un slot no puede pertenecer a usuario y aliado a la vez
        |--------------------------------------------------------------------------
        |
        | MySQL no permite utilizar estas columnas en un CHECK porque
        | participan en foreign keys con acciones referenciales.
        |
        | Lo protegemos mediante triggers a nivel de base de datos.
        |
        */

        DB::unprepared(
            '
            CREATE TRIGGER event_slots_single_occupant_before_insert
            BEFORE INSERT ON event_slots
            FOR EACH ROW
            BEGIN
                IF NEW.user_id IS NOT NULL
                AND NEW.ally_id IS NOT NULL THEN

                    SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT =
                        \'Un slot no puede tener user_id y ally_id simultáneamente\';

                END IF;
            END
            '
        );

        DB::unprepared(
            '
            CREATE TRIGGER event_slots_single_occupant_before_update
            BEFORE UPDATE ON event_slots
            FOR EACH ROW
            BEGIN
                IF NEW.user_id IS NOT NULL
                AND NEW.ally_id IS NOT NULL THEN

                    SIGNAL SQLSTATE \'45000\'
                    SET MESSAGE_TEXT =
                        \'Un slot no puede tener user_id y ally_id simultáneamente\';

                END IF;
            END
            '
        );


        /*
        |--------------------------------------------------------------------------
        | Índice para el histórico público del evento
        |--------------------------------------------------------------------------
        |
        | La vista consulta:
        |
        | WHERE event_id = ?
        | ORDER BY created_at ...
        |
        */

        Schema::table(
            'event_slot_history',
            function (Blueprint $table): void {
                $table->index(
                    [
                        'event_id',
                        'created_at',
                    ],
                    'event_slot_history_event_created_index'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'event_slot_history',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'event_slot_history_event_created_index'
                );
            }
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS event_slots_single_occupant_before_insert'
        );

        DB::unprepared(
            'DROP TRIGGER IF EXISTS event_slots_single_occupant_before_update'
        );

        Schema::table(
            'event_slots',
            function (Blueprint $table): void {
                $table->dropUnique(
                    'event_slots_event_user_unique'
                );
            }
        );
    }
};