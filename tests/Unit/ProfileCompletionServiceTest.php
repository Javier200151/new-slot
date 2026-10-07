<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ProfileCompletionService;
use PHPUnit\Framework\TestCase;

class ProfileCompletionServiceTest extends TestCase
{
    public function test_missing_discord_and_steam_are_two_pending_steps(): void
    {
        $user = new User([
            'nick' => 'Rylod',
            'discord_id' => null,
            'steam_id' => null,
        ]);

        $completion = (new ProfileCompletionService())->forUser($user);

        $this->assertFalse($completion['is_complete']);
        $this->assertSame(2, $completion['missing_count']);
        $this->assertSame(0, $completion['percent']);
        $this->assertSame(['discord_id', 'steam_id'], array_column($completion['missing'], 'key'));
    }

    public function test_only_valid_identifiers_count_as_complete(): void
    {
        $user = new User([
            'discord_id' => '123456789012345678',
            'steam_id' => '76561198000000000',
        ]);

        $completion = (new ProfileCompletionService())->forUser($user);

        $this->assertTrue($completion['is_complete']);
        $this->assertSame(0, $completion['missing_count']);
        $this->assertSame(100, $completion['percent']);
    }

    public function test_invalid_identifiers_keep_profile_incomplete(): void
    {
        $user = new User([
            'discord_id' => 'usuario-discord',
            'steam_id' => '1234',
        ]);

        $completion = (new ProfileCompletionService())->forUser($user);

        $this->assertSame(2, $completion['missing_count']);
        $this->assertSame(0, $completion['percent']);
    }

    public function test_historical_identifiers_remain_complete_without_linked_at(): void
    {
        $user = new User([
            'discord_id' => '123456789012345678',
            'steam_id' => '76561198000000000',
        ]);

        $completion = (new ProfileCompletionService())->forUser($user);

        $this->assertTrue($completion['is_complete']);
        $this->assertSame('Configurado manualmente; puedes verificarlo con Discord.', $completion['steps'][0]['description']);
        $this->assertSame('SteamID64 configurado manualmente.', $completion['steps'][1]['description']);
    }

    public function test_verified_links_expose_friendly_completion_descriptions(): void
    {
        $user = new User([
            'discord_id' => '123456789012345678',
            'discord_username' => 'rylod',
            'steam_id' => '76561198000000000',
        ]);

        // This is a pure PHPUnit unit test (it does not bootstrap Laravel).
        // Setting datetime-cast attributes through mass assignment makes
        // Eloquent ask for the application's DB/config services in order to
        // determine the date format. Use raw attributes here because this test
        // only needs to verify ProfileCompletionService's linked-state logic.
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'discord_linked_at' => '2026-10-07 10:00:00',
            'steam_linked_at' => '2026-10-07 10:00:00',
        ]), sync: true);

        $completion = (new ProfileCompletionService())->forUser($user);

        $this->assertSame('Vinculado como @rylod', $completion['steps'][0]['description']);
        $this->assertSame('Steam vinculado · 76561198000000000', $completion['steps'][1]['description']);
    }

}
