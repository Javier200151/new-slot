<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use App\Models\Status;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nick')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('promo_id')
                    ->label('Promoción')
                    ->numeric()
                    ->helperText('Introduce el número de promoción. Si no existe, se generará automáticamente.')
                    ->disabled(function ($get) {
                        $reclutaId = Status::where('name', 'RECLUTA')->value('id');

                        return (int) $get('status_id') === (int) $reclutaId;
                    })
                    ->dehydrated(true),
                TextInput::make('tagname'),
                Select::make('status_id')
                    ->label('Estado')
                    ->relationship('status', 'name')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $reclutaId = Status::where('name', 'RECLUTA')->value('id');

                        if ((int) $state === (int) $reclutaId) {
                            $set('promo_id', 1);
                        }
                    }),
                Select::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->required(),
                TextInput::make('firma')
                    ->disabled()
                    ->default(fn ($record) => $record?->getSignatureUrl()),
                TextInput::make('arma_uid'),
                TextInput::make('discord_id'),
                TextInput::make('steam_id'),
                DatePicker::make('member_at'),

                Select::make('metopas')
                    ->label('Metopas')
                    ->relationship('metopas', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->disabled(function ($get) {
                        $reclutaId = Status::where('name', 'RECLUTA')->value('id');

                        return (int) $get('status_id') === (int) $reclutaId;
                    }),
                //TextInput::make('created_by')
                //    ->numeric(),
                //TextInput::make('updated_by')
                //    ->numeric(),
            ]);
    }
}
