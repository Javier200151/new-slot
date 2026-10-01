<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicInfrastructureStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('public.infrastructure.status.v1');
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
            $table->string('ts3_host')->nullable();
            $table->unsignedSmallInteger('ts3_query_port')->default(10011);
            $table->unsignedSmallInteger('ts3_virtual_server_id')->default(1);
            $table->string('ts3_query_user')->nullable();
            $table->text('ts3_query_password')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Cache::forget('public.infrastructure.status.v1');
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
            ->assertJsonPath('teamspeak.enabled', false);
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
}
