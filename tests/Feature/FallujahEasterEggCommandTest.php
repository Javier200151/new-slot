<?php

namespace Tests\Feature;

use App\Support\FallujahEasterEgg;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FallujahEasterEggCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('sqa_easter_egg_overrides', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('mode');
            $table->timestamps();
        });
    }

    public function test_command_sets_override_then_returns_to_calendar(): void
    {
        $this->artisan('sqa:fallujah', ['action' => 'on'])->assertExitCode(0);
        $this->assertSame('on', FallujahEasterEgg::mode());

        $this->artisan('sqa:fallujah', ['action' => 'off'])->assertExitCode(0);
        $this->assertSame('off', FallujahEasterEgg::mode());

        $this->artisan('sqa:fallujah', ['action' => 'auto'])->assertExitCode(0);
        $this->assertSame('auto', FallujahEasterEgg::mode());
    }
}
