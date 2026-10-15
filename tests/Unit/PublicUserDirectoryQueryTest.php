<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PublicUserDirectoryQueryTest extends TestCase
{
    public function test_directory_query_selects_only_public_card_fields_and_keeps_relationships(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/PublicUserController.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("->select(['id', 'nick', 'image', 'promo_id', 'status_id'])", $controller);
        $this->assertStringContainsString("'status:id,name,color'", $controller);
        $this->assertStringContainsString("'mainSqaGroup'", $controller);
        $this->assertStringNotContainsString("'promo:id'", $controller);
        $this->assertStringContainsString('$users = $usersQuery->get();', $controller);
    }
}
