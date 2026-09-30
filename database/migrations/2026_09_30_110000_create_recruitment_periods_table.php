<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_periods', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /*
             * Mientras el periodo está abierto contiene el mismo ID que user_id.
             * Al cerrarlo pasa a NULL. UNIQUE permite múltiples NULL en MySQL y
             * garantiza a nivel de BD que un usuario no tenga dos periodos abiertos.
             */
            $table->foreignId('open_user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('period_number');

            $table->foreignId('tutor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('process_status', 40)->default('PENDING_TUTOR');
            $table->string('result', 30)->nullable();

            $table->string('tutorials_status', 20)->default('NO');
            $table->string('diary_rating', 30)->nullable();

            $table->boolean('official_events_allowed')->default(false);
            $table->text('current_note')->nullable();

            $table->timestamp('promotion_pending_at')->nullable();
            $table->foreignId('promotion_pending_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->string('started_at_source', 40)->default('status_transition');
            $table->timestamp('ended_at')->nullable();

            $table->foreignId('final_status_id')
                ->nullable()
                ->constrained('status')
                ->restrictOnDelete();
            $table->string('final_status_name')->nullable();

            $table->unsignedInteger('events_played_final')->nullable();

            /* Snapshots mínimos para que el histórico sobreviva a cambios futuros. */
            $table->string('user_nick_snapshot')->nullable();
            $table->string('tutor_nick_snapshot')->nullable();

            $table->foreignId('closed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'period_number'], 'recruitment_period_user_number_uq');
            $table->index(['user_id', 'ended_at'], 'recruitment_period_user_end_idx');
            $table->index(['process_status', 'ended_at'], 'recruitment_period_status_end_idx');
            $table->index('result', 'recruitment_period_result_idx');
            $table->index('promotion_pending_at', 'recruitment_period_promotion_idx');
        });

        $this->bootstrapCurrentRecruits();
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_periods');
    }

    private function bootstrapCurrentRecruits(): void
    {
        $recruitStatusId = DB::table('status')
            ->whereRaw('UPPER(TRIM(name)) = ?', ['RECLUTA'])
            ->value('id');

        if (! $recruitStatusId) {
            return;
        }

        $now = now();

        DB::table('users')
            ->where('status_id', $recruitStatusId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'nick', 'tutor_id'])
            ->each(function (object $user) use ($now): void {
                DB::table('recruitment_periods')->insert([
                    'user_id' => $user->id,
                    'open_user_id' => $user->id,
                    'period_number' => 1,
                    // Compatibilidad inicial: conservamos el tutor actualmente
                    // asignado en users, pero los siguientes periodos empiezan limpios.
                    'tutor_id' => $user->tutor_id,
                    'process_status' => $user->tutor_id ? 'IN_PROGRESS' : 'PENDING_TUTOR',
                    'result' => null,
                    'tutorials_status' => 'NO',
                    'diary_rating' => null,
                    'official_events_allowed' => false,
                    'current_note' => null,
                    'promotion_pending_at' => null,
                    'promotion_pending_by' => null,
                    // Para reclutas ya existentes no inferimos una fecha histórica.
                    // Se ajustará manualmente mientras el periodo permanezca abierto.
                    'started_at' => null,
                    'started_at_source' => 'manual_pending',
                    'ended_at' => null,
                    'final_status_id' => null,
                    'final_status_name' => null,
                    'events_played_final' => null,
                    'user_nick_snapshot' => null,
                    'tutor_nick_snapshot' => null,
                    'closed_by_user_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

};
