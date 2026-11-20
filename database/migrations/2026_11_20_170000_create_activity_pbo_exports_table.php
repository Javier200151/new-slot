<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_pbo_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->unique()->constrained('activities')->cascadeOnDelete();
            $table->string('filename')->nullable();
            $table->string('source_path', 512)->nullable();
            $table->string('pbo_sha256', 64)->nullable();
            $table->string('html_sha256', 64)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_pbo_exports');
    }
};
