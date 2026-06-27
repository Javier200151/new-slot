<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('nick')->unique();

            $table->string('email')->unique();

            $table->string('password');

            $table->unsignedSmallInteger('promo_id')->nullable();

            $table->string('tagname')->nullable();

            $table->foreignId('status_id');

            $table->string('firma')->nullable();

            $table->string('arma_uid')->nullable()->index();

            $table->string('discord_id')->nullable()->index();

            $table->string('steam_id')->nullable()->index();

            $table->date('member_at')->nullable();

            $table->date('birth_at')->nullable();

            $table->foreignId('tutor_id')->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();

            $table->rememberToken();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
