<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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
            ->globalSearch(false)
            ->sidebarFullyCollapsibleOnDesktop()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): HtmlString {
                    $styles = [];

                    foreach ([
                        'newslot-filament-custom' => public_path('css/filament-custom.css'),
                        'newslot-personal-dashboard' => public_path('css/personal-dashboard.css'),
                    ] as $id => $path) {
                        if (! is_file($path)) {
                            continue;
                        }

                        $css = file_get_contents($path);

                        if ($css === false) {
                            continue;
                        }

                        // Estos estilos son críticos para el layout del panel. Se inyectan
                        // inline para no depender de la caché de Caddy/navegador ni del
                        // registro de assets de Filament después de un despliegue.
                        $css = str_replace('</style>', '<\/style>', $css);
                        $styles[] = "<style id=\"{$id}\">{$css}</style>";
                    }

                    return new HtmlString(implode("\n", $styles));
                },
            )
            ->assets([
                Js::make(
                    'filament-orbat-layout',
                    asset('js/filament-orbat-layout.js')
                        . '?v='
                        . filemtime(public_path('js/filament-orbat-layout.js'))
                )->defer(),
                Js::make(
                    'filament-bbcode-editor',
                    asset('js/filament-bbcode-editor.js')
                        . '?v='
                        . filemtime(public_path('js/filament-bbcode-editor.js'))
                )->defer(),
            ])
            ->navigationGroups([
                NavigationGroup::make('Actividades'),
                NavigationGroup::make('Eventos'),
                NavigationGroup::make('Streams'),
                NavigationGroup::make('Comunidad'),
                NavigationGroup::make('Usuarios'),
                NavigationGroup::make('Procedimientos'),
                NavigationGroup::make('Reclutamiento'),
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
