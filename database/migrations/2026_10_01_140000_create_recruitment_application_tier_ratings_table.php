<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL ejecuta CREATE TABLE antes de añadir las constraints.
        // Si una ejecución anterior falló al crear una FK, la tabla puede
        // existir ya aunque Laravel no haya registrado la migración.
        if (! Schema::hasTable('recruitment_application_tier_ratings')) {
            Schema::create('recruitment_application_tier_ratings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('contact_submission_id');
                $table->foreignId('user_id');
                $table->unsignedTinyInteger('tier');
                $table->text('reason')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('recruitment_application_tier_ratings', function (Blueprint $table): void {
            // Nombres explícitos y cortos para respetar el límite de 64 caracteres de MySQL.
            $table->foreign(
                'contact_submission_id',
                'recruit_tier_submission_fk'
            )->references('id')
                ->on('contact_submissions')
                ->cascadeOnDelete();

            $table->foreign(
                'user_id',
                'recruit_tier_user_fk'
            )->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unique(
                ['contact_submission_id', 'user_id'],
                'recruitment_tier_submission_user_unique'
            );

            $table->index(
                ['contact_submission_id', 'tier'],
                'recruitment_tier_submission_tier_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_tier_ratings');
    }
};
