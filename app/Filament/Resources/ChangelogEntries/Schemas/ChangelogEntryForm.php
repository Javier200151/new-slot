<?php

namespace App\Filament\Resources\ChangelogEntries\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChangelogEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Publicación')
                ->columns(4)
                ->schema([
                    TextInput::make('version')
                        ->label('Versión')
                        ->placeholder('v0.8.4 / Hotfix 33-4')
                        ->maxLength(80),
                    DatePicker::make('release_date')
                        ->label('Fecha')
                        ->required()
                        ->default(now()),
                    Toggle::make('is_published')
                        ->label('Publicada')
                        ->default(false),
                    DateTimePicker::make('published_at')
                        ->label('Publicar desde')
                        ->seconds(false),
                ]),

            Repeater::make('changes')
                ->label('Cambios')
                ->schema([
                    Select::make('area')
                        ->label('Área')
                        ->options([
                            'frontend' => 'Frontend',
                            'filament' => 'Filament',
                            'system' => 'Sistema',
                        ])
                        ->required(),
                    Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'new' => 'Nueva función',
                            'improvement' => 'Mejora',
                            'fix' => 'Fix',
                        ])
                        ->required(),
                    BbcodeTextarea::make('description')
                        ->label('Descripción')
                        ->rows(4)
                        ->required()
                        ->maxLength(6000)
                        ->columnSpanFull(),
                    TextInput::make('url')
                        ->label('Enlace opcional')
                        ->url()
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->defaultItems(1)
                ->minItems(1)
                ->reorderable()
                ->collapsible()
                ->itemLabel(function (array $state): ?string {
                    $area = [
                        'frontend' => 'Frontend',
                        'filament' => 'Filament',
                        'system' => 'Sistema',
                    ][$state['area'] ?? ''] ?? null;
                    $type = [
                        'new' => 'Nueva función',
                        'improvement' => 'Mejora',
                        'fix' => 'Fix',
                    ][$state['type'] ?? ''] ?? null;

                    return collect([$area, $type])->filter()->implode(' · ') ?: 'Cambio';
                })
                ->columnSpanFull(),
        ]);
    }
}
