<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Marca el instante a partir del cual podemos conocer realmente qué
         * es "nuevo" para cada usuario. De este modo, al desplegar la función
         * no convertimos todo el histórico del foro en mensajes sin leer.
         */
        if (! Schema::hasColumn('users', 'forum_unread_baseline_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('forum_unread_baseline_at')
                    ->useCurrent()
                    ->after('remember_token');
            });
        }

        /*
         * MySQL puede utilizar una zona horaria distinta de PHP para
         * CURRENT_TIMESTAMP. Reescribimos el baseline de los usuarios ya
         * existentes con `now()` de Laravel para compararlo con los
         * `updated_at` que genera Eloquent en la misma referencia temporal.
         */
        DB::table('users')->update([
            'forum_unread_baseline_at' => now(),
        ]);

        if (! Schema::hasTable('community_post_reads')) {
            Schema::create('community_post_reads', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('community_post_id')
                    ->constrained('community_posts')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->timestamp('read_at');
                $table->timestamps();

                $table->unique(
                    ['community_post_id', 'user_id'],
                    'community_post_reads_post_user_unique',
                );
                $table->index(
                    ['user_id', 'read_at'],
                    'community_post_reads_user_read_index',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_post_reads');

        if (Schema::hasColumn('users', 'forum_unread_baseline_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('forum_unread_baseline_at');
            });
        }
    }
};
