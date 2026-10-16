<?php

namespace Tests\Unit;

use App\Models\Ally;
use App\Models\EventSlot;
use App\Models\Status;
use App\Models\User;
use Tests\TestCase;

class OrbatAllyIdentityTest extends TestCase
{
    public function test_user_with_ally_status_displays_linked_community_tag(): void
    {
        $user = new User(['nick' => 'Ghost']);
        $user->setRelation('status', new Status(['name' => 'ALIADO']));
        $user->setRelation('ally', new Ally(['name' => 'BEAR']));

        $this->assertSame('[BEAR] Ghost', $user->orbatDisplayName());
    }

    public function test_regular_user_keeps_original_nick(): void
    {
        $user = new User(['nick' => 'Ghost']);
        $user->setRelation('status', new Status(['name' => 'ACTIVO']));
        $user->setRelation('ally', new Ally(['name' => 'BEAR']));

        $this->assertSame('Ghost', $user->orbatDisplayName());
    }

    public function test_ally_nickname_can_be_stored_on_a_slot(): void
    {
        $slot = new EventSlot(['ally_id' => 3, 'ally_nickname' => 'John_Smith']);

        $this->assertSame('John_Smith', $slot->ally_nickname);
    }
}
