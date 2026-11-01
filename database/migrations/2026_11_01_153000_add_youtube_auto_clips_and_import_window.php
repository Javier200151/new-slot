<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table): void {
            $table->boolean('auto_import_youtube_clips')->default(false);
            $table->string('clip_import_window', 3)->default('7d');
        });
    }

    public function down(): void
    {
        Schema::table('streamers', function (Blueprint $table): void {
            $table->dropColumn(['auto_import_youtube_clips', 'clip_import_window']);
        });
    }
};
