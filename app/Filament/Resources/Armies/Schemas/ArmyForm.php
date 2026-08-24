<?php

namespace App\Filament\Resources\Armies\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Country;
use Illuminate\Support\Facades\Storage;

class ArmyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('country_id')
                    ->label('País')
                    ->relationship(
                        name: 'country',
                        titleAttribute: 'name',
                    )
                    ->getOptionLabelFromRecordUsing(
                        function (Country $country): string {
                            $name = e($country->name);

                            if (! filled($country->image)) {
                                return $name;
                            }

                            $imageUrl = e(
                                Storage::disk('public')->url(
                                    $country->image
                                )
                            );

                            return <<<HTML
                                <div style="
                                    display: flex;
                                    align-items: center;
                                    gap: 10px;
                                ">
                                    <img
                                        src="{$imageUrl}"
                                        alt=""
                                        style="
                                            width: 28px;
                                            height: 20px;
                                            object-fit: cover;
                                            border-radius: 3px;
                                            flex-shrink: 0;
                                        "
                                    >

                                    <span>{$name}</span>
                                </div>
                            HTML;
                        }
                    )
                    ->allowHtml()
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('description')
                    ->label('Descripción')
                    ->maxLength(255),

                FileUpload::make('image')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('armies')
                    ->visibility('public')
                    ->preserveFilenames(),
            ]);
    }
}
