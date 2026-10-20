<?php

namespace Tests\Unit;

use App\Models\Ally;
use App\Models\Status;
use App\Models\User;
use Tests\TestCase;

class UserOrbatDisplayNameTest extends TestCase
{
    public function test_regular_user_uses_nick_in_orbat(): void
    {
        $user = new User();
        $user->nick = 'Rylod';
        $user->setRelation('status', new Status(['name' => 'ACTIVO']));

        $this->assertSame('Rylod', $user->orbatDisplayName());
    }

    public function test_allied_user_includes_ally_name_in_orbat(): void
    {
        $user = new User();
        $user->nick = 'Invitado';
        $user->setRelation('status', new Status(['name' => 'ALIADO']));
        $user->setRelation('ally', new Ally(['name' => 'Clan Ejemplo']));

        $this->assertSame('[Clan Ejemplo] Invitado', $user->orbatDisplayName());
    }

    public function test_allied_user_without_group_uses_nick(): void
    {
        $user = new User();
        $user->nick = 'Invitado';
        $user->setRelation('status', new Status(['name' => 'ALIADO']));
        $user->setRelation('ally', null);

        $this->assertSame('Invitado', $user->orbatDisplayName());
        $this->assertContains('ally_id', $user->getFillable());
    }
}
