<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sqa_easter_egg_overrides', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->string('mode', 8)->default('auto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sqa_easter_egg_overrides');
    }
};
