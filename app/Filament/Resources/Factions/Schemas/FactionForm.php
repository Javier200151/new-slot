<?php

namespace App\Filament\Resources\Factions\Schemas;

use App\Models\Army;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class FactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('army_id')
                    ->label('Ejército')
                    ->relationship(
                        'army',
                        'name',
                        modifyQueryUsing:
                            fn (Builder $query): Builder =>
                                $query
                                    ->with('country')
                                    ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        function (Army $army): string {
                            $name = e($army->name);
                            $country = $army->country;

                            if (! filled($country?->image)) {
                                return $name;
                            }

                            $imageUrl = e(
                                Storage::disk('public')->url(
                                    $country->image
                                )
                            );

                            $countryName = e(
                                $country->name ?? 'País'
                            );

                            return <<<HTML
                                <div style="display:flex;align-items:center;gap:9px;min-width:0;">
                                    <img
                                        src="{$imageUrl}"
                                        alt=""
                                        title="{$countryName}"
                                        style="width:28px;height:20px;object-fit:cover;border-radius:3px;flex:0 0 auto;"
                                    >
                                    <span style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {$name}
                                    </span>
                                </div>
                            HTML;
                        }
                    )
                    ->allowHtml()
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('side_id')
                    ->label('Bando')
                    ->relationship('side', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
            ]);
    }
}
