<?php

namespace App\Filament\Pages\Auth;

use App\Models\Status;
use App\Rules\NotReservedUsername;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nick')
                    ->label('Nick')
                    ->required()
                    ->minLength(3)
                    ->maxLength(30)
                    ->rules([
                        'regex:/^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/',
                        new NotReservedUsername(),
                    ])
                    ->validationMessages([
                        'required' => 'El nick es obligatorio.',
                        'min' => 'El nick debe tener al menos 3 caracteres.',
                        'max' => 'El nick no puede tener más de 30 caracteres.',
                        'regex' => 'El nick solo puede contener letras sin tildes, números, guiones (-), guiones bajos (_) y puntos (.). No puede comenzar ni terminar con un punto, ni contener puntos consecutivos.',
                        'unique' => 'Este nick ya está en uso.',
                    ])
                    ->helperText('Entre 3 y 30 caracteres. Se permiten letras, números, guiones, guiones bajos y puntos.')
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
