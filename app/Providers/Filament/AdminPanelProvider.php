<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationItem;
use Filament\Support\Assets\Css;
//use Pxlrbt\FilamentActivityLog\FilamentActivityLogPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Administración de Squad Alpha') //Nombre que sale en la parte de arriba de administración
            ->login()
            ->registration(\App\Filament\Pages\Auth\Register::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->assets([
                Css::make(
                    'filament-custom',
                    '/css/filament-custom.css?v='
                        . filemtime(
                            public_path(
                                'css/filament-custom.css'
                            )
                        )
                ),
            ])
            ->navigationGroups([
                NavigationGroup::make('Operativos'),
                NavigationGroup::make('Eventos'),
                NavigationGroup::make('Streams'),
                NavigationGroup::make('Comunidad'),
                NavigationGroup::make('Usuarios'),
                NavigationGroup::make('Sistema'),    
            ])
            ->navigationItems([
                NavigationItem::make('Volver a la web')
                    ->url(fn (): string => route('home'))
                    ->icon('heroicon-o-arrow-left')
                    ->sort(-100),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                //FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
