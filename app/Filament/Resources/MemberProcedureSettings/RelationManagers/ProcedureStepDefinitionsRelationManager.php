<?php

namespace App\Filament\Resources\MemberProcedureSettings\RelationManagers;

use App\Models\MemberProcedure;
use App\Models\MemberProcedureStep;
use App\Models\MemberProcedureStepDefinition;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

abstract class ProcedureStepDefinitionsRelationManager extends RelationManager
{
    protected static string $relationship = 'stepDefinitions';

    /**
     * El tipo de procedimiento se fija en cada pestaña concreta.
     */
    protected static string $procedureType;

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('procedure_type', static::$procedureType))
            ->columns([
                TextColumn::make('position')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Nombre del paso')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        MemberProcedureStep::KIND_AUTOMATIC => 'Automático',
                        MemberProcedureStep::KIND_MANUAL => 'Manual',
                        MemberProcedureStep::KIND_WAITING => 'Espera automática',
                        default => $state,
                    }),
                TextColumn::make('instructions')
                    ->label('Instrucciones')
                    ->limit(140)
                    ->wrap()
                    ->placeholder('Sin instrucciones'),
                IconColumn::make('is_enabled')
                    ->label('Activo')
                    ->boolean()
                    ->tooltip(fn (MemberProcedureStepDefinition $record): string => $record->kind === MemberProcedureStep::KIND_MANUAL
                        ? 'Los pasos manuales desactivados no se añaden a procedimientos nuevos.'
                        : 'Los pasos automáticos del sistema siempre permanecen activos.'),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options([
                        MemberProcedureStep::KIND_AUTOMATIC => 'Automático',
                        MemberProcedureStep::KIND_MANUAL => 'Manual',
                        MemberProcedureStep::KIND_WAITING => 'Espera automática',
                    ]),
            ])
            ->defaultSort('position')
            ->headerActions([
                CreateAction::make('createManualStep')
                    ->label('Crear paso manual')
                    ->icon('heroicon-o-plus')
                    ->form([
                        TextInput::make('label')
                            ->label('Nombre del paso')
                            ->required()
                            ->maxLength(180),
                        Textarea::make('instructions')
                            ->label('Instrucciones')
                            ->rows(5)
                            ->maxLength(5000)
                            ->helperText('Describe exactamente qué debe hacer el administrador para poder marcar este paso como completado.'),
                        TextInput::make('position')
                            ->label('Orden')
                            ->numeric()
                            ->minValue(1)
                            ->default(fn (): int => ((int) MemberProcedureStepDefinition::query()
                                ->where('procedure_type', static::$procedureType)
                                ->max('position')) + 1)
                            ->required(),
                        Toggle::make('is_enabled')
                            ->label('Incluir en procedimientos nuevos')
                            ->default(true),
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        $data['procedure_type'] = static::$procedureType;
                        $data['step_key'] = 'custom_manual_' . Str::lower(Str::random(16));
                        $data['kind'] = MemberProcedureStep::KIND_MANUAL;
                        $data['required'] = true;
                        $data['is_system'] = false;
                        $data['depends_on'] = [];

                        return $data;
                    })
                    ->successNotificationTitle('Paso manual creado'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->form(fn (MemberProcedureStepDefinition $record): array => [
                        TextInput::make('label')
                            ->label('Nombre del paso')
                            ->required()
                            ->maxLength(180),
                        Textarea::make('instructions')
                            ->label('Instrucciones')
                            ->rows(7)
                            ->maxLength(5000)
                            ->helperText('Los cambios de texto también se aplican a pasos equivalentes de procedimientos que todavía estén abiertos.'),
                        TextInput::make('position')
                            ->label('Orden')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Toggle::make('is_enabled')
                            ->label('Incluir en procedimientos nuevos')
                            ->default(true)
                            ->disabled(fn (): bool => $record->kind !== MemberProcedureStep::KIND_MANUAL)
                            ->helperText($record->kind === MemberProcedureStep::KIND_MANUAL
                                ? 'Desactívalo para ocultarlo de los próximos procedimientos sin borrarlo.'
                                : 'Los pasos automáticos y de espera forman parte de la lógica del sistema y no se pueden desactivar.'),
                    ])
                    ->mutateDataUsing(function (array $data, MemberProcedureStepDefinition $record): array {
                        if ($record->kind !== MemberProcedureStep::KIND_MANUAL) {
                            $data['is_enabled'] = true;
                        }

                        return $data;
                    })
                    ->after(function (MemberProcedureStepDefinition $record): void {
                        $this->syncOpenProcedureTexts($record);
                    }),
                DeleteAction::make()
                    ->label('Borrar')
                    ->visible(fn (MemberProcedureStepDefinition $record): bool => $record->canBeDeleted())
                    ->requiresConfirmation()
                    ->modalDescription('El paso dejará de aparecer en procedimientos nuevos. Los procedimientos ya iniciados conservan su copia histórica.'),
            ])
            ->paginated(false);
    }

    private function syncOpenProcedureTexts(MemberProcedureStepDefinition $definition): void
    {
        MemberProcedureStep::query()
            ->where('step_key', $definition->step_key)
            ->whereHas('procedure', fn ($query) => $query
                ->where('type', $definition->procedure_type)
                ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR]))
            ->get()
            ->each(function (MemberProcedureStep $step) use ($definition): void {
                $meta = $step->meta ?? [];
                $meta['instructions'] = $definition->instructions;

                $step->forceFill([
                    'label' => $definition->label,
                    'meta' => $meta,
                ])->saveQuietly();
            });

        Notification::make()
            ->success()
            ->title('Paso actualizado')
            ->body('Nombre e instrucciones actualizados también en los procedimientos que siguen abiertos.')
            ->send();
    }
}
