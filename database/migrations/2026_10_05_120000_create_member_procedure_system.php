<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_procedure_settings')) {
            Schema::create('member_procedure_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('alpha_metopa_id')->nullable();
                $table->unsignedBigInteger('treasury_group_id')->nullable();
                $table->unsignedBigInteger('tutors_group_id')->nullable();
                $table->string('google_spreadsheet_id', 160)->nullable();
                $table->string('google_general_sheet_gid', 32)->nullable();
                $table->string('armasquads_squad_id', 80)->nullable();
                $table->timestamps();

                $table->foreign('alpha_metopa_id', 'mps_alpha_metopa_fk')
                    ->references('id')->on('metopas')->nullOnDelete();
                $table->foreign('treasury_group_id', 'mps_treasury_group_fk')
                    ->references('id')->on('sqa_groups')->nullOnDelete();
                $table->foreign('tutors_group_id', 'mps_tutors_group_fk')
                    ->references('id')->on('sqa_groups')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('member_procedures')) {
            Schema::create('member_procedures', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('type', 48);
                $table->string('status', 24)->default('in_progress');
                $table->json('input')->nullable();
                $table->unsignedBigInteger('started_by_user_id')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->timestamps();

                $table->foreign('user_id', 'mp_user_fk')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('started_by_user_id', 'mp_started_by_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['user_id', 'status'], 'mp_user_status_idx');
                $table->index(['type', 'status'], 'mp_type_status_idx');
            });
        }

        if (! Schema::hasTable('member_procedure_steps')) {
            Schema::create('member_procedure_steps', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_procedure_id');
                $table->string('step_key', 80);
                $table->string('label', 180);
                $table->string('kind', 24);
                $table->string('status', 24);
                $table->unsignedSmallInteger('position')->default(0);
                $table->boolean('required')->default(true);
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->json('meta')->nullable();
                $table->json('result')->nullable();
                $table->text('last_error')->nullable();
                $table->unsignedBigInteger('completed_by_user_id')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('member_procedure_id', 'mps_proc_fk')
                    ->references('id')->on('member_procedures')->cascadeOnDelete();
                $table->foreign('completed_by_user_id', 'mps_completed_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->unique(['member_procedure_id', 'step_key'], 'mps_proc_step_uq');
                $table->index(['status', 'kind'], 'mps_status_kind_idx');
            });
        }

        if (! Schema::hasTable('procedure_notifications')) {
            Schema::create('procedure_notifications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('member_procedure_id')->nullable();
                $table->unsignedBigInteger('member_procedure_step_id')->nullable();
                $table->string('target_type', 24);
                $table->unsignedBigInteger('target_id');
                $table->string('title', 180);
                $table->text('body')->nullable();
                $table->string('dedupe_key', 191)->unique();
                $table->timestamp('acknowledged_at')->nullable();
                $table->unsignedBigInteger('acknowledged_by_user_id')->nullable();
                $table->timestamps();

                $table->foreign('member_procedure_id', 'pn_proc_fk')
                    ->references('id')->on('member_procedures')->cascadeOnDelete();
                $table->foreign('member_procedure_step_id', 'pn_step_fk')
                    ->references('id')->on('member_procedure_steps')->cascadeOnDelete();
                $table->foreign('acknowledged_by_user_id', 'pn_ack_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->index(['target_type', 'target_id', 'acknowledged_at'], 'pn_target_idx');
            });
        }

        if (DB::connection()->pretending()) {
            return;
        }

        if (DB::table('member_procedure_settings')->count() === 0) {
            DB::table('member_procedure_settings')->insert([
                'google_spreadsheet_id' => '1hMezm3dfuvuvYSrBECzvHll0vGqXgOIH8_0FzXKnvAk',
                'google_general_sheet_gid' => '1711111556',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procedure_notifications');
        Schema::dropIfExists('member_procedure_steps');
        Schema::dropIfExists('member_procedures');
        Schema::dropIfExists('member_procedure_settings');
    }
};
