<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserRecruitmentDateFieldTest extends TestCase
{
    public function test_recruitment_entry_is_mass_assignable_and_is_a_date_distinct_from_membership(): void
    {
        $user = new User();

        self::assertContains('recruited_at', $user->getFillable());
        self::assertContains('member_at', $user->getFillable());
        self::assertSame('date', $user->getCasts()['recruited_at']);
        self::assertSame('date', $user->getCasts()['member_at']);
    }
}
