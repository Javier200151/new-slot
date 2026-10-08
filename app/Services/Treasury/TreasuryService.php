<?php

namespace App\Services\Treasury;

use App\Models\MemberProcedureSetting;
use App\Models\Status;
use App\Models\User;
use App\Services\MemberProcedures\GoogleSheetsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class TreasuryService
{
    private const SUMMARY_SHEET = 'Resumen';
    private const MOVEMENTS_SHEET = 'Movimientos';
    private const PLAYERS_SHEET = 'Jugadores';
    private const DUES_SHEET = 'Vista pública cuotas';
    private const CONTROL_SHEET = 'Control mensual';

    public function __construct(
        private readonly GoogleSheetsService $googleSheets,
        private readonly TreasurySheetParser $parser,
    ) {
    }

    public function isConfigured(?MemberProcedureSetting $setting = null): bool
    {
        $setting ??= MemberProcedureSetting::current();

        return $this->googleSheets->hasCredentials()
            && filled($this->spreadsheetId($setting));
    }

    public function canViewPrivateBalance(User $user, ?MemberProcedureSetting $setting = null): bool
    {
        $setting ??= MemberProcedureSetting::current();
        $configuredStatusIds = $setting->treasury_private_status_ids;

        if ($configuredStatusIds === null) {
            $activeStatusId = Status::query()
                ->whereRaw('UPPER(name) = ?', ['ACTIVO'])
                ->value('id');

            $configuredStatusIds = $activeStatusId ? [(int) $activeStatusId] : [];
        }

        $allowedStatusIds = array_values(array_filter(
            array_map('intval', is_array($configuredStatusIds) ? $configuredStatusIds : []),
            static fn (int $id): bool => $id > 0,
        ));

        return $user->status_id !== null
            && in_array((int) $user->status_id, $allowedStatusIds, true);
    }

    /**
     * @return array{
     *     configured:bool,
     *     bank:?float,
     *     paypal:?float,
     *     total:?float,
     *     expenses:array<int,array{date:string,date_iso:string,category:string,account:string,amount:float,concept:string}>,
     *     from:string,
     *     fetched_at:string
     * }
     */
    public function publicOverview(?MemberProcedureSetting $setting = null): array
    {
        $setting ??= MemberProcedureSetting::current();
        if (! $this->isConfigured($setting)) {
            return [
                'configured' => false,
                'bank' => null,
                'paypal' => null,
                'total' => null,
                'expenses' => [],
                'from' => now()->subYear()->format('d/m/Y'),
                'fetched_at' => now()->toIso8601String(),
            ];
        }

        $spreadsheetId = $this->spreadsheetId($setting);
        $cacheKey = 'newslot:treasury:public:' . sha1($spreadsheetId);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($spreadsheetId): array {
            $summaryRows = $this->read($spreadsheetId, self::SUMMARY_SHEET, 'A1:H40');
            $movementRows = $this->read($spreadsheetId, self::MOVEMENTS_SHEET, 'A1:J');
            $summary = $this->parser->summary($summaryRows);
            $from = now()->subYear()->startOfDay();

            return [
                'configured' => true,
                ...$summary,
                'expenses' => $this->parser->expenses($movementRows, $from),
                'from' => $from->format('d/m/Y'),
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * @return array{
     *     configured:bool,
     *     found:bool,
     *     player_id:?int,
     *     remanent:?float,
     *     next_quarter:?string,
     *     last_payment:?array,
     *     state:?string,
     *     fetched_at:string
     * }
     */
    public function memberOverview(User $user, ?MemberProcedureSetting $setting = null): array
    {
        $setting ??= MemberProcedureSetting::current();
        if (! $this->isConfigured($setting)) {
            return [
                'configured' => false,
                'found' => false,
                'player_id' => null,
                'remanent' => null,
                'next_quarter' => null,
                'last_payment' => null,
                'state' => null,
                'fetched_at' => now()->toIso8601String(),
            ];
        }

        $spreadsheetId = $this->spreadsheetId($setting);
        $cacheKey = 'newslot:treasury:member:' . sha1($spreadsheetId . '|' . $user->id . '|' . $user->nick);

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($spreadsheetId, $user): array {
            $players = $this->read($spreadsheetId, self::PLAYERS_SHEET, 'A1:H');
            $dues = $this->read($spreadsheetId, self::DUES_SHEET, 'A1:Z');
            $movements = $this->read($spreadsheetId, self::MOVEMENTS_SHEET, 'A1:J');

            return [
                'configured' => true,
                ...$this->parser->member($players, $dues, $movements, (string) $user->nick),
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /** @return array<string, mixed> */
    public function testConnection(?MemberProcedureSetting $setting = null): array
    {
        $setting ??= MemberProcedureSetting::current();
        $spreadsheetId = $this->spreadsheetId($setting);
        if ($spreadsheetId === '') {
            throw new RuntimeException('Configura primero el Spreadsheet ID de Tesorería.');
        }

        if (! $this->googleSheets->hasCredentials()) {
            throw new RuntimeException('La Service Account de Google Sheets no está disponible en el servidor.');
        }

        $summary = $this->parser->summary($this->readFresh($spreadsheetId, self::SUMMARY_SHEET, 'A1:H40'));
        $movements = $this->readFresh($spreadsheetId, self::MOVEMENTS_SHEET, 'A1:J20');
        $players = $this->readFresh($spreadsheetId, self::PLAYERS_SHEET, 'A1:H20');
        $dues = $this->readFresh($spreadsheetId, self::DUES_SHEET, 'A1:Z20');
        $control = $this->readFresh($spreadsheetId, self::CONTROL_SHEET, 'A1:P20');

        if ($summary['bank'] === null || $summary['paypal'] === null) {
            throw new RuntimeException('La pestaña Resumen no contiene los saldos esperados de Banco y PayPal.');
        }

        if ($movements === [] || $players === [] || $dues === [] || $control === []) {
            throw new RuntimeException('No se pudieron leer todas las pestañas necesarias de Tesorería.');
        }

        // Control mensual requiere escritura. Reescribimos el encabezado del mes
        // actual con exactamente el mismo texto para verificar permisos sin tocar
        // ninguna celda de usuario ni alterar la lógica de pagos.
        $controlInfo = $this->parser->monthlyControl($control, now('Europe/Madrid'));
        if (! $controlInfo['valid']) {
            throw new RuntimeException((string) ($controlInfo['reason'] ?? 'No se pudo interpretar Control mensual.'));
        }

        $headerCell = $controlInfo['month_column'] . $controlInfo['header_row'];
        $this->googleSheets->writeSpreadsheetValues(
            $spreadsheetId,
            $this->a1(self::CONTROL_SHEET, $headerCell . ':' . $headerCell),
            [[$controlInfo['month']]],
            'RAW',
        );

        return [
            'spreadsheet_id' => $spreadsheetId,
            'summary' => true,
            'movements' => true,
            'players' => true,
            'dues' => true,
            'control_monthly' => true,
            'write' => true,
        ];
    }

    /**
     * Rellena únicamente celdas vacías del mes actual en Control mensual.
     * Los valores ya escritos se consideran manuales y tienen prioridad.
     *
     * @return array{month:string,year:int,planned:int,written:int,existing:int,ignored:int,dry_run:bool}
     */
    public function syncMonthlyControl(
        CarbonInterface $date,
        bool $dryRun = false,
        ?MemberProcedureSetting $setting = null,
    ): array {
        $setting ??= MemberProcedureSetting::current();
        if (! $this->isConfigured($setting)) {
            throw new RuntimeException('Tesorería no está configurada.');
        }

        $spreadsheetId = $this->spreadsheetId($setting);
        $rows = $this->readFresh($spreadsheetId, self::CONTROL_SHEET, 'A1:P');
        $control = $this->parser->monthlyControl($rows, $date);

        if (! $control['valid']) {
            throw new RuntimeException((string) ($control['reason'] ?? 'No se pudo interpretar Control mensual.'));
        }

        $updates = $control['updates'];
        if (! $dryRun && $updates !== []) {
            $data = array_map(fn (array $update): array => [
                'range' => $this->a1(self::CONTROL_SHEET, $update['range']),
                'values' => [[$update['value']]],
            ], $updates);

            $this->googleSheets->batchWriteSpreadsheetValues($spreadsheetId, $data);
        }

        return [
            'month' => $control['month'],
            'year' => $control['year'],
            'planned' => count($updates),
            'written' => $dryRun ? 0 : count($updates),
            'existing' => $control['existing'],
            'ignored' => $control['ignored'],
            'dry_run' => $dryRun,
        ];
    }

    /** @return array<int, array<int, mixed>> */
    private function read(string $spreadsheetId, string $sheet, string $cells): array
    {
        $cacheKey = 'newslot:treasury:range:' . sha1($spreadsheetId . '|' . $sheet . '|' . $cells);

        return Cache::remember($cacheKey, now()->addMinutes(3), fn (): array => $this->readFresh($spreadsheetId, $sheet, $cells));
    }

    /** @return array<int, array<int, mixed>> */
    private function readFresh(string $spreadsheetId, string $sheet, string $cells): array
    {
        return $this->googleSheets->readSpreadsheetValues(
            $spreadsheetId,
            $this->a1($sheet, $cells),
            'UNFORMATTED_VALUE',
        );
    }

    private function spreadsheetId(MemberProcedureSetting $setting): string
    {
        $value = trim((string) $setting->treasury_spreadsheet_id);
        if (preg_match('~/spreadsheets/d/([^/]+)~', $value, $matches)) {
            return (string) $matches[1];
        }

        return $value;
    }

    private function a1(string $sheet, string $cells): string
    {
        return "'" . str_replace("'", "''", $sheet) . "'!{$cells}";
    }
}
