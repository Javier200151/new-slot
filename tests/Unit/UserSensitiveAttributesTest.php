<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserSensitiveAttributesTest extends TestCase
{
    public function test_password_and_remember_token_are_hidden_from_serialization(): void
    {
        $user = new User();
        $user->setRawAttributes([
            'nick' => 'TestUser',
            'email' => 'test@example.com',
            'password' => 'hashed-secret',
            'remember_token' => 'remember-secret',
        ]);

        $attributes = $user->attributesToArray();

        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
    }
    public function test_steam_id_is_normalized_before_it_is_saved(): void
    {
        $user = new User();

        $user->steam_id = ' 76561198000000000 ';
        $this->assertSame('76561198000000000', $user->getAttributes()['steam_id']);

        $user->steam_id = '   ';
        $this->assertNull($user->getAttributes()['steam_id']);
    }

}
