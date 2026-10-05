<?php

namespace Tests\Feature;

use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\ForumCategory;
use App\Services\ForumCategoryArchiveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ForumCategoryArchiveServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->seedFixture();
    }

    public function test_category_backup_can_restore_threads_comments_polls_votes_reactions_subscriptions_and_reads(): void
    {
        $service = app(ForumCategoryArchiveService::class);
        $category = ForumCategory::query()->where('slug', 'general')->firstOrFail();

        $payload = $service->export($category);

        $this->assertSame(ForumCategoryArchiveService::FORMAT, $payload['format']);
        $this->assertSame('general', $payload['category']['slug']);
        $this->assertCount(1, $payload['posts']);
        $this->assertCount(1, $payload['posts'][0]['comments']);
        $this->assertCount(1, $payload['posts'][0]['poll']['votes']);
        $this->assertCount(1, $payload['posts'][0]['reactions']);
        $this->assertCount(1, $payload['posts'][0]['subscriptions']);
        $this->assertCount(1, $payload['posts'][0]['reads']);

        $service->deleteCategoryWithContent($category);

        $this->assertDatabaseMissing('community_forum_categories', ['slug' => 'general']);
        $this->assertSame(0, DB::table('community_posts')->count());
        $this->assertSame(0, DB::table('community_post_comments')->count());
        $this->assertSame(0, DB::table('community_polls')->count());

        $restored = $service->import($payload);

        $this->assertSame('general', $restored->slug);
        $this->assertSame(['ACTIVO'], $restored->statuses()->pluck('name')->all());
        $this->assertDatabaseHas('community_posts', ['forum_category_id' => $restored->id, 'title' => 'Hilo de prueba']);
        $this->assertDatabaseHas('community_post_comments', ['body' => 'Respuesta de prueba']);
        $this->assertDatabaseHas('community_polls', ['title' => 'Votación de prueba']);
        $this->assertDatabaseHas('community_poll_votes', ['user_id' => 2]);
        $this->assertDatabaseHas('community_reactions', ['reaction' => 'like']);
        $this->assertDatabaseHas('community_subscriptions', ['user_id' => 2]);
        $this->assertDatabaseHas('community_post_reads', ['user_id' => 2]);
    }

    public function test_diary_cannot_be_exported_or_deleted_as_a_normal_category(): void
    {
        DB::table('community_forum_categories')->insert([
            'id' => 2,
            'slug' => 'diario',
            'title' => 'Diarios',
            'singular' => 'Diario',
            'icon' => '📓',
            'color' => '#22c55e',
            'channel' => 'diary',
            'system_type' => ForumCategory::TYPE_DIARY,
            'is_system' => true,
            'is_enabled' => true,
            'allow_polls' => false,
            'sort_order' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $diary = ForumCategory::query()->findOrFail(2);

        $this->expectException(\RuntimeException::class);
        app(ForumCategoryArchiveService::class)->export($diary);
    }

    public function test_diary_allows_safe_personalization_but_preserves_internal_behavior(): void
    {
        DB::table('status')->insert([
            'id' => 2,
            'name' => 'RESERVA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('community_forum_categories')->insert([
            'id' => 2,
            'slug' => 'diario',
            'title' => 'Diarios',
            'singular' => 'Diario',
            'description' => 'Descripción interna',
            'hint' => 'Ayuda interna',
            'icon' => '📓',
            'color' => '#22c55e',
            'channel' => 'diary',
            'system_type' => ForumCategory::TYPE_DIARY,
            'process_type' => null,
            'is_system' => true,
            'is_enabled' => true,
            'allow_polls' => false,
            'sort_order' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $diary = ForumCategory::query()->findOrFail(2);
        $diary->fill([
            'title' => 'Bitácoras',
            'singular' => 'Bitácora',
            'color' => '#abcdef',
            'slug' => 'otro-slug',
            'description' => 'No debe cambiar',
            'hint' => 'No debe cambiar',
            'icon' => '🔥',
            'channel' => 'personal',
            'system_type' => ForumCategory::TYPE_STANDARD,
            'process_type' => 'consulta',
            'is_system' => false,
            'is_enabled' => false,
            'allow_polls' => true,
            'sort_order' => 999,
        ])->save();

        $diary->statuses()->sync([2]);
        $diary->refresh();

        $this->assertSame('Bitácoras', $diary->title);
        $this->assertSame('Bitácora', $diary->singular);
        $this->assertSame('#abcdef', $diary->color);
        $this->assertSame(['RESERVA'], $diary->statuses()->pluck('name')->all());

        $this->assertSame('diario', $diary->slug);
        $this->assertSame('Descripción interna', $diary->description);
        $this->assertSame('Ayuda interna', $diary->hint);
        $this->assertSame('📓', $diary->icon);
        $this->assertSame('diary', $diary->channel);
        $this->assertSame(ForumCategory::TYPE_DIARY, $diary->system_type);
        $this->assertNull($diary->process_type);
        $this->assertTrue($diary->is_system);
        $this->assertTrue($diary->is_enabled);
        $this->assertFalse($diary->allow_polls);
        $this->assertSame(999, $diary->sort_order);
    }

    private function seedFixture(): void
    {
        $now = now();

        DB::table('status')->insert([
            'id' => 1,
            'name' => 'ACTIVO',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->insert([
            [
                'id' => 1,
                'nick' => 'Autor',
                'email' => 'autor@example.test',
                'password' => 'x',
                'status_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'nick' => 'Lector',
                'email' => 'lector@example.test',
                'password' => 'x',
                'status_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('community_forum_categories')->insert([
            'id' => 1,
            'slug' => 'general',
            'title' => 'General',
            'singular' => 'Hilo',
            'description' => 'General',
            'hint' => 'Publica aquí.',
            'icon' => '💬',
            'color' => '#38bdf8',
            'channel' => 'personal',
            'system_type' => ForumCategory::TYPE_STANDARD,
            'process_type' => null,
            'is_system' => false,
            'is_enabled' => true,
            'allow_polls' => true,
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('community_forum_category_status')->insert([
            'community_forum_category_id' => 1,
            'status_id' => 1,
        ]);

        DB::table('community_posts')->insert([
            'id' => 10,
            'channel' => 'personal',
            'forum_category_id' => 1,
            'user_id' => 1,
            'title' => 'Hilo de prueba',
            'body' => 'Contenido',
            'is_pinned' => true,
            'is_locked' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('community_post_comments')->insert([
            'id' => 20,
            'community_post_id' => 10,
            'user_id' => 2,
            'body' => 'Respuesta de prueba',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('community_polls')->insert([
            'id' => 30,
            'community_post_id' => 10,
            'title' => 'Votación de prueba',
            'is_published' => true,
            'selection_mode' => 'single',
            'min_choices' => 1,
            'max_choices' => 1,
            'allow_vote_change' => true,
            'is_anonymous' => false,
            'results_visibility' => 'always',
            'show_voter_names' => true,
            'show_participation' => true,
            'allow_abstain' => false,
            'randomize_options' => false,
            'created_by' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('community_poll_options')->insert([
            'id' => 31,
            'community_poll_id' => 30,
            'label' => 'Sí',
            'sort_order' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('community_poll_votes')->insert([
            'community_poll_id' => 30,
            'community_poll_option_id' => 31,
            'user_id' => 2,
            'is_abstain' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('community_reactions')->insert([
            'user_id' => 2,
            'reactable_type' => (new CommunityPost())->getMorphClass(),
            'reactable_id' => 10,
            'reaction' => 'like',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('community_subscriptions')->insert([
            'user_id' => 2,
            'subscribable_type' => (new CommunityPost())->getMorphClass(),
            'subscribable_id' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('community_post_reads')->insert([
            'community_post_id' => 10,
            'user_id' => 2,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->string('email');
            $table->string('password');
            $table->unsignedBigInteger('status_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk');
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk');
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'rhp_pk');
        });

        Schema::create('community_forum_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('singular')->nullable();
            $table->text('description')->nullable();
            $table->string('hint')->nullable();
            $table->string('icon')->default('💬');
            $table->string('color')->default('#38bdf8');
            $table->string('channel')->default('personal');
            $table->string('system_type')->default(ForumCategory::TYPE_STANDARD);
            $table->string('process_type')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('allow_polls')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });
        Schema::create('community_forum_category_status', function (Blueprint $table): void {
            $table->unsignedBigInteger('community_forum_category_id');
            $table->unsignedBigInteger('status_id');
        });
        Schema::create('community_processes', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('discussion');
            $table->boolean('applications_enabled')->default(false);
            $table->timestamp('applications_start_at')->nullable();
            $table->timestamp('applications_end_at')->nullable();
            $table->boolean('allow_application_edit')->default(true);
            $table->boolean('allow_application_withdraw')->default(true);
            $table->unsignedTinyInteger('max_winners')->nullable();
            $table->json('eligible_statuses')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('channel');
            $table->unsignedBigInteger('community_process_id')->nullable();
            $table->unsignedBigInteger('forum_category_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('title');
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('community_post_comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('community_post_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('community_process_applications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('community_process_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
        });
        Schema::create('community_polls', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('community_process_id')->nullable();
            $table->unsignedBigInteger('community_post_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('selection_mode')->default('single');
            $table->unsignedTinyInteger('min_choices')->default(1);
            $table->unsignedTinyInteger('max_choices')->nullable();
            $table->boolean('allow_vote_change')->default(true);
            $table->boolean('is_anonymous')->default(false);
            $table->string('results_visibility')->default('always');
            $table->boolean('show_voter_names')->default(false);
            $table->boolean('show_participation')->default(true);
            $table->boolean('allow_abstain')->default(false);
            $table->boolean('randomize_options')->default(false);
            $table->unsignedTinyInteger('quorum_percent')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('community_poll_options', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('community_poll_id');
            $table->unsignedBigInteger('candidate_user_id')->nullable();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(10);
            $table->timestamps();
        });
        Schema::create('community_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('community_poll_id');
            $table->unsignedBigInteger('community_poll_option_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_abstain')->default(false);
            $table->timestamps();
        });
        Schema::create('community_reactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('reactable_type');
            $table->unsignedBigInteger('reactable_id');
            $table->string('reaction');
            $table->timestamps();
        });
        Schema::create('community_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('subscribable_type');
            $table->unsignedBigInteger('subscribable_id');
            $table->timestamps();
            $table->unique(['user_id', 'subscribable_type', 'subscribable_id'], 'cs_unique');
        });
        Schema::create('community_post_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('community_post_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at');
            $table->timestamps();
            $table->unique(['community_post_id', 'user_id'], 'cpr_unique');
        });
    }
}
