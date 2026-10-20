<?php

namespace Tests\Unit;

use App\Support\SquadEasterEggs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SquadEasterEggPathsTest extends TestCase
{
    public static function allowedPaths(): array
    {
        return array_map(fn ($path) => [$path], [
            '/', '/eventos', '/area/foro', '/actividades', '/usuarios',
            '/comunidad/organigrama', '/metopas', '/tesoreria',
        ]);
    }

    #[DataProvider('allowedPaths')]
    public function test_grenade_is_available_only_on_whitelisted_routes(string $path): void
    {
        self::assertTrue(SquadEasterEggs::grenadeAllowedOn($path));
        self::assertTrue(SquadEasterEggs::grenadeAllowedOn(rtrim($path, '/') . '/'));
    }

    public function test_grenade_is_not_available_on_editable_or_nested_routes(): void
    {
        foreach (['/eventos/67', '/area/foro/categoria/tema', '/usuarios/Rylod',
            '/perfil', '/admin', '/admin/easter_eggs', '/tesoreria/editar', '/area/diario'] as $route) {
            self::assertFalse(SquadEasterEggs::grenadeAllowedOn($route), $route);
        }
    }
}
