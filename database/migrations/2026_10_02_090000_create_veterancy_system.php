<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_status_histories')) {
            Schema::create('user_status_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('from_status_id')->nullable();
                $table->unsignedBigInteger('to_status_id');
                $table->dateTime('changed_at');
                $table->unsignedBigInteger('changed_by_user_id')->nullable();
                $table->string('source', 32)->default('automatic');
                $table->char('source_hash', 64)->nullable();
                $table->string('retutored_by', 120)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->foreign('from_status_id', 'ush_from_status_fk')
                    ->references('id')->on('status')->nullOnDelete();
                $table->foreign('to_status_id', 'ush_to_status_fk')
                    ->references('id')->on('status')->restrictOnDelete();
                $table->foreign('changed_by_user_id', 'ush_actor_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->index(['user_id', 'changed_at'], 'ush_user_changed_idx');
                $table->unique('source_hash', 'ush_source_hash_uq');
            });
        }

        if (! Schema::hasTable('veterancy_settings')) {
            Schema::create('veterancy_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('forum_category_id')->nullable();
                $table->unsignedBigInteger('bronze_metopa_id')->nullable();
                $table->unsignedBigInteger('silver_metopa_id')->nullable();
                $table->unsignedBigInteger('gold_metopa_id')->nullable();
                $table->text('post_body')->nullable();
                $table->timestamps();

                $table->foreign('forum_category_id', 'vs_forum_category_fk')
                    ->references('id')->on('community_forum_categories')->nullOnDelete();
                $table->foreign('bronze_metopa_id', 'vs_bronze_metopa_fk')
                    ->references('id')->on('metopas')->nullOnDelete();
                $table->foreign('silver_metopa_id', 'vs_silver_metopa_fk')
                    ->references('id')->on('metopas')->nullOnDelete();
                $table->foreign('gold_metopa_id', 'vs_gold_metopa_fk')
                    ->references('id')->on('metopas')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('veterancy_awards')) {
            Schema::create('veterancy_awards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('level', 16);
                $table->unsignedBigInteger('metopa_id');
                $table->unsignedInteger('effective_days');
                $table->dateTime('approved_at');
                $table->unsignedBigInteger('approved_by_user_id')->nullable();
                $table->unsignedBigInteger('community_post_id')->nullable();
                $table->timestamps();

                $table->foreign('metopa_id', 'va_metopa_fk')
                    ->references('id')->on('metopas')->restrictOnDelete();
                $table->foreign('approved_by_user_id', 'va_actor_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('community_post_id', 'va_post_fk')
                    ->references('id')->on('community_posts')->nullOnDelete();
                $table->unique(['user_id', 'level'], 'va_user_level_uq');
                $table->index(['level', 'approved_at'], 'va_level_approved_idx');
            });
        }

        if (DB::connection()->pretending()) {
            return;
        }

        if (DB::table('veterancy_settings')->count() === 0) {
            DB::table('veterancy_settings')->insert([
                'post_body' => "[h2]Reconocimiento a nuestros veteranos[/h2]\n\nSquad Alpha reconoce la trayectoria y el tiempo de servicio efectivo de los siguientes miembros:",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Punto de corte para instalaciones con miembros históricos. El Excel
        // puede insertar después eventos anteriores a este baseline sin perder
        // el estado que el usuario tenía en el momento del despliegue.
        $now = now();
        DB::table('users')
            ->whereNotNull('member_at')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->select(['id', 'status_id', 'member_at'])
            ->chunkById(250, function ($users) use ($now): void {
                foreach ($users as $user) {
                    DB::table('user_status_histories')->insertOrIgnore([
                        'user_id' => $user->id,
                        'from_status_id' => null,
                        'to_status_id' => $user->status_id,
                        'changed_at' => $now,
                        'changed_by_user_id' => null,
                        'source' => 'baseline',
                        'source_hash' => hash('sha256', 'baseline|' . $user->id),
                        'note' => 'Estado registrado al activar el historial de veteranías.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('veterancy_awards');
        Schema::dropIfExists('veterancy_settings');
        Schema::dropIfExists('user_status_histories');
    }
};
