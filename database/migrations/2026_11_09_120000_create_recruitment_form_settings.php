<?php

use App\Support\RecruitmentFormFields;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_form_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('fields');
            $table->timestamps();
        });

        DB::table('recruitment_form_settings')->insert([
            'fields' => json_encode(RecruitmentFormFields::defaults(), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('contact_submissions', function (Blueprint $table): void {
            $table->json('recruitment_answers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table): void {
            $table->dropColumn('recruitment_answers');
        });
        Schema::dropIfExists('recruitment_form_settings');
    }
};
