<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clip_year_contests', function (Blueprint $table): void {
            $table->json('allowed_status_ids')->nullable()->after('enabled');
        });
        Schema::create('community_clip_protagonists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_clip_id')->constrained('community_clips')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['community_clip_id', 'user_id']);
        });
        // Conservar el protagonista anterior de los clips ya publicados.
        \Illuminate\Support\Facades\DB::table('community_clips')
            ->whereNotNull('protagonist_user_id')->orderBy('id')
            ->select(['id', 'protagonist_user_id'])->chunkById(200, function ($clips): void {
                foreach ($clips as $clip) {
                    \Illuminate\Support\Facades\DB::table('community_clip_protagonists')->insertOrIgnore([
                        'community_clip_id' => $clip->id, 'user_id' => $clip->protagonist_user_id,
                    ]);
                }
            });
    }
    public function down(): void
    {
        Schema::dropIfExists('community_clip_protagonists');
        Schema::table('clip_year_contests', fn (Blueprint $table) => $table->dropColumn('allowed_status_ids'));
    }
};
