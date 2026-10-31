<?php

namespace App\Filament\Resources\HomepageSettings\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use App\Models\Status;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
                                    . 'Todas las consultas y solicitudes se envían usando el SMTP configurado para Squad ALPHA.'
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
                                        ->label('Invitación al servidor de Discord')
                                        ->placeholder('https://discord.gg/...')
                                        ->url()
                                        ->maxLength(2048)
                                        ->copyable(copyMessage: 'Enlace copiado')
                                        ->helperText('Puedes copiar el enlace o escribir otro. Al guardar uno manualmente, se desactiva la renovación automática.')
                                        ->columnSpanFull(),
                                    Toggle::make('discord_invite_auto_refresh')
                                        ->label('Mantener la invitación actualizada automáticamente')
                                        ->helperText('Al activarlo y guardar, el bot crea una invitación si hace falta. Si caduca, se renovará automáticamente cada día.')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),

                            Section::make('Servicios conectados')
                                ->description('Logos de servicios externos. Discord y Steam se muestran en Mi perfil; Telegram se utiliza en los correos automáticos de alta y reactivación.')
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
                                    FileUpload::make('telegram_account_logo')
                                        ->label('Logo de Telegram')
                                        ->helperText('Sube el logo de Telegram que se utilizará en los correos automáticos de alta y reactivación. Para máxima compatibilidad con clientes de correo se recomienda PNG.')
                                        ->image()
                                        ->acceptedFileTypes(['image/png', 'image/webp', 'image/svg+xml'])
                                        ->disk('public')
                                        ->directory('site/linked-accounts')
                                        ->visibility('public')
                                        ->maxSize(1024)
                                        ->previewable(false)
                                        ->deletable(),
                                ])
                                ->columns(3),

                            Section::make('Tablón de anuncios')->schema([
                                TextInput::make('news_title')->label('Título')->required()->maxLength(255),
                                BbcodeTextarea::make('news_intro')->label('Introducción')->rows(3)->columnSpanFull(),
                                Select::make('news_home_limit')
                                    ->label('Anuncios en la portada')
                                    ->options(array_combine(range(1, 6), array_map('strval', range(1, 6))))
                                    ->default(6)
                                    ->required()
                                    ->native(false),
                                Select::make('news_visible_status_ids')
                                    ->label('Estados que pueden ver el tablón')
                                    ->options(fn (): array => Status::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn (): array => Status::query()
                                        ->whereIn('name', ['ACTIVO', 'RESERVA', 'RECLUTA'])
                                        ->pluck('id')->all())
                                    ->helperText('Solo estos estados podrán consultar los anuncios y su historial.')
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->columnSpan(1),
                ])
                ->columnSpanFull(),
        ]);
    }
}
