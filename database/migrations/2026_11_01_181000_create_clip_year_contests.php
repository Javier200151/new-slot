<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clip_year_contests', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->boolean('enabled')->default(false);
            $table->dateTime('nomination_starts_at');
            $table->dateTime('nomination_ends_at');
            $table->dateTime('voting_starts_at');
            $table->dateTime('voting_ends_at');
            $table->timestamps();
        });
        Schema::create('clip_year_nominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained('clip_year_contests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('community_clip_id')->constrained('community_clips')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            $table->unique(['contest_id','user_id']);
        });
        Schema::create('clip_year_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomination_id')->constrained('clip_year_nominations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('skulls');
            $table->timestamps();
            $table->unique(['nomination_id','user_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('clip_year_votes');
        Schema::dropIfExists('clip_year_nominations');
        Schema::dropIfExists('clip_year_contests');
    }
};
