<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;

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
                Select::make('promo_id')
                    ->label('Promoción')
                    ->relationship('promo', 'id')
                    ->searchable()
                    ->preload(),
                TextInput::make('tagname'),
                Select::make('status_id')
                    ->label('Estado')
                    ->relationship('status', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('roles')
                    ->label('Rol')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->required(),
                FileUpload::make('firma')
                    ->label('Firma')
                    ->image()
                    ->disk('public')
                    ->directory('firmas')
                    ->visibility('public'),
                TextInput::make('arma_uid'),
                TextInput::make('discord_id'),
                TextInput::make('steam_id'),
                DatePicker::make('member_at'),

                Select::make('metopas')
                    ->label('Metopas')
                    ->relationship('metopas', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                //TextInput::make('created_by')
                //    ->numeric(),
                //TextInput::make('updated_by')
                //    ->numeric(),
            ]);
    }
}
