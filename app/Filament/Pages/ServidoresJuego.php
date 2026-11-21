<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Pantalla de maqueta: no realiza peticiones al agente ni cambios de estado reales.
 */
class ServidoresJuego extends Page
{
    protected static ?string $slug = 'servidores-juego';

    protected static ?string $title = 'Servidores de juego';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.servidores-juego';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->hasRole('admin') || $user->can('game-servers.view')));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }
}
