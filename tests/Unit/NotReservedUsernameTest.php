<?php

namespace Tests\Unit;

use App\Rules\NotReservedUsername;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotReservedUsernameTest extends TestCase
{
    #[DataProvider('reservedUsernames')]
    public function test_reserved_usernames_are_rejected(string $username): void
    {
        $validator = Validator::make(
            ['nick' => $username],
            ['nick' => [new NotReservedUsername()]],
        );

        $this->assertTrue($validator->fails());
    }

    #[DataProvider('allowedUsernames')]
    public function test_allowed_usernames_are_accepted(string $username): void
    {
        $validator = Validator::make(
            ['nick' => $username],
            [
                'nick' => [
                    'regex:/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/',
                    new NotReservedUsername(),
                ],
            ],
        );

        $this->assertFalse($validator->fails());
    }

    public static function reservedUsernames(): array
    {
        return [
            ['ADMINISTRATOR'],
            ['Admin.Istrator'],
            ['a.d.m.i.n'],
            ['squad-alpha'],
        ];
    }

    public static function allowedUsernames(): array
    {
        return [
            ['asier.sqa1'],
            ['john.doe'],
            ['user_1.test'],
            ['alpha-bravo.2'],
        ];
    }
}
