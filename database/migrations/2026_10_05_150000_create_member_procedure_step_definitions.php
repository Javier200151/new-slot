<?php

use App\Models\MemberProcedure;
use App\Models\MemberProcedureStep;
use App\Services\MemberProcedures\MemberProcedureRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_procedure_step_definitions')) {
            Schema::create('member_procedure_step_definitions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_procedure_setting_id');
                $table->string('procedure_type', 48);
                $table->string('step_key', 80);
                $table->string('label', 180);
                $table->text('instructions')->nullable();
                $table->string('kind', 24)->default('manual');
                $table->unsignedSmallInteger('position')->default(0);
                $table->boolean('required')->default(true);
                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_system')->default(false);
                $table->json('depends_on')->nullable();
                $table->timestamps();

                $table->foreign('member_procedure_setting_id', 'mpsd_setting_fk')
                    ->references('id')->on('member_procedure_settings')->cascadeOnDelete();
                $table->unique(['member_procedure_setting_id', 'procedure_type', 'step_key'], 'mpsd_setting_type_key_uq');
                $table->index(['procedure_type', 'position'], 'mpsd_type_position_idx');
            });
        }

        if (DB::connection()->pretending()) {
            return;
        }

        /** @var MemberProcedureRegistry $registry */
        $registry = app(MemberProcedureRegistry::class);
        $registry->seedStoredDefinitions();

        // Los procedimientos ya abiertos conservan su snapshot, pero rellenamos las
        // instrucciones que estaban vacías para que las tarjetas actuales sean útiles.
        $defaults = $registry->defaultDefinitions();

        MemberProcedure::query()
            ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR])
            ->with('steps')
            ->chunkById(100, function ($procedures) use ($defaults): void {
                foreach ($procedures as $procedure) {
                    $byKey = collect($defaults[$procedure->type]['steps'] ?? [])->keyBy('key');

                    foreach ($procedure->steps as $step) {
                        $definition = $byKey->get($step->step_key);
                        if (! $definition) {
                            continue;
                        }

                        $meta = $step->meta ?? [];
                        if (filled(data_get($meta, 'instructions'))) {
                            continue;
                        }

                        $meta['instructions'] = $definition['instructions'] ?? null;
                        $step->forceFill(['meta' => $meta])->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_procedure_step_definitions');
    }
};
