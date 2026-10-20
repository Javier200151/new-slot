<?php

namespace Tests\Feature;

use App\Support\FallujahEasterEgg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FallujahEasterEggCommandTest extends TestCase
{
    use RefreshDatabase;

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
