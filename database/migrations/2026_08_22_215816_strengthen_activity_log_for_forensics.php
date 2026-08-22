<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            /*
             * Permite agrupar todos los cambios producidos
             * por una misma petición.
             */
            $table->uuid('correlation_id')
                ->nullable()
                ->after('url');

            /*
             * web | api | console | system
             */
            $table->string('source', 20)
                ->nullable()
                ->after('correlation_id');

            $table->string('request_method', 10)
                ->nullable()
                ->after('source');

            $table->string('route_name')
                ->nullable()
                ->after('request_method');

            /*
             * Copia histórica del nick.
             *
             * Aunque el usuario cambie de nick o sea eliminado,
             * sabremos quién realizó la acción.
             */
            $table->string('actor_nick')
                ->nullable()
                ->after('route_name');

            /*
             * Información histórica del objeto afectado.
             */
            $table->string('subject_table')
                ->nullable()
                ->after('actor_nick');

            $table->string('subject_label')
                ->nullable()
                ->after('subject_table');

            /*
             * Índices útiles para investigaciones.
             */
            $table->index(
                'created_at',
                'activity_log_created_at_idx'
            );

            $table->index(
                'event',
                'activity_log_event_idx'
            );

            $table->index(
                'ip_address',
                'activity_log_ip_address_idx'
            );

            $table->index(
                'correlation_id',
                'activity_log_correlation_id_idx'
            );

            $table->index(
                'actor_nick',
                'activity_log_actor_nick_idx'
            );

            $table->index(
                'subject_table',
                'activity_log_subject_table_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex('activity_log_created_at_idx');
            $table->dropIndex('activity_log_event_idx');
            $table->dropIndex('activity_log_ip_address_idx');
            $table->dropIndex('activity_log_correlation_id_idx');
            $table->dropIndex('activity_log_actor_nick_idx');
            $table->dropIndex('activity_log_subject_table_idx');

            $table->dropColumn([
                'correlation_id',
                'source',
                'request_method',
                'route_name',
                'actor_nick',
                'subject_table',
                'subject_label',
            ]);
        });
    }
};