<?php

namespace App\Filament\Resources\MemberProcedureSettings\Schemas;

use App\Models\Metopa;
use App\Models\SqaGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberProcedureSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Configuración interna')
                ->description('Elementos de NewSlot utilizados automáticamente por los procedimientos.')
                ->schema([
                    Select::make('alpha_metopa_id')
                        ->label('Metopa de miembro ALPHA')
                        ->options(fn () => Metopa::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()
                        ->helperText('Se entrega automáticamente al completar el reclutamiento.'),
                    Select::make('treasury_group_id')
                        ->label('Grupo SQA · Tesorería')
                        ->options(fn () => SqaGroup::query()->orderBy('display_order')->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()
                        ->helperText('Los avisos de señales, altas, reservas, reactivaciones, bajas y ceses se dirigirán a los miembros de este grupo.'),
                    Select::make('tutors_group_id')
                        ->label('Grupo SQA · Tutores')
                        ->options(fn () => SqaGroup::query()->orderBy('display_order')->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()
                        ->helperText('El aviso de un nuevo recluta se envía al usuario marcado como coordinador dentro de este grupo.'),
                ])->columns(3),

            Section::make('Google Sheets · registro de miembros')
                ->description('Estos identificadores no son credenciales. En esta primera versión el paso sigue siendo manual y queda trazado en el procedimiento.')
                ->schema([
                    TextInput::make('google_spreadsheet_id')
                        ->label('Spreadsheet ID')
                        ->maxLength(160)
                        ->helperText('ID de la hoja de cálculo de Google que contiene el registro de miembros.'),
                    TextInput::make('google_general_sheet_gid')
                        ->label('GID de la pestaña General')
                        ->maxLength(32)
                        ->helperText('La pestaña General del documento que contiene ID Web, INGRESO y FECHA CALAVERA.'),
                ])->columns(2),

            Section::make('ArmaSquads')
                ->description('El Squad ID se configura aquí. La API key permanece exclusivamente en el entorno del servidor.')
                ->schema([
                    TextInput::make('armasquads_squad_id')
                        ->label('Squad ID')
                        ->maxLength(80)
                        ->helperText('Identificador numérico del Squad en ArmaSquads. Con ARMASQUADS_ENABLED=true y la API key configurada, el alta/baja se automatiza.'),
                ]),
        ]);
    }
}
