<?php

namespace App\Filament\Resources\HomepageSettings\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HomepageSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'lg' => 2,
            ])
                ->schema([
                    Grid::make(1)
                        ->schema([
                            Section::make('Contacto y alistamiento')
                                ->description(
                                    'Controla si el formulario público admite solicitudes de alistamiento. '
                                    . 'Todas las consultas y solicitudes se envían usando el SMTP configurado para NewSlot.'
                                )
                                ->schema([
                                    Toggle::make('recruitment_open')
                                        ->label('Alistamiento abierto')
                                        ->helperText('Si está desactivado, la portada muestra únicamente el formulario de consultas.'),
                                ]),

                            Section::make('Google Fotos')
                                ->schema([
                                    TextInput::make('google_photos_url')
                                        ->label('Álbum público de Google Fotos')
                                        ->url()
                                        ->helperText('Enlace compartido del álbum del que la portada obtiene automáticamente las 6 últimas fotos.')
                                        ->maxLength(2048)
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Bloque de VODs')->schema([
                                TextInput::make('streams_title')->label('Título')->required()->maxLength(255),
                                BbcodeTextarea::make('streams_intro')->label('Introducción')->rows(3)->columnSpanFull(),
                            ]),
                        ])
                        ->columnSpan(1),

                    Grid::make(1)
                        ->schema([
                            Section::make('Pie de página y redes sociales')
                                ->description('Configuración única de redes sociales para toda la web. Instagram se reutiliza también en el bloque de últimas publicaciones de la portada. Si un enlace queda vacío, su icono no se muestra en el pie.')
                                ->schema([
                                    TextInput::make('x_url')
                                        ->label('X / Twitter')
                                        ->url()
                                        ->maxLength(255),
                                    TextInput::make('instagram_url')
                                        ->label('Instagram')
                                        ->url()
                                        ->maxLength(255)
                                        ->helperText('Este mismo enlace se utiliza en la portada y en el pie de página.'),
                                    TextInput::make('youtube_url')
                                        ->label('YouTube')
                                        ->url()
                                        ->maxLength(255),
                                    TextInput::make('discord_invite_url')
                                        ->label('Discord · invitación pública')
                                        ->url()
                                        ->maxLength(2048)
                                        ->helperText('Puede editarse manualmente. El botón «Regenerar invitación Discord» sustituye este valor usando el bot.'),
                                    Toggle::make('discord_invite_auto_refresh')
                                        ->label('Renovar automáticamente la invitación de Discord')
                                        ->helperText('NewSlot comprueba a diario la invitación y crea una nueva cuando queda menos de 24 h para caducar. Requiere un Canal ID configurado en Procedimientos → Config. procedimientos → Discord.')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),

                            Section::make('Servicios conectados')
                                ->description('Logos que se muestran en Mi perfil dentro de Cuentas vinculadas. El bloque está preparado para poder incorporar más servicios en el futuro.')
                                ->schema([
                                    FileUpload::make('discord_account_logo')
                                        ->label('Logo de Discord')
                                        ->helperText('Sube el logo oficial en PNG, WebP o SVG. Se mostrará junto a Discord en Mi perfil.')
                                        ->image()
                                        ->acceptedFileTypes(['image/png', 'image/webp', 'image/svg+xml'])
                                        ->disk('public')
                                        ->directory('site/linked-accounts')
                                        ->visibility('public')
                                        ->maxSize(1024)
                                        ->previewable(false)
                                        ->deletable(),
                                    FileUpload::make('steam_account_logo')
                                        ->label('Logo de Steam')
                                        ->helperText('Sube el logo oficial en PNG, WebP o SVG. Se mostrará junto a Steam en Mi perfil.')
                                        ->image()
                                        ->acceptedFileTypes(['image/png', 'image/webp', 'image/svg+xml'])
                                        ->disk('public')
                                        ->directory('site/linked-accounts')
                                        ->visibility('public')
                                        ->maxSize(1024)
                                        ->previewable(false)
                                        ->deletable(),
                                ])
                                ->columns(2),

                            Section::make('Bloque de actualidad')->schema([
                                TextInput::make('news_title')->label('Título')->required()->maxLength(255),
                                BbcodeTextarea::make('news_intro')->label('Introducción')->rows(3)->columnSpanFull(),
                            ]),
                        ])
                        ->columnSpan(1),
                ])
                ->columnSpanFull(),
        ]);
    }
}
