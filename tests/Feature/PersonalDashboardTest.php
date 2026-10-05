<?php

namespace Tests\Feature;

use App\Filament\Widgets\RecruitmentApplicationsDashboardWidget;
use App\Models\PersonalDashboard;
use App\Models\PersonalDashboardWidget;
use App\Models\User;
use App\Services\PersonalDashboardService;
use App\Support\PersonalDashboardWidgetRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PersonalDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_dashboards', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name', 80);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::create('personal_dashboard_widgets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('personal_dashboard_id');
            $table->string('type', 64);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('size', 16)->default('2x2');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['personal_dashboard_id', 'type']);
        });

        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_recruitment')->default(false);
            $table->string('recruitment_review_status')->nullable();
            $table->unsignedBigInteger('recruitment_matched_user_id')->nullable();
            $table->dateTime('recruited_at')->nullable();
            $table->dateTime('recruitment_reviewed_at')->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert([
            [
                'id' => 1,
                'nick' => 'Alpha',
                'email' => 'alpha@example.test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'nick' => 'Bravo',
                'email' => 'bravo@example.test',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_each_user_gets_an_independent_default_dashboard(): void
    {
        $service = app(PersonalDashboardService::class);
        $alpha = User::query()->findOrFail(1);
        $bravo = User::query()->findOrFail(2);

        $alphaDashboard = $service->activeFor($alpha);
        $bravoDashboard = $service->activeFor($bravo);

        $this->assertNotSame($alphaDashboard->id, $bravoDashboard->id);
        $this->assertSame('Principal', $alphaDashboard->name);
        $this->assertTrue($alphaDashboard->is_active);
        $this->assertSame($alpha->id, $alphaDashboard->user_id);

        $this->assertSame(
            PersonalDashboardWidgetRegistry::defaultTypes(),
            $alphaDashboard->widgets()->orderBy('position')->pluck('type')->all(),
        );
    }

    public function test_switching_dashboard_is_scoped_to_its_owner(): void
    {
        $service = app(PersonalDashboardService::class);
        $alpha = User::query()->findOrFail(1);
        $bravo = User::query()->findOrFail(2);

        $first = $service->activeFor($alpha);
        $second = $service->create($alpha, 'Operaciones');
        $bravoDashboard = $service->activeFor($bravo);

        $service->activate($alpha, $first->id);

        $this->assertTrue($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
        $this->assertTrue($bravoDashboard->fresh()->is_active);
    }

    public function test_widget_settings_are_private_to_each_dashboard(): void
    {
        $dashboard = app(PersonalDashboardService::class)->activeFor(User::query()->findOrFail(1));

        $widget = $dashboard->widgets()->create([
            'type' => PersonalDashboardWidgetRegistry::REMINDERS,
            'position' => 99,
            'size' => PersonalDashboardWidget::SIZE_SQUARE,
            'settings' => ['text' => 'Recordatorio privado'],
        ]);

        $this->assertSame('Recordatorio privado', $widget->fresh()->settings['text']);
        $this->assertSame(1, PersonalDashboard::query()->where('user_id', 1)->count());
        $this->assertSame(0, PersonalDashboard::query()->where('user_id', 2)->count());
    }


    public function test_widget_grid_sizes_respect_each_widgets_minimum_and_maximum(): void
    {
        $this->assertArrayHasKey('1x1', PersonalDashboardWidgetRegistry::sizeOptions(
            PersonalDashboardWidgetRegistry::QUICK_LINKS,
        ));

        $this->assertArrayNotHasKey('1x1', PersonalDashboardWidgetRegistry::sizeOptions(
            PersonalDashboardWidgetRegistry::QUICK_SEARCH,
        ));
        $this->assertArrayHasKey('2x1', PersonalDashboardWidgetRegistry::sizeOptions(
            PersonalDashboardWidgetRegistry::QUICK_SEARCH,
        ));
        $this->assertArrayHasKey('4x2', PersonalDashboardWidgetRegistry::sizeOptions(
            PersonalDashboardWidgetRegistry::QUICK_SEARCH,
        ));
        $this->assertArrayHasKey('3x3', PersonalDashboardWidgetRegistry::sizeOptions(
            PersonalDashboardWidgetRegistry::MINI_CALENDAR,
        ));
        $this->assertArrayNotHasKey('2x2', PersonalDashboardWidgetRegistry::sizeOptions(
            PersonalDashboardWidgetRegistry::MINI_CALENDAR,
        ));

        $this->assertSame(
            '3x3',
            PersonalDashboardWidgetRegistry::normalizeSize(
                PersonalDashboardWidgetRegistry::MINI_CALENDAR,
                '1x1',
            ),
        );
    }

    public function test_recruited_applications_do_not_remain_in_approved_dashboard_widget(): void
    {
        DB::table('contact_submissions')->insert([
            [
                'id' => 10,
                'is_recruitment' => true,
                'recruitment_review_status' => 'approved',
                'recruitment_matched_user_id' => 1,
                'recruited_at' => null,
                'recruitment_reviewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 11,
                'is_recruitment' => true,
                'recruitment_review_status' => 'approved',
                'recruitment_matched_user_id' => 2,
                'recruited_at' => now(),
                'recruitment_reviewed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->assertSame(
            [10],
            RecruitmentApplicationsDashboardWidget::pendingApprovedQuery()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
        );
    }
}
