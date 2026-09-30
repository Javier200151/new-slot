<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL auto-commits DDL. If a previous attempt of this migration failed
        // halfway through, these new support tables may have been left behind
        // even though Laravel did not record the migration as completed.
        // They belong exclusively to this still-unapplied migration, so reset
        // only this small group before creating it atomically from our point of view.
        Schema::dropIfExists('recruitment_period_comments');
        Schema::dropIfExists('recruitment_period_reinforcement_area');
        Schema::dropIfExists('recruitment_reinforcement_areas');

        Schema::create('recruitment_reinforcement_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('recruitment_period_reinforcement_area', function (Blueprint $table): void {
            $table->unsignedBigInteger('recruitment_period_id');
            $table->unsignedBigInteger('recruitment_reinforcement_area_id');

            $table->foreign('recruitment_period_id', 'rpr_period_fk')
                ->references('id')
                ->on('recruitment_periods')
                ->cascadeOnDelete();

            $table->foreign('recruitment_reinforcement_area_id', 'rpr_area_fk')
                ->references('id')
                ->on('recruitment_reinforcement_areas')
                ->restrictOnDelete();

            $table->primary(
                ['recruitment_period_id', 'recruitment_reinforcement_area_id'],
                'recruitment_period_reinforcement_pk'
            );
        });

        Schema::create('recruitment_period_comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('recruitment_period_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('content');
            $table->timestamps();

            $table->foreign('recruitment_period_id', 'rpc_period_fk')
                ->references('id')
                ->on('recruitment_periods')
                ->cascadeOnDelete();

            $table->foreign('user_id', 'rpc_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['recruitment_period_id', 'created_at'], 'recruitment_comment_period_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_period_comments');
        Schema::dropIfExists('recruitment_period_reinforcement_area');
        Schema::dropIfExists('recruitment_reinforcement_areas');
    }
};
