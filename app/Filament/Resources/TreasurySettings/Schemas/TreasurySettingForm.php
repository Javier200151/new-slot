<?php

namespace App\Filament\Resources\TreasurySettings\Schemas;

use App\Models\MemberProcedureSetting;
use App\Models\SqaGroup;
use App\Models\Status;
use App\Services\Treasury\TreasuryService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Throwable;

class TreasurySettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make([
                'default' => 1,
                'lg' => 2,
            ])->schema([
                Section::make('Conexión con Google Sheets')
                    ->description('La Tesorería usa la misma Service Account ya configurada para Squad ALPHA. El documento puede pertenecer a otra cuenta de Google siempre que esté compartido con esa Service Account.')
                    ->schema([
                        TextInput::make('treasury_spreadsheet_id')
                            ->label('Spreadsheet ID · Tesorería')
                            ->default(MemberProcedureSetting::DEFAULT_TREASURY_SPREADSHEET_ID)
                            ->maxLength(160)
                            ->required()
                            ->helperText('Puedes pegar el ID o la URL completa del Google Sheet. El valor por defecto corresponde al documento actual de Tesorería.'),

                        Actions::make([
                            Action::make('testTreasuryConnection')
                                ->label('Probar Tesorería')
                                ->icon('heroicon-o-signal')
                                ->color('info')
                                ->visible(fn (): bool => auth()->user()?->can('treasury-settings.sync') ?? false)
                                ->action(function (TreasuryService $treasury): void {
                                    try {
                                        $treasury->testConnection(MemberProcedureSetting::current());

                                        Notification::make()
                                            ->success()
                                            ->title('Tesorería conectada')
                                            ->body('Resumen, Movimientos, Jugadores, cuotas y Control mensual verificados. La lectura y escritura funcionan correctamente.')
                                            ->persistent()
                                            ->send();
                                    } catch (Throwable $exception) {
                                        report($exception);

                                        Notification::make()
                                            ->danger()
                                            ->title('Tesorería no está lista')
                                            ->body($exception->getMessage())
                                            ->persistent()
                                            ->send();
                                    }
                                }),

                            Action::make('openPublicTreasury')
                                ->label('Abrir /tesoreria')
                                ->icon('heroicon-o-arrow-top-right-on-square')
                                ->color('gray')
                                ->url(fn (): string => route('pages.show', 'tesoreria'))
                                ->openUrlInNewTab(),
                        ])
                            ->fullWidth(),
                    ]),

                Section::make('Privacidad · Mi saldo')
                    ->description('Define qué estados de usuario pueden consultar su información económica privada. La misma regla se aplica en Mi Perfil y dentro de /tesoreria.')
                    ->schema([
                        Select::make('treasury_private_status_ids')
                            ->label('Estados que pueden ver · Mi saldo')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (): array => Status::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->helperText('Por defecto solo ACTIVO. Los usuarios fuera de estos estados no reciben ni visualizan datos privados de Tesorería.'),

                        Placeholder::make('_privacy_note')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<strong>Vinculación:</strong> la asociación con la hoja de Tesorería se realiza únicamente mediante el nickname del usuario.'
                            )),
                    ]),

                Section::make('Avisos internos')
                    ->description('Configuración utilizada por los Procedimientos para avisar al equipo de Tesorería.')
                    ->schema([
                        Select::make('treasury_group_id')
                            ->label('Grupo SQA · Tesorería')
                            ->options(fn () => SqaGroup::query()
                                ->orderBy('display_order')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->helperText('Recibe avisos de señales, altas, reservas, reactivaciones, bajas y ceses.'),
                    ]),

                Section::make('Control mensual automático')
                    ->description('Cada día 15 se completan únicamente las celdas vacías del mes actual. Los valores escritos manualmente tienen siempre prioridad y nunca se sobrescriben.')
                    ->schema([
                        Placeholder::make('_monthly_rules')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<div><strong>Reglas:</strong> Miembro → X · Recluta → X · Reserva → R · Cesado → -</div>'
                                . '<div style="margin-top:.35rem;">Ejecución programada: día 15 de cada mes a las 00:15 (Europe/Madrid).</div>'
                            )),

                        Actions::make([
                            Action::make('simulateMonthlyControl')
                                ->label('Simular mes actual')
                                ->icon('heroicon-o-magnifying-glass')
                                ->color('gray')
                                ->visible(fn (): bool => auth()->user()?->can('treasury-settings.sync') ?? false)
                                ->action(function (TreasuryService $treasury): void {
                                    try {
                                        $date = CarbonImmutable::now('Europe/Madrid');
                                        $result = $treasury->syncMonthlyControl($date, true, MemberProcedureSetting::current());

                                        Notification::make()
                                            ->success()
                                            ->title('Simulación · ' . $result['month'] . ' ' . $result['year'])
                                            ->body(sprintf(
                                                '%d celdas se completarían; %d ya tienen un valor y %d filas no corresponden a Miembro, Recluta, Reserva o Cesado.',
                                                $result['planned'],
                                                $result['existing'],
                                                $result['ignored'],
                                            ))
                                            ->persistent()
                                            ->send();
                                    } catch (Throwable $exception) {
                                        report($exception);

                                        Notification::make()
                                            ->danger()
                                            ->title('No se pudo simular el Control mensual')
                                            ->body($exception->getMessage())
                                            ->persistent()
                                            ->send();
                                    }
                                }),
                        ])->fullWidth(),
                    ]),
            ]),
        ]);
    }
}
