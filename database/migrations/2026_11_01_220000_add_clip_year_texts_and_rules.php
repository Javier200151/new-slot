<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clip_year_contests', function (Blueprint $table): void {
            $table->string('tab_text_nominations', 120)->nullable();
            $table->string('tab_text_voting', 120)->nullable();
            $table->text('rules_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clip_year_contests', function (Blueprint $table): void {
            $table->dropColumn(['tab_text_nominations', 'tab_text_voting', 'rules_text']);
        });
    }
};
