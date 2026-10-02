<?php

namespace App\Filament\Resources\VeterancySettings\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use App\Models\ForumCategory;
use App\Models\Metopa;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VeterancySettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Publicación en el foro')
                ->description('Define dónde se publicará el reconocimiento cuando se aprueben una o varias veteranías.')
                ->schema([
                    Select::make('forum_category_id')
                        ->label('Subcategoría del foro')
                        ->options(fn () => ForumCategory::query()
                            ->where('is_enabled', true)
                            ->where('system_type', '!=', ForumCategory::TYPE_DIARY)
                            ->orderBy('sort_order')
                            ->orderBy('title')
                            ->pluck('title', 'id'))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Elige la subcategoría que recibirá la publicación automática de veteranías.'),

                    BbcodeTextarea::make('post_body')
                        ->label('Texto introductorio')
                        ->rows(8)
                        ->columnSpanFull()
                        ->helperText('Este texto aparecerá al inicio del post. Después se añadirán automáticamente los galardonados y las imágenes de las metopas correspondientes.'),
                ]),

            Section::make('Metopas por veteranía')
                ->description('Selecciona qué metopa se entrega al aprobar cada nivel.')
                ->schema([
                    self::metopaSelect('bronze_metopa_id', 'Veterano Bronce · 1 año efectivo'),
                    self::metopaSelect('silver_metopa_id', 'Veterano Plata · 3 años efectivos'),
                    self::metopaSelect('gold_metopa_id', 'Veterano Oro · 5 años efectivos'),
                ])
                ->columns(3),
        ]);
    }

    private static function metopaSelect(string $field, string $label): Select
    {
        return Select::make($field)
            ->label($label)
            ->options(fn () => Metopa::query()->orderBy('name')->pluck('name', 'id'))
            ->searchable()
            ->preload()
            ->required();
    }
}
