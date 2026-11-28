<?php

namespace Tests\Feature;

use App\Filament\Pages\ServidoresJuego;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesEventRegressionSchema;
use Tests\TestCase;

class MissionControlLogAccessTest extends TestCase
{
    use CreatesEventRegressionSchema;

    private string $certificate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEventRegressionSchema();
        $this->insertVerifiedUser(31, 'SinLogs');
        $this->insertVerifiedUser(32, 'ConLogs');
        $this->insertVerifiedUser(33, 'AdminLogs');
        $this->createRoleTables();

        $this->certificate = tempnam(sys_get_temp_dir(), 'sqa-ca-');
        file_put_contents($this->certificate, "test CA certificate\n");
        config()->set('mission_control.enabled', true);
        config()->set('mission_control.url', 'https://192.168.37.1:8443/api/v1');
        config()->set('mission_control.secret', str_repeat('s', 40));
        config()->set('mission_control.ca_certificate', $this->certificate);

        $view = Permission::firstOrCreate(['name' => 'game-servers.view', 'guard_name' => 'web']);
        $logs = Permission::firstOrCreate(['name' => 'game-servers.logs', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'visores', 'guard_name' => 'web']);
        $viewer->givePermissionTo($view);
        $authorized = Role::firstOrCreate(['name' => 'lectores_logs', 'guard_name' => 'web']);
        $authorized->givePermissionTo([$view, $logs]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        User::findOrFail(31)->assignRole($viewer);
        User::findOrFail(32)->assignRole($authorized);
        User::findOrFail(33)->assignRole('admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        RateLimiter::clear('sqa-mission-control-logs:31');
        RateLimiter::clear('sqa-mission-control-logs:32');
        RateLimiter::clear('sqa-mission-control-logs:33');
    }

    protected function tearDown(): void
    {
        @unlink($this->certificate);
        parent::tearDown();
    }

    private function createRoleTables(): void
    {
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
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_log_permission_is_in_role_catalog(): void
    {
        $this->assertContains('game-servers.logs', PermissionCatalog::permissionNames());
        $this->assertSame('Ver logs de los servidores',
            PermissionCatalog::actionOptionsFor('game-servers')['logs'] ?? null);
    }

    public function test_non_authorized_user_is_rejected_before_agent_request(): void
    {
        Http::fake();
        $this->actingAs(User::findOrFail(31));
        $component = app(ServidoresJuego::class);

        foreach ([
            fn () => $component->loadLogs('academia'),
            fn () => $component->readLog('academia', 'HC1', 'test.rpt'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Expected 403.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        Http::assertNothingSent();
        $this->get(route('admin.game-servers.logs.download', [
            'server' => 'academia', 'source' => 'HC1', 'file' => 'test.rpt',
        ]))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_authorized_role_can_read_list_chunks_and_download(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/descargar')) {
                return Http::response("RPT\n-password=***\n", 200, ['Content-Type' => 'text/plain']);
            }
            if (str_contains($request->url(), '/HC1/')) {
                return Http::response(['data' => ['texto' => 'warning', 'fin' => 7, 'mas' => false]], 200);
            }
            return Http::response(['data' => ['fuentes' => []]], 200);
        });

        $this->actingAs(User::findOrFail(32));
        $component = app(ServidoresJuego::class);
        $this->assertTrue($component->loadLogs('academia')['ok']);
        $result = $component->readLog('academia', 'HC1', 'test.rpt');
        $this->assertTrue($result['ok']);
        $this->assertSame(7, $result['data']['fin']);

        $response = $this->get(route('admin.game-servers.logs.download', [
            'server' => 'academia', 'source' => 'HC1', 'file' => 'test.rpt',
        ]))->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        $this->assertStringContainsString('attachment; filename="test.rpt"',
            (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('-password=***', $response->streamedContent());
    }

    public function test_admin_role_can_read_logs_without_extra_grants(): void
    {
        Http::fake(['*' => Http::response(['data' => ['fuentes' => []]], 200)]);
        $this->actingAs(User::findOrFail(33));
        $this->assertTrue(app(ServidoresJuego::class)->loadLogs('academia')['ok']);
    }

    public function test_reading_logs_does_not_modify_livewire_public_state(): void
    {
        Http::fake(['*' => Http::response(['data' => ['texto' => 'private RPT', 'fin' => 11]], 200)]);
        $this->actingAs(User::findOrFail(32));
        $component = app(ServidoresJuego::class);
        $before = $component->all();
        $this->assertTrue($component->readLog('academia', 'HC1', 'test.rpt')['ok']);
        $this->assertSame($before, $component->all());
    }
}
