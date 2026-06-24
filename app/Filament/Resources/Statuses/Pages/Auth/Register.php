<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Status;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nick')
                    ->label('Nick')
                    ->required()
                    ->unique('users', 'nick'),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique('users', 'email'),

                TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->required()
                    ->confirmed(),

                TextInput::make('password_confirmation')
                    ->label('Confirmar contraseña')
                    ->password()
                    ->required(),
            ]);
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data['status_id'] = Status::where('name', 'USUARIO')->first()->id;

        return $data;
    }
}