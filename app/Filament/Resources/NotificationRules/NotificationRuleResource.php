<?php

namespace App\Filament\Resources\NotificationRules;

use App\Filament\Resources\NotificationRules\Pages\EditNotificationRule;
use App\Filament\Resources\NotificationRules\Pages\ListNotificationRules;
use App\Models\NotificationRule;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class NotificationRuleResource extends Resource
{
    protected static ?string $model = NotificationRule::class;
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 2;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notificaciones';
    protected static ?string $modelLabel = 'Tipo de notificación';
    protected static ?string $pluralModelLabel = 'Notificaciones';
    protected static ?string $recordTitleAttribute = 'key';

    public static function canViewAny(): bool
    {
        return (bool) (auth()->user()?->hasRole('admin') || auth()->user()?->can('notification-rules.view'));
    }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool
    {
        return (bool) (auth()->user()?->hasRole('admin') || auth()->user()?->can('notification-rules.update'));
    }
    public static function canDelete($record): bool { return false; }

    public static function ageFields(): array
    {
        return [
            TextInput::make('max_age_value')
                ->label('Antigüedad máxima')
                ->numeric()->integer()->minValue(1)->maxValue(3650)
                ->placeholder('Sin límite')
                ->helperText('Déjalo vacío para no limitar por antigüedad. La fecha es la del evento o contenido original.'),
            Select::make('max_age_unit')
                ->label('Unidad')
                ->options(['days' => 'Días', 'weeks' => 'Semanas', 'months' => 'Meses', 'years' => 'Años'])
                ->default('months')
                ->required(),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('enabled')->label('Enviar esta notificación'),
            TextInput::make('max_age_value')
                ->label('Antigüedad máxima')
                ->numeric()->integer()->minValue(1)->maxValue(3650)
                ->placeholder('Sin límite')
                ->helperText('Déjalo vacío para avisar sin límite temporal. Solo se aplica cuando hay una fecha de referencia.')
                ->disabled(fn (?NotificationRule $record): bool => $record && ! $record->allows_age),
            Select::make('max_age_unit')->label('Unidad')
                ->options(['days' => 'Días', 'weeks' => 'Semanas', 'months' => 'Meses', 'years' => 'Años'])
                ->required()->default('months')
                ->disabled(fn (?NotificationRule $record): bool => $record && ! $record->allows_age),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('label')->label('Notificación')->searchable(query: fn ($query, string $search) => $query->where('key', 'like', '%' . $search . '%'))->wrap(),
            TextColumn::make('category')->label('Área'),
            IconColumn::make('enabled')->label('Habilitada')->boolean(),
            TextColumn::make('age_display')->label('No notificar si es anterior a')->state(fn (NotificationRule $record): string =>
                ! $record->allows_age ? 'No aplica' : ($record->max_age_value === null ? 'Sin límite' : $record->max_age_value . ' ' . match ($record->max_age_unit) {
                    'days' => 'días', 'weeks' => 'semanas', 'years' => 'años', default => 'meses',
                })
            ),
        ])->defaultSort('key')
            ->recordActions([
                EditAction::make()->visible(fn (NotificationRule $record): bool => self::canEdit($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('enableSelection')->label('Activar seleccionadas')->icon('heroicon-o-bell')
                        ->visible(fn (): bool => self::canManage())
                        ->action(fn (Collection $records) => self::updateSelection($records, ['enabled' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('disableSelection')->label('Desactivar seleccionadas')->icon('heroicon-o-bell-slash')->color('danger')
                        ->visible(fn (): bool => self::canManage())->requiresConfirmation()
                        ->action(fn (Collection $records) => self::updateSelection($records, ['enabled' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('ageSelection')->label('Cambiar antigüedad seleccionadas')->icon('heroicon-o-clock')
                        ->visible(fn (): bool => self::canManage())->form(self::ageFields())
                        ->action(fn (Collection $records, array $data) => self::updateSelection($records, [
                            'max_age_value' => filled($data['max_age_value'] ?? null) ? (int) $data['max_age_value'] : null,
                            'max_age_unit' => $data['max_age_unit'],
                        ], onlyAge: true))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function canManage(): bool
    {
        return (bool) (auth()->user()?->hasRole('admin') || auth()->user()?->can('notification-rules.update'));
    }

    public static function updateSelection(Collection $records, array $values, bool $onlyAge = false): void
    {
        abort_unless(self::canManage(), 403);
        $count = 0;
        foreach ($records as $record) {
            if ($onlyAge && ! $record->allows_age) continue;
            $record->update($values);
            $count++;
        }
        Notification::make()->success()->title('Configuración actualizada')
            ->body($count . ' tipos de notificación modificados.')->send();
    }

    public static function getPages(): array
    {
        return ['index' => ListNotificationRules::route('/'), 'edit' => EditNotificationRule::route('/{record}/edit')];
    }
}
