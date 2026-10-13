<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ProfileCompletionService;
use PHPUnit\Framework\TestCase;

class ProfileCompletionServiceTest extends TestCase
{
    public function test_missing_discord_and_steam_are_two_pending_steps_when_birthday_choice_is_confirmed(): void
    {
        $user = new User([
            'nick' => 'Rylod',
            'discord_id' => null,
            'steam_id' => null,
        ]);
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'birthday_visibility_confirmed_at' => '2026-10-13 12:00:00',
        ]), true);

        $completion = (new ProfileCompletionService())->forUser($user);

        $this->assertFalse($completion['is_complete']);
        $this->assertSame(2, $completion['missing_count']);
        $this->assertSame(33, $completion['percent']);
        $this->assertSame(['discord_id', 'steam_id'], array_column($completion['missing'], 'key'));
    }

    public function test_only_valid_identifiers_and_confirmed_birthday_choice_count_as_complete(): void
    {
        $user = new User([
            'discord_id' => '123456789012345678',
            'steam_id' => '76561198000000000',
        ]);
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'birthday_visibility_confirmed_at' => '2026-10-13 12:00:00',
        ]), true);

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
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'birthday_visibility_confirmed_at' => '2026-10-13 12:00:00',
        ]), true);

        $completion = (new ProfileCompletionService())->forUser($user);

        $this->assertSame(2, $completion['missing_count']);
        $this->assertSame(33, $completion['percent']);
    }

    public function test_birthday_visibility_must_be_confirmed_once(): void
    {
        $user = new User([
            'discord_id' => '123456789012345678',
            'steam_id' => '76561198000000000',
            'birthday_public' => true,
        ]);

        $completion = (new ProfileCompletionService())->forUser($user);

        $this->assertFalse($completion['is_complete']);
        $this->assertSame(1, $completion['missing_count']);
        $this->assertSame(67, $completion['percent']);
        $this->assertSame('birthday_visibility', $completion['missing'][0]['key']);
        $this->assertSame('profile-birthday-visibility', $completion['missing'][0]['anchor']);
    }
}
