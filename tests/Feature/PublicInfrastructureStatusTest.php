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
            ->assertJsonPath('services.0.key', 'arma3_academy')
            ->assertJsonPath('services.1.key', 'arma3_operations')
            ->assertJsonPath('services.2.key', 'reforger_academy')
            ->assertJsonPath('services.3.key', 'reforger_operations')
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

    public function test_tsviewer_reports_online_state_and_connected_count(): void
    {
        InfrastructureSetting::query()->create([
            'ts3_enabled' => true,
            'tsviewer_server_id' => 1121394,
        ]);

        Http::fake([
            'www.tsviewer.com/*' => Http::response(
                "document.write('<div class=\"serverstatus_online\">online</div><span>7 / 32</span>');",
                200,
                ['Content-Type' => 'application/javascript'],
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
    }

    public function test_tsviewer_offline_state_is_exposed_without_serverquery(): void
    {
        InfrastructureSetting::query()->create([
            'ts3_enabled' => true,
            'tsviewer_server_id' => 1121394,
        ]);

        Http::fake([
            'www.tsviewer.com/*' => Http::response(
                "document.write('<div class=\"serverstatus_offline\">offline</div>');",
                200,
                ['Content-Type' => 'application/javascript'],
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
