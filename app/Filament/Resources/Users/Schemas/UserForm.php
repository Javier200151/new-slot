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
                DatePicker::make('birth_at')
                    ->label('Fecha de nacimiento')
                    ->displayFormat('d/m/Y')
                    ->native(false),

                Select::make('tutor_id')
                    ->label('Tutor')
                    ->relationship('tutor', 'nick')
                    ->searchable()
                    ->preload()
                    ->nullable(),
                    
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
                DatePicker::make('member_at')
                    ->label('Miembro desde')
                    ->helperText('Fecha en la que el recluta pasó a ser miembro.'),

                
                //TextInput::make('created_by')
                //    ->numeric(),
                //TextInput::make('updated_by')
                //    ->numeric(),
            ]);
    }
}
