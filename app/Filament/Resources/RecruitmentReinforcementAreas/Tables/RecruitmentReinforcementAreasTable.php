<?php
namespace App\Filament\Resources\RecruitmentReinforcementAreas\Tables;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
class RecruitmentReinforcementAreasTable { public static function configure(Table $table): Table { return $table->columns([
    TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
    IconColumn::make('active')->label('Activa')->boolean(),
    TextColumn::make('recruitment_periods_count')->counts('recruitmentPeriods')->label('Periodos')->badge(),
])->recordActions([EditAction::make()]); } }
