<?php

namespace App\Filament\Resources\StatisticsSettings;

use App\Filament\Resources\StatisticsSettings\Pages\EditStatisticsSetting;
use App\Filament\Resources\StatisticsSettings\Pages\ListStatisticsSettings;
use App\Models\StatisticsSetting;
use App\Models\Status;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class StatisticsSettingResource extends Resource
{
    protected static ?string $model = StatisticsSetting::class;
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 4;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;
    protected static ?string $navigationLabel = 'Estadísticas';
    protected static ?string $modelLabel = 'Configuración de estadísticas';
    protected static ?string $pluralModelLabel = 'Estadísticas';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Centro de estadísticas')
                ->schema([
                    Toggle::make('is_enabled')
                        ->label('Activar estadísticas')
                        ->default(true),
                    Select::make('allowed_statuses')
                        ->label('Estados permitidos')
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => Status::query()->orderBy('name')->pluck('name', 'name')->all())
                        ->default(['ACTIVO', 'RESERVA']),
                    Toggle::make('allow_admins')
                        ->label('Permitir a administradores')
                        ->default(true),
                    Toggle::make('show_user_profiles')
                        ->label('Estadísticas en los perfiles')
                        ->default(true),
                    Select::make('profile_metrics')
                        ->label('Indicadores del perfil')
                        ->multiple()
                        ->options(StatisticsSetting::PROFILE_METRICS)
                        ->default(['favorite_role'])
                        ->helperText('El rol más jugado aparece siempre.'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('allowed_statuses')->label('Estados permitidos')
                ->state(fn (StatisticsSetting $record): string => implode(', ', $record->allowed_statuses ?? []))
                ->wrap(),
            TextColumn::make('is_enabled')->label('Página activa')->badge()
                ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No'),
            TextColumn::make('updated_at')->label('Actualizado')->since(),
        ])->recordActions([EditAction::make()])->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStatisticsSettings::route('/'),
            'edit' => EditStatisticsSetting::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }
}
