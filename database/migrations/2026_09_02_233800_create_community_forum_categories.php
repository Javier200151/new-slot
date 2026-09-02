<?php

use App\Models\ForumCategory;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        // Esta migración puede quedar a medias en MySQL si una ejecución previa
        // falla después de crear una tabla (los DDL hacen commit implícito).
        // Por eso comprobamos cada pieza antes de crearla y hacemos el seed de
        // categorías de forma idempotente más abajo.
        if (! Schema::hasTable('community_forum_categories')) {
            Schema::create('community_forum_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 100)->unique();
                $table->string('title', 120);
                $table->string('singular', 80)->nullable();
                $table->text('description')->nullable();
                $table->string('hint', 255)->nullable();
                $table->string('icon', 32)->default('💬');
                $table->string('color', 16)->default('#38bdf8');
                $table->string('channel', 32)->default('personal')->index();
                $table->string('system_type', 32)->default(ForumCategory::TYPE_STANDARD)->index();
                $table->string('process_type', 32)->nullable()->index();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_enabled')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('community_forum_category_status')) {
            Schema::create('community_forum_category_status', function (Blueprint $table): void {
                $table->foreignId('community_forum_category_id')
                    ->constrained('community_forum_categories')
                    ->cascadeOnDelete();
                $table->foreignId('status_id')
                    ->constrained('status')
                    ->cascadeOnDelete();

                $table->primary(
                    ['community_forum_category_id', 'status_id'],
                    'community_forum_category_status_primary'
                );
            });
        }

        if (! Schema::hasColumn('community_posts', 'forum_category_id')) {
            Schema::table('community_posts', function (Blueprint $table): void {
                $table->foreignId('forum_category_id')
                    ->nullable()
                    ->after('community_process_id')
                    ->constrained('community_forum_categories')
                    ->restrictOnDelete();
            });
        }

        $categories = [
            [
                'slug' => 'saludos-presentaciones',
                'title' => 'Saludos y presentaciones',
                'singular' => 'Presentación',
                'description' => 'Un espacio para presentarte, dar la bienvenida y conocer mejor a la gente de Squad Alpha.',
                'hint' => 'Cuéntanos quién eres, qué te gusta jugar y cómo has llegado a Squad Alpha.',
                'icon' => '👋',
                'color' => '#14b8a6',
                'channel' => 'personal',
                'system_type' => ForumCategory::TYPE_STANDARD,
                'process_type' => null,
                'is_system' => false,
                'is_enabled' => true,
                'sort_order' => 5,
                'statuses' => ['ACTIVO', 'RESERVA', 'RECLUTA'],
            ],
            [
                'slug' => 'debate',
                'title' => 'Debates',
                'singular' => 'Debate',
                'description' => 'Conversaciones, solicitudes y asuntos generales de la comunidad.',
                'hint' => 'Abre un tema para debatirlo con el resto de miembros.',
                'icon' => '💬',
                'color' => '#38bdf8',
                'channel' => 'personal',
                'system_type' => ForumCategory::TYPE_DEBATE,
                'process_type' => null,
                'is_system' => true,
                'is_enabled' => true,
                'sort_order' => 10,
                'statuses' => ['ACTIVO', 'RESERVA'],
            ],
            [
                'slug' => 'convocatoria',
                'title' => 'Convocatorias',
                'singular' => 'Convocatoria',
                'description' => 'Postulaciones para cargos, grupos de trabajo, plazas o responsabilidades.',
                'hint' => 'Publica una convocatoria, recibe candidaturas y vincula después una votación.',
                'icon' => '📣',
                'color' => '#f43f5e',
                'channel' => 'personal',
                'system_type' => ForumCategory::TYPE_CALL,
                'process_type' => 'convocatoria',
                'is_system' => true,
                'is_enabled' => true,
                'sort_order' => 20,
                'statuses' => ['ACTIVO', 'RESERVA'],
            ],
            [
                'slug' => 'propuesta',
                'title' => 'Propuestas',
                'singular' => 'Propuesta',
                'description' => 'Ideas y cambios que se presentan a la comunidad antes de decidir.',
                'hint' => 'Presenta una idea, debátela y añade una votación si procede.',
                'icon' => '💡',
                'color' => '#facc15',
                'channel' => 'personal',
                'system_type' => ForumCategory::TYPE_PROPOSAL,
                'process_type' => 'propuestas',
                'is_system' => true,
                'is_enabled' => true,
                'sort_order' => 30,
                'statuses' => ['ACTIVO', 'RESERVA'],
            ],
            [
                'slug' => 'consulta',
                'title' => 'Consultas',
                'singular' => 'Consulta',
                'description' => 'Preguntas para recoger opinión y tomar una decisión cuando sea necesario.',
                'hint' => 'Consulta a la comunidad y vincula una votación al hilo si lo necesitas.',
                'icon' => '🗳️',
                'color' => '#a78bfa',
                'channel' => 'personal',
                'system_type' => ForumCategory::TYPE_CONSULTATION,
                'process_type' => 'consulta',
                'is_system' => true,
                'is_enabled' => true,
                'sort_order' => 40,
                'statuses' => ['ACTIVO', 'RESERVA'],
            ],
            [
                'slug' => 'diario',
                'title' => 'Diarios',
                'singular' => 'Diario',
                'description' => 'Bitácoras personales vinculadas a los eventos en los que ha participado cada jugador.',
                'hint' => 'Consulta los diarios o continúa escribiendo el tuyo.',
                'icon' => '📓',
                'color' => '#22c55e',
                'channel' => 'diary',
                'system_type' => ForumCategory::TYPE_DIARY,
                'process_type' => null,
                'is_system' => true,
                'is_enabled' => true,
                'sort_order' => 50,
                'statuses' => ['ACTIVO', 'RESERVA', 'RECLUTA'],
            ],
            [
                'slug' => 'cantina',
                'title' => 'WHISKEY — Enguarrinando',
                'singular' => 'Hilo',
                'description' => 'La zona informal del foro: quedadas, videojuegos, cine, rol y cualquier tema off-topic.',
                'hint' => 'Abre un hilo en WHISKEY.',
                'icon' => '🥃',
                'color' => '#f97316',
                'channel' => 'cantina',
                'system_type' => ForumCategory::TYPE_CANTINA,
                'process_type' => null,
                'is_system' => true,
                'is_enabled' => true,
                'sort_order' => 60,
                'statuses' => ['ACTIVO', 'RESERVA', 'RECLUTA'],
            ],
        ];

        // `artisan migrate --pretend` no ejecuta INSERT/UPDATE: solo registra
        // las consultas que se ejecutarían. Esta migración necesita leer los IDs
        // de categorías que acaba de crear para reasignar publicaciones y estados.
        // En modo pretend esos registros todavía no existen y acceder a claves como
        // `cantina` provocaría un falso "Undefined array key". Las operaciones de
        // datos se ejecutan únicamente en una migración real.
        if (DB::connection()->pretending()) {
            return;
        }

        $statusIds = DB::table('status')
            ->whereIn('name', ['ACTIVO', 'RESERVA', 'RECLUTA', 'CESADO'])
            ->pluck('id', 'name');

        foreach ($categories as $definition) {
            $visibleStatuses = $definition['statuses'];
            unset($definition['statuses']);

            $now = now();
            DB::table('community_forum_categories')->updateOrInsert(
                ['slug' => $definition['slug']],
                [
                    ...$definition,
                    'updated_at' => $now,
                ]
            );

            // Si la categoría ya existía por una ejecución parcial, no
            // tocamos su created_at. Si acaba de crearse, lo rellenamos aquí.
            DB::table('community_forum_categories')
                ->where('slug', $definition['slug'])
                ->whereNull('created_at')
                ->update(['created_at' => $now]);

            $categoryId = DB::table('community_forum_categories')
                ->where('slug', $definition['slug'])
                ->value('id');

            DB::table('community_forum_category_status')
                ->where('community_forum_category_id', $categoryId)
                ->delete();

            foreach ($visibleStatuses as $statusName) {
                $statusId = $statusIds[$statusName] ?? null;

                if ($statusId) {
                    DB::table('community_forum_category_status')->insertOrIgnore([
                        'community_forum_category_id' => $categoryId,
                        'status_id' => $statusId,
                    ]);
                }
            }
        }

        $categoryIds = DB::table('community_forum_categories')
            ->pluck('id', 'slug');

        DB::table('community_posts')
            ->where('channel', 'cantina')
            ->update(['forum_category_id' => $categoryIds['cantina']]);

        DB::table('community_posts')
            ->where('channel', 'personal')
            ->whereNull('community_process_id')
            ->update(['forum_category_id' => $categoryIds['debate']]);

        $processMappings = [
            'convocatoria' => 'convocatoria',
            'propuestas' => 'propuesta',
            'consulta' => 'consulta',
        ];

        foreach ($processMappings as $processType => $categorySlug) {
            $processIds = DB::table('community_processes')
                ->where('type', $processType)
                ->pluck('id');

            if ($processIds->isNotEmpty()) {
                DB::table('community_posts')
                    ->where('channel', 'personal')
                    ->whereIn('community_process_id', $processIds)
                    ->update(['forum_category_id' => $categoryIds[$categorySlug]]);
            }
        }

        // Cualquier publicación personal antigua cuyo proceso no encaje en los
        // tipos conocidos se conserva dentro de Debates.
        DB::table('community_posts')
            ->where('channel', 'personal')
            ->whereNull('forum_category_id')
            ->update(['forum_category_id' => $categoryIds['debate']]);

        // En producción ya existen roles cuando se ejecuta esta migración, así
        // que creamos aquí mismo los permisos nuevos para que el despliegue quede
        // listo con solo `artisan migrate`. En una instalación desde cero los
        // roles todavía no existen en este punto: dejamos entonces que
        // PermissionsSeeder cree los permisos después, conservando sus valores
        // por defecto para los roles `user` y `moderador foro`.
        if (Schema::hasTable('roles') && Schema::hasTable('permissions') && DB::table('roles')->exists()) {
            ForumCategory::query()
                ->orderBy('sort_order')
                ->each(fn (ForumCategory $category) => $category->ensurePermissions(true));

            $categoryAdminPermissions = [];
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissionName = "community-forum-categories.{$action}";
                Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
                $categoryAdminPermissions[] = $permissionName;
            }

            $admin = Role::query()
                ->where('name', 'admin')
                ->where('guard_name', 'web')
                ->first();

            if ($admin) {
                $admin->givePermissionTo($categoryAdminPermissions);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('community_posts', 'forum_category_id')) {
            Schema::table('community_posts', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('forum_category_id');
            });
        }

        Schema::dropIfExists('community_forum_category_status');
        Schema::dropIfExists('community_forum_categories');
    }
};
