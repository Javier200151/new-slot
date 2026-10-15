<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ProfileCompletionService;
use App\Services\Wiki\WikiAccessPolicy;
use App\Services\Wiki\WikiLinkClient;
use Illuminate\Support\Facades\Cache;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProfileCompletionWikiResilienceTest extends TestCase
{
    private function eligibleUser(): User
    {
        $user = new User(['nick' => 'MiembroPrueba', 'email' => 'member@example.test']);
        $user->id = 4872;
        $user->exists = true;

        $policy = Mockery::mock(WikiAccessPolicy::class);
        $policy->shouldReceive('canAuthenticate')->andReturn(true);
        $policy->shouldReceive('isSuperAdmin')->andReturn(false);
        $this->app->instance(WikiAccessPolicy::class, $policy);
        config()->set('wiki_link.enabled', true);
        Cache::flush();

        return $user;
    }

    public function test_unavailable_wiki_is_temporarily_cached_and_does_not_flag_a_user_as_pending(): void
    {
        $user = $this->eligibleUser();
        $client = Mockery::mock(WikiLinkClient::class);
        $client->shouldReceive('call')->once()
            ->with('status', 'sqa-user-4872', 'member@example.test', null, null, 2)
            ->andThrow(new RuntimeException('Wiki unavailable'));
        $this->app->instance(WikiLinkClient::class, $client);

        $service = app(ProfileCompletionService::class);
        $first = $service->forUser($user);
        $second = $service->forUser($user);

        $this->assertNotContains('wiki_link', array_column($first['missing'], 'key'));
        $this->assertNotContains('wiki_link', array_column($second['missing'], 'key'));
        $this->assertFalse(Cache::get('wiki_link:pending_profile:4872'));
    }

    public function test_pending_historical_wiki_account_is_cached_without_repeating_remote_call(): void
    {
        $user = $this->eligibleUser();
        $client = Mockery::mock(WikiLinkClient::class);
        $client->shouldReceive('call')->once()
            ->with('status', 'sqa-user-4872', 'member@example.test', null, null, 2)
            ->andReturn(['status' => 'pending_link']);
        $this->app->instance(WikiLinkClient::class, $client);

        $service = app(ProfileCompletionService::class);
        $first = $service->forUser($user);
        $second = $service->forUser($user);

        $this->assertContains('wiki_link', array_column($first['missing'], 'key'));
        $this->assertContains('wiki_link', array_column($second['missing'], 'key'));
        $this->assertTrue(Cache::get('wiki_link:pending_profile:4872'));
    }
}
