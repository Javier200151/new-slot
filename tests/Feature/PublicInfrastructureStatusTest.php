<?php

namespace Tests\Feature;

use App\Models\InfrastructureSetting;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicInfrastructureStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('public.infrastructure.status.v2');
        Schema::dropIfExists('infrastructure_settings');

        Schema::create('infrastructure_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('arma3_academy_host')->nullable();
            $table->unsignedSmallInteger('arma3_academy_query_port')->nullable();
            $table->string('arma3_operations_host')->nullable();
            $table->unsignedSmallInteger('arma3_operations_query_port')->nullable();
            $table->string('reforger_academy_host')->nullable();
            $table->unsignedSmallInteger('reforger_academy_query_port')->nullable();
            $table->string('reforger_operations_host')->nullable();
            $table->unsignedSmallInteger('reforger_operations_query_port')->nullable();
            $table->boolean('ts3_enabled')->default(false);
            $table->unsignedBigInteger('tsviewer_server_id')->nullable();
            $table->json('extra_arma_servers')->nullable();
            $table->json('arma_servers')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Cache::forget('public.infrastructure.status.v2');
        Schema::dropIfExists('infrastructure_settings');
        parent::tearDown();
    }

    private function userWithStatus(string $statusName): User
    {
        $user = new User();
        $user->id = 999;
        $user->exists = true;
        $user->setRelation('status', new Status(['name' => $statusName]));

        return $user;
    }

    public function test_guest_cannot_read_infrastructure_status(): void
    {
        $this->getJson(route('infrastructure.status'))->assertForbidden();
    }

    public function test_active_member_can_read_infrastructure_status(): void
    {
        $this->actingAs($this->userWithStatus('ACTIVO'));

        $response = $this->getJson(route('infrastructure.status'));

        $response
            ->assertOk()
            ->assertJsonCount(4, 'services')
            ->assertJsonPath('services.0.label', 'ArmA 3 Academia')
            ->assertJsonPath('services.1.label', 'ArmA 3 Operativos')
            ->assertJsonPath('services.2.label', 'ArmA Reforger Academia')
            ->assertJsonPath('services.3.label', 'ArmA Reforger Operativos')
            ->assertJsonPath('teamspeak.enabled', false)
            ->assertJsonPath('teamspeak.provider', 'tsviewer');
    }

    public function test_recruit_can_read_infrastructure_status(): void
    {
        $this->actingAs($this->userWithStatus('RECLUTA'));

        $this->getJson(route('infrastructure.status'))->assertOk();
    }

    public function test_other_statuses_cannot_read_infrastructure_status(): void
    {
        $this->actingAs($this->userWithStatus('RESERVA'));

        $this->getJson(route('infrastructure.status'))->assertForbidden();
    }

    public function test_all_arma_servers_keep_configured_order_and_use_game_port_plus_one(): void
    {
        InfrastructureSetting::query()->create([
            'arma_servers' => [
                [
                    'name' => 'ArmA Reforger Operativos',
                    'host' => 'reforger.example.test',
                    'game_port' => 2001,
                ],
                [
                    'name' => 'ArmA 3 Academia',
                    'host' => 'academy.example.test',
                    'game_port' => 2302,
                ],
                [
                    'name' => 'ArmA 3 Zeus',
                    'host' => 'zeus.example.test',
                    'game_port' => 2402,
                ],
            ],
        ]);

        $service = new class extends \App\Services\InfrastructureStatusService
        {
            public array $queries = [];

            protected function queryA2sInfo(string $host, int $port): ?array
            {
                $this->queries[] = [$host, $port];

                return [
                    'name' => $host,
                    'players' => 1,
                    'max_players' => 64,
                ];
            }
        };

        $snapshot = $service->snapshot(force: true);

        $this->assertSame([
            'ArmA Reforger Operativos',
            'ArmA 3 Academia',
            'ArmA 3 Zeus',
        ], array_column($snapshot['services'], 'label'));

        $this->assertSame([
            ['reforger.example.test', 2002],
            ['academy.example.test', 2303],
            ['zeus.example.test', 2403],
        ], $service->queries);
    }

    public function test_tsviewer_reports_online_state_and_connected_count(): void
    {
        InfrastructureSetting::query()->create([
            'ts3_enabled' => true,
            'tsviewer_server_id' => 1121394,
        ]);

        Http::fake([
            'www.tsviewer.com/*' => Http::response(
                '<html><body><div>See when friends come online</div><span class="status">ONLINE</span><span>7 / 32 user</span></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $this->actingAs($this->userWithStatus('ACTIVO'));

        $this->getJson(route('infrastructure.status'))
            ->assertOk()
            ->assertJsonPath('teamspeak.enabled', true)
            ->assertJsonPath('teamspeak.configured', true)
            ->assertJsonPath('teamspeak.available', true)
            ->assertJsonPath('teamspeak.online', true)
            ->assertJsonPath('teamspeak.players', 7)
            ->assertJsonPath('teamspeak.max_players', 32)
            ->assertJsonPath('teamspeak.provider', 'tsviewer');

        Http::assertSent(function ($request): bool {
            $url = $request->url();

            return str_contains($url, 'www.tsviewer.com/index.php')
                && str_contains($url, 'page=ts_viewer')
                && str_contains($url, 'ID=1121394');
        });
    }

    public function test_tsviewer_offline_state_is_exposed_without_serverquery(): void
    {
        InfrastructureSetting::query()->create([
            'ts3_enabled' => true,
            'tsviewer_server_id' => 1121394,
        ]);

        Http::fake([
            'www.tsviewer.com/*' => Http::response(
                '<html><body><span class="status">OFFLINE</span></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $this->actingAs($this->userWithStatus('ACTIVO'));

        $this->getJson(route('infrastructure.status'))
            ->assertOk()
            ->assertJsonPath('teamspeak.available', true)
            ->assertJsonPath('teamspeak.online', false)
            ->assertJsonPath('teamspeak.players', null);
    }
}
