<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicRegistrationRateLimitTest extends TestCase
{
    public function test_registration_post_has_an_explicit_request_rate_limit(): void
    {
        $route = Route::getRoutes()->getByName('public.register.store');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
    }
}
