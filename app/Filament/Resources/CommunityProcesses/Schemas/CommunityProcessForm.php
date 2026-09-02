<?php

namespace App\Filament\Resources\CommunityProcesses\Schemas;

use App\Models\CommunityProcess;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunityProcessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Convocatoria / proceso')
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('Tipo')
                        ->options(CommunityProcess::typeOptions())
                        ->default(CommunityProcess::TYPE_CALL)
                        ->required(),

                    Select::make('status')
                        ->label('Estado administrativo')
                        ->options(CommunityProcess::statusOptions())
                        ->default(CommunityProcess::STATUS_APPLICATIONS_OPEN)
                        ->required()
                        ->helperText('Las fechas y la votación pueden hacer que el estado público avance automáticamente.'),

                    TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(180)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Descripción / convocatoria')
                        ->rows(7)
                        ->maxLength(20000)
                        ->columnSpanFull(),
                ]),

            Section::make('Postulaciones')
                ->columns(3)
                ->schema([
                    Toggle::make('applications_enabled')
                        ->label('Permitir postulaciones')
                        ->default(true),

                    Toggle::make('allow_application_edit')
                        ->label('Permitir editar candidatura')
                        ->default(true),

                    Toggle::make('allow_application_withdraw')
                        ->label('Permitir retirar candidatura')
                        ->default(true),

                    DateTimePicker::make('applications_start_at')
                        ->label('Inicio de postulaciones')
                        ->seconds(false),

                    DateTimePicker::make('applications_end_at')
                        ->label('Cierre de postulaciones')
                        ->seconds(false)
                        ->after('applications_start_at'),

                    TextInput::make('max_winners')
                        ->label('Máximo de elegidos')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->placeholder('Sin definir'),

                    CheckboxList::make('eligible_statuses')
                        ->label('Estados que pueden postularse')
                        ->options([
                            'ACTIVO' => 'Miembro activo',
                            'RESERVA' => 'Reserva',
                            'RECLUTA' => 'Recluta',
                        ])
                        ->default(['ACTIVO'])
                        ->columns(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
