<?php

namespace App\Filament\Resources\PublicNavigationSettings\Schemas;

use App\Support\PublicNavigation;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class PublicNavigationSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Header público')
                ->description(
                    'Ordena hasta 6 posiciones del menú principal. Las páginas y rutas siguen definiéndose por código; aquí solo decides cómo se muestran. El Área 51 / Reclutamiento y los controles de cuenta se añaden automáticamente y no consumen estas posiciones.'
                )
                ->schema([
                    Repeater::make('items')
                        ->label('Posiciones del menú')
                        ->schema([
                            Select::make('type')
                                ->label('Presentación')
                                ->options([
                                    'link' => 'Enlace individual',
                                    'dropdown' => 'Desplegable',
                                ])
                                ->default('link')
                                ->required()
                                ->live(),

                            TextInput::make('label')
                                ->label(fn (Get $get): string => $get('type') === 'dropdown'
                                    ? 'Título del desplegable'
                                    : 'Texto mostrado')
                                ->helperText(fn (Get $get): ?string => $get('type') === 'link'
                                    ? 'Puedes dejarlo vacío para usar el nombre normal de la página.'
                                    : null)
                                ->required(fn (Get $get): bool => $get('type') === 'dropdown')
                                ->maxLength(80),

                            Select::make('destination')
                                ->label('Página / destino')
                                ->options(PublicNavigation::destinationOptions())
                                ->searchable()
                                ->required(fn (Get $get): bool => $get('type') === 'link')
                                ->visible(fn (Get $get): bool => $get('type') === 'link'),

                            Repeater::make('children')
                                ->label('Elementos del desplegable')
                                ->schema([
                                    Select::make('destination')
                                        ->label('Página / destino')
                                        ->options(PublicNavigation::destinationOptions())
                                        ->searchable()
                                        ->required(),
                                    TextInput::make('label')
                                        ->label('Texto mostrado')
                                        ->helperText('Déjalo vacío para usar el nombre normal de la página.')
                                        ->maxLength(80),
                                ])
                                ->columns(2)
                                ->minItems(1)
                                ->maxItems(20)
                                ->defaultItems(1)
                                ->reorderable()
                                ->collapsible()
                                ->visible(fn (Get $get): bool => $get('type') === 'dropdown')
                                ->required(fn (Get $get): bool => $get('type') === 'dropdown')
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->minItems(1)
                        ->maxItems(PublicNavigation::MAX_TOP_LEVEL_ITEMS)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            if (($state['type'] ?? null) === 'dropdown') {
                                return trim((string) ($state['label'] ?? '')) ?: 'Desplegable';
                            }

                            $destination = (string) ($state['destination'] ?? '');

                            return trim((string) ($state['label'] ?? ''))
                                ?: ($destination !== '' ? PublicNavigation::label($destination) : 'Enlace');
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
