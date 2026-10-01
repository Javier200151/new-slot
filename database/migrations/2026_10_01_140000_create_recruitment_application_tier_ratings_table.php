<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esta migración puede haber alcanzado a crear la tabla antes de que
        // Laravel registrara la migración como completada. En ese caso no
        // intentamos recrearla: dejamos que migrate continúe y la registre.
        if (Schema::hasTable('recruitment_application_tier_ratings')) {
            return;
        }

        Schema::create('recruitment_application_tier_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_submission_id')->constrained('contact_submissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('tier');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['contact_submission_id', 'user_id'], 'recruitment_tier_submission_user_unique');
            $table->index(['contact_submission_id', 'tier'], 'recruitment_tier_submission_tier_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_application_tier_ratings');
    }
};
