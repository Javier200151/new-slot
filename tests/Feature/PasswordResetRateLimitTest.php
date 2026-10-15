<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PasswordResetRateLimitTest extends TestCase
{
    public function test_password_reset_submission_has_explicit_rate_limit(): void
    {
        $route = Route::getRoutes()->getByName('password.update');

        $this->assertNotNull($route);
        $this->assertSame('POST', $route->methods()[0]);
        $this->assertContains('guest', $route->gatherMiddleware());
        $this->assertContains('throttle:5,1', $route->gatherMiddleware());
    }

    public function test_password_reset_link_request_retains_rate_limit(): void
    {
        $route = Route::getRoutes()->getByName('password.email');

        $this->assertNotNull($route);
        $this->assertContains('throttle:5,1', $route->gatherMiddleware());
    }
}
