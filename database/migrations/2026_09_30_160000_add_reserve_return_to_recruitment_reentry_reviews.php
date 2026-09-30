<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_reentry_reviews', function (Blueprint $table): void {
            $table->string('review_type', 40)
                ->default('PROMOTED_TO_RECRUIT')
                ->after('previous_period_id')
                ->index();

            $table->foreignId('tutorial_tutor_user_id')
                ->nullable()
                ->after('review_type')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('tutorial_approved_at')
                ->nullable()
                ->after('tutorial_tutor_user_id');
        });

        // Las revisiones históricas existentes corresponden al flujo original.
        DB::table('recruitment_reentry_reviews')
            ->whereNull('review_type')
            ->update(['review_type' => 'PROMOTED_TO_RECRUIT']);

        // Un retorno desde RESERVA puede pertenecer a usuarios antiguos que no
        // tengan un RecruitmentPeriod previo registrado en NewSlot.
        Schema::table('recruitment_reentry_reviews', function (Blueprint $table): void {
            $table->unsignedBigInteger('previous_period_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_reentry_reviews', function (Blueprint $table): void {
            $table->dropForeign(['tutorial_tutor_user_id']);
            $table->dropColumn([
                'tutorial_tutor_user_id',
                'tutorial_approved_at',
                'review_type',
            ]);

            $table->unsignedBigInteger('previous_period_id')->nullable(false)->change();
        });
    }
};
