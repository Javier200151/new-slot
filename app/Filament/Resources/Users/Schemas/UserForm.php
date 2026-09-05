<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Status;
use App\Rules\NotReservedUsername;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nick')
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
                    ->unique(ignoreRecord: true),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->minLength(8)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->saved(fn (?string $state): bool => filled($state))
                    ->revealable()
                    ->helperText('Déjalo vacío al editar para conservar la contraseña actual.'),
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
                            $set('promo_id', null);
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

                Textarea::make('quote')
                    ->label('Cita')
                    ->rows(3)
                    ->maxLength(500)
                    ->helperText('Frase o cita que se muestra en el perfil público.'),


                FileUpload::make('image')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('users')
                    ->visibility('public')
                    ->preserveFilenames()
                    //->columnSpanFull()
                    ->rule(
                        Rule::dimensions()
                            ->minWidth(100)
                            ->minHeight(100)
                            ->maxWidth(150)
                            ->maxHeight(150)
                    ),

                TextInput::make('discord_id'),
                TextInput::make('steam_id')
                    ->label('Steam ID')
                    ->trim()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(
                        fn (?string $state): ?string => filled($state) ? $state : null
                    )
                    ->validationMessages([
                        'unique' => 'Este Steam ID ya está asignado a otro usuario.',
                    ]),
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
