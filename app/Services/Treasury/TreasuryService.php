<?php

namespace App\Services\Treasury;

use App\Models\MemberProcedure;
use App\Models\MemberProcedureSetting;
use App\Models\Status;
use App\Models\User;
use App\Services\MemberProcedures\GoogleSheetsService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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

        return $this->userStatusAllowed($user, $setting->treasury_private_status_ids);
    }

    public function canViewTreasuryPage(?User $user, ?MemberProcedureSetting $setting = null): bool
    {
        if (! $user) {
            return false;
        }

        $setting ??= MemberProcedureSetting::current();

        return $this->userStatusAllowed($user, $setting->treasury_page_status_ids);
    }

    private function userStatusAllowed(User $user, mixed $configuredStatusIds): bool
    {
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
     *     quarter:int,
     *     quarter_year:int,
     *     quarter_label:string,
     *     quarter_period:string,
     *     quarter_price:float,
     *     quarter_paid:bool,
     *     quarter_missing:float,
     *     quarter_available:float,
     *     display_balance:float,
     *     consumed_months:int,
     *     gifted_months:int,
     *     excluded_months:int,
     *     billable_months:int,
     *     pending_current_month:float,
     *     arrears_due:float,
     *     quarter_due:float,
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
                'quarter' => 0,
                'quarter_year' => (int) now('Europe/Madrid')->year,
                'quarter_label' => '',
                'quarter_period' => '',
                'quarter_price' => 9.0,
                'quarter_paid' => false,
                'quarter_missing' => 9.0,
                'quarter_available' => 0.0,
                'display_balance' => 0.0,
                'consumed_months' => 0,
                'gifted_months' => 0,
                'excluded_months' => 0,
                'billable_months' => 3,
                'pending_current_month' => 0.0,
                'arrears_due' => 0.0,
                'quarter_due' => 9.0,
                'fetched_at' => now()->toIso8601String(),
            ];
        }

        $spreadsheetId = $this->spreadsheetId($setting);
        $cacheKey = 'newslot:treasury:member:' . sha1($spreadsheetId . '|' . $user->id . '|' . $user->nick . '|' . now('Europe/Madrid')->format('Y-m'));

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($spreadsheetId, $user): array {
            $players = $this->read($spreadsheetId, self::PLAYERS_SHEET, 'A1:H');
            $dues = $this->read($spreadsheetId, self::DUES_SHEET, 'A1:Z');
            $movements = $this->read($spreadsheetId, self::MOVEMENTS_SHEET, 'A1:J');
            $control = $this->read($spreadsheetId, self::CONTROL_SHEET, 'A1:P');
            $member = $this->parser->member($players, $dues, $movements, (string) $user->nick);
            $quarter = $this->parser->quarterlyBalance(
                $control,
                (string) $user->nick,
                $member['remanent'] ?? null,
                now('Europe/Madrid'),
            );

            return [
                'configured' => true,
                ...$member,
                ...$quarter,
                'fetched_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Diagnóstico de solo lectura entre Jugadores y las cuentas de la web.
     * La relación se hace exclusivamente por nickname.
     *
     * @return array{
     *     total:int,ok:int,errors:int,
     *     issues:array<int,array{type:string,nick:string,row:?int,treasury_state:?string,web_state:?string,expected_state:?string,message:string}>
     * }
     */
    public function diagnosePlayers(?MemberProcedureSetting $setting = null): array
    {
        $setting ??= MemberProcedureSetting::current();
        if (! $this->isConfigured($setting)) {
            throw new RuntimeException('Tesorería no está configurada.');
        }

        $spreadsheetId = $this->spreadsheetId($setting);
        $table = $this->parser->playersTable($this->readFresh($spreadsheetId, self::PLAYERS_SHEET, 'A1:H1000'));
        if (! $table['valid']) {
            throw new RuntimeException((string) ($table['reason'] ?? 'No se pudo interpretar la pestaña Jugadores.'));
        }

        $treasuryByNick = [];
        foreach ($table['entries'] as $entry) {
            $key = $this->normalizeNick((string) $entry['nick']);
            if ($key !== '') {
                $treasuryByNick[$key][] = $entry;
            }
        }

        $webByNick = [];
        User::withTrashed()
            ->with(['status' => fn ($query) => $query->withTrashed()])
            ->get(['id', 'nick', 'status_id', 'deleted_at'])
            ->each(function (User $user) use (&$webByNick): void {
                $key = $this->normalizeNick((string) $user->nick);
                if ($key !== '') {
                    $webByNick[$key][] = $user;
                }
            });

        $issues = [];
        $ok = 0;

        foreach ($treasuryByNick as $key => $entries) {
            if (count($entries) > 1) {
                $rows = implode(', ', array_map(fn (array $entry): string => (string) $entry['row'], $entries));
                $issues[] = [
                    'type' => 'duplicate_treasury',
                    'nick' => (string) $entries[0]['nick'],
                    'row' => null,
                    'treasury_state' => null,
                    'web_state' => null,
                    'expected_state' => null,
                    'message' => 'Nickname duplicado en Jugadores (filas ' . $rows . '). Revísalo manualmente.',
                ];
                continue;
            }

            $entry = $entries[0];
            $matches = $webByNick[$key] ?? [];

            if ($matches === []) {
                $issues[] = [
                    'type' => 'missing_web',
                    'nick' => (string) $entry['nick'],
                    'row' => (int) $entry['row'],
                    'treasury_state' => (string) $entry['state'],
                    'web_state' => null,
                    'expected_state' => null,
                    'message' => 'No existe ninguna cuenta con este nickname en la web.',
                ];
                continue;
            }

            if (count($matches) > 1) {
                $issues[] = [
                    'type' => 'duplicate_web',
                    'nick' => (string) $entry['nick'],
                    'row' => (int) $entry['row'],
                    'treasury_state' => (string) $entry['state'],
                    'web_state' => null,
                    'expected_state' => null,
                    'message' => 'Hay más de una cuenta en la web que coincide con este nickname.',
                ];
                continue;
            }

            /** @var User $user */
            $user = $matches[0];
            $webState = strtoupper(trim((string) ($user->status?->name ?? '')));
            $expected = $user->trashed()
                ? 'Cesado'
                : $this->treasuryStateForWebState($webState);
            $actual = $this->normalizeTreasuryState((string) $entry['state']);

            if ($actual !== $this->normalizeTreasuryState($expected)) {
                $issues[] = [
                    'type' => 'state_mismatch',
                    'nick' => (string) $entry['nick'],
                    'row' => (int) $entry['row'],
                    'treasury_state' => (string) $entry['state'],
                    'web_state' => $webState !== '' ? $webState : 'SIN ESTADO',
                    'expected_state' => $expected,
                    'message' => 'Estado no sincronizado: en Tesorería figura «' . ((string) $entry['state'] ?: 'vacío') . '» y debería figurar «' . $expected . '».',
                ];
                continue;
            }

            $ok++;
        }

        return [
            'total' => count($table['entries']),
            'ok' => $ok,
            'errors' => count($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Sincroniza únicamente la ficha de Jugadores asociada al procedimiento.
     * Los errores dejan el paso en ERROR para que pueda revisarse el Excel y
     * reintentarse después desde el propio procedimiento.
     *
     * @return array<string, mixed>
     */
    public function syncProcedureUser(
        MemberProcedure $procedure,
        User $user,
        ?MemberProcedureSetting $setting = null,
    ): array {
        $setting ??= MemberProcedureSetting::current();
        if (! $this->isConfigured($setting)) {
            throw new RuntimeException('Tesorería no está configurada.');
        }

        $date = CarbonImmutable::parse($procedure->started_at ?? now())
            ->setTimezone('Europe/Madrid');

        return match ($procedure->type) {
            MemberProcedure::TYPE_RECRUITMENT_START => $this->registerRecruit($user, $date, $setting),
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => $this->changePlayerStateAndCalculate($user, 'Miembro', $date, $setting, true),
            MemberProcedure::TYPE_REACTIVATION => $this->changePlayerStateAndCalculate($user, 'Miembro', $date, $setting, false),
            MemberProcedure::TYPE_RESERVE => $this->changePlayerState($user, 'Reserva', $setting),
            MemberProcedure::TYPE_NOT_PROMOTED,
            MemberProcedure::TYPE_DEPARTURE,
            MemberProcedure::TYPE_DISMISSAL => $this->changePlayerState($user, 'Cesado', $setting),
            default => throw new RuntimeException('Este procedimiento no tiene sincronización de Tesorería.'),
        };
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

    /** @return array<string, mixed> */
    private function registerRecruit(User $user, CarbonInterface $date, MemberProcedureSetting $setting): array
    {
        $spreadsheetId = $this->spreadsheetId($setting);
        $table = $this->parser->playersTable($this->readFresh($spreadsheetId, self::PLAYERS_SHEET, 'A1:H1000'));
        if (! $table['valid']) {
            throw new RuntimeException((string) ($table['reason'] ?? 'No se pudo interpretar la pestaña Jugadores.'));
        }

        $matches = $this->playerMatches($table['entries'], (string) $user->nick);
        if (count($matches) > 1) {
            throw new RuntimeException('El nickname «' . $user->nick . '» aparece duplicado en Jugadores. Corrígelo manualmente y vuelve a intentar el paso.');
        }

        if (count($matches) === 1) {
            $existingState = $this->normalizeTreasuryState((string) $matches[0]['state']);
            if ($existingState !== 'RECLUTA') {
                throw new RuntimeException('El nickname «' . $user->nick . '» ya existe en Jugadores con estado «' . ((string) $matches[0]['state'] ?: 'vacío') . '». Revísalo manualmente antes de reintentar.');
            }

            $signal = $this->parser->signalPayment(
                $this->readFresh($spreadsheetId, self::MOVEMENTS_SHEET, 'A1:J'),
                (string) $user->nick,
            );

            return [
                'action' => 'recruit_already_registered',
                'row' => (int) $matches[0]['row'],
                'state' => 'Recluta',
                'signal_paid' => $signal['paid'],
                'signal_total' => $signal['total'],
            ];
        }

        $row = (int) ($table['next_row'] ?? 0);
        if ($row <= 0) {
            throw new RuntimeException('No se pudo localizar la siguiente fila libre de Jugadores.');
        }

        $preparedRow = $this->readFresh($spreadsheetId, self::PLAYERS_SHEET, 'A' . $row . ':H' . $row);
        if (trim((string) ($preparedRow[0][0] ?? '')) === '') {
            throw new RuntimeException('La siguiente fila libre de Jugadores no está preparada con ID/fórmulas. Amplía la tabla manualmente antes de reintentar.');
        }
        if (trim((string) ($preparedRow[0][1] ?? '')) !== '') {
            throw new RuntimeException('La fila libre calculada acaba de ser ocupada por otro usuario. Vuelve a intentar el paso.');
        }

        // Solo B/C/D (Nick, Estado, Fecha alta). No tocamos ID, saldo de arranque,
        // remanente, notas, selector ni las fórmulas preparadas por Tesorería.
        $this->googleSheets->writeSpreadsheetValues(
            $spreadsheetId,
            $this->a1(self::PLAYERS_SHEET, 'B' . $row . ':D' . $row),
            [[(string) $user->nick, 'Recluta', $date->format('d/m/Y')]],
            'USER_ENTERED',
        );

        $verification = $this->readFresh($spreadsheetId, self::PLAYERS_SHEET, 'B' . $row . ':D' . $row);
        if (
            $this->normalizeNick((string) ($verification[0][0] ?? '')) !== $this->normalizeNick((string) $user->nick)
            || $this->normalizeTreasuryState((string) ($verification[0][1] ?? '')) !== 'RECLUTA'
        ) {
            throw new RuntimeException('Google Sheets no confirmó correctamente el alta del recluta en Jugadores.');
        }

        $signal = $this->parser->signalPayment(
            $this->readFresh($spreadsheetId, self::MOVEMENTS_SHEET, 'A1:J'),
            (string) $user->nick,
        );

        $this->forgetMemberCache($user, $spreadsheetId);

        return [
            'action' => 'recruit_registered',
            'row' => $row,
            'state' => 'Recluta',
            'signal_paid' => $signal['paid'],
            'signal_total' => $signal['total'],
            'signal_message' => $signal['paid']
                ? 'Se ha localizado una señal registrada de al menos 6 €.'
                : 'Todavía no consta una señal pagada de 6 € en Movimientos.',
        ];
    }

    /** @return array<string, mixed> */
    private function changePlayerStateAndCalculate(
        User $user,
        string $state,
        CarbonInterface $date,
        MemberProcedureSetting $setting,
        bool $includeSignal,
    ): array {
        $result = $this->changePlayerState($user, $state, $setting);
        $spreadsheetId = $this->spreadsheetId($setting);

        $players = $this->readFresh($spreadsheetId, self::PLAYERS_SHEET, 'A1:H1000');
        $dues = $this->readFresh($spreadsheetId, self::DUES_SHEET, 'A1:Z');
        $movements = $this->readFresh($spreadsheetId, self::MOVEMENTS_SHEET, 'A1:J');
        $control = $this->readFresh($spreadsheetId, self::CONTROL_SHEET, 'A1:P');
        $member = $this->parser->member($players, $dues, $movements, (string) $user->nick);

        if (! $member['found']) {
            throw new RuntimeException('La ficha de Tesorería desapareció después de actualizar el estado.');
        }

        $quarter = $this->parser->quarterlyBalance(
            $control,
            (string) $user->nick,
            $member['remanent'] ?? null,
            $date,
        );

        $signal = $includeSignal
            ? $this->parser->signalPayment($movements, (string) $user->nick)
            : null;

        return [
            ...$result,
            'amount_due' => round((float) $quarter['quarter_missing'], 2),
            'quarter_label' => (string) $quarter['quarter_label'],
            'quarter_period' => (string) $quarter['quarter_period'],
            'quarter_due' => round((float) $quarter['quarter_due'], 2),
            'previous_or_current_due' => round((float) $quarter['arrears_due'], 2),
            'available_balance' => round((float) $quarter['quarter_available'], 2),
            'signal_paid' => $signal['paid'] ?? null,
            'signal_total' => $signal['total'] ?? null,
            'explanation' => $this->paymentExplanation($quarter, $signal),
        ];
    }

    /** @return array<string, mixed> */
    private function changePlayerState(User $user, string $state, MemberProcedureSetting $setting): array
    {
        $spreadsheetId = $this->spreadsheetId($setting);
        $table = $this->parser->playersTable($this->readFresh($spreadsheetId, self::PLAYERS_SHEET, 'A1:H1000'));
        if (! $table['valid']) {
            throw new RuntimeException((string) ($table['reason'] ?? 'No se pudo interpretar la pestaña Jugadores.'));
        }

        $matches = $this->playerMatches($table['entries'], (string) $user->nick);
        if ($matches === []) {
            throw new RuntimeException('No existe «' . $user->nick . '» en Jugadores. Corrígelo manualmente y vuelve a intentar el paso.');
        }
        if (count($matches) > 1) {
            throw new RuntimeException('El nickname «' . $user->nick . '» aparece duplicado en Jugadores. Corrígelo manualmente y vuelve a intentar el paso.');
        }

        $row = (int) $matches[0]['row'];
        $column = (string) ($table['state_column'] ?? 'C');
        if ($this->normalizeTreasuryState((string) $matches[0]['state']) !== $this->normalizeTreasuryState($state)) {
            $this->googleSheets->writeSpreadsheetValues(
                $spreadsheetId,
                $this->a1(self::PLAYERS_SHEET, $column . $row . ':' . $column . $row),
                [[$state]],
                'RAW',
            );
        }

        $verification = $this->readFresh($spreadsheetId, self::PLAYERS_SHEET, $column . $row . ':' . $column . $row);
        if ($this->normalizeTreasuryState((string) ($verification[0][0] ?? '')) !== $this->normalizeTreasuryState($state)) {
            throw new RuntimeException('Google Sheets no confirmó el estado «' . $state . '» para «' . $user->nick . '».');
        }

        $this->forgetMemberCache($user, $spreadsheetId);

        return [
            'action' => 'player_state_synced',
            'row' => $row,
            'state' => $state,
        ];
    }

    /** @param array<int,array{row:int,id:?int,nick:string,state:string,date:mixed}> $entries */
    private function playerMatches(array $entries, string $nick): array
    {
        $normalized = $this->normalizeNick($nick);

        return array_values(array_filter(
            $entries,
            fn (array $entry): bool => $this->normalizeNick((string) $entry['nick']) === $normalized,
        ));
    }

    private function treasuryStateForWebState(string $webState): string
    {
        return match (strtoupper(trim($webState))) {
            'ACTIVO' => 'Miembro',
            'RECLUTA' => 'Recluta',
            'RESERVA' => 'Reserva',
            default => 'Cesado',
        };
    }

    private function normalizeTreasuryState(string $state): string
    {
        return strtoupper(Str::ascii(trim($state)));
    }

    private function normalizeNick(string $nick): string
    {
        return strtoupper(Str::ascii(trim($nick)));
    }

    /** @param array<string,mixed> $quarter @param array<string,mixed>|null $signal */
    private function paymentExplanation(array $quarter, ?array $signal): string
    {
        $due = (float) ($quarter['quarter_missing'] ?? 0);
        if ($due <= 0.00001) {
            return 'No queda ningún importe pendiente para ' . (string) ($quarter['quarter_label'] ?? 'el trimestre') . '.';
        }

        $parts = [];
        $arrears = (float) ($quarter['arrears_due'] ?? 0);
        $quarterDue = (float) ($quarter['quarter_due'] ?? 0);
        $giftedMonths = (int) ($quarter['gifted_months'] ?? 0);

        if ($signal !== null) {
            $parts[] = ($signal['paid'] ?? false)
                ? 'La señal de 6 € está registrada.'
                : 'No consta todavía una señal pagada de 6 €.';
        }

        if ($giftedMonths > 0) {
            $parts[] = $giftedMonths === 1
                ? 'Hay 1 mes regalado (G), por lo que ese mes no se cobra.'
                : 'Hay ' . $giftedMonths . ' meses regalados (G), por lo que esos meses no se cobran.';
        }

        if ($arrears > 0.00001) {
            $parts[] = 'Hay ' . number_format($arrears, 2, ',', '.') . ' € pendientes de meses anteriores o del mes en curso.';
        }
        if ($quarterDue > 0.00001) {
            $parts[] = 'Para ' . (string) ($quarter['quarter_label'] ?? 'el trimestre') . ' faltan ' . number_format($quarterDue, 2, ',', '.') . ' €.';
        }

        $parts[] = 'Total a pagar: ' . number_format($due, 2, ',', '.') . ' €.';

        return implode(' ', $parts);
    }

    private function forgetMemberCache(User $user, string $spreadsheetId): void
    {
        Cache::forget('newslot:treasury:member:' . sha1($spreadsheetId . '|' . $user->id . '|' . $user->nick . '|' . now('Europe/Madrid')->format('Y-m')));
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
