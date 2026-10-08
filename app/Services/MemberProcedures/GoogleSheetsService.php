<?php

namespace App\Services\MemberProcedures;

use App\Models\ContactSubmission;
use App\Models\MemberProcedureSetting;
use App\Models\RecruitmentPeriod;
use App\Models\User;
use App\Services\VeterancyService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleSheetsService
{
    private const SHEETS_BASE_URL = 'https://sheets.googleapis.com/v4';
    private const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    /** @var array<int, string> */
    private const EXPECTED_HEADERS = [
        2 => 'ID WEB',
        3 => 'NOMBRE',
        4 => 'APELLIDOS',
        5 => 'TELEFONO',
        6 => 'EMAIL',
        7 => 'FECHA DE NACIMIENTO',
        8 => 'RESIDENCIA',
        9 => 'TUTOR',
        10 => 'INGRESO',
        11 => 'DIAS EN RESERVA',
        12 => 'ESTADO',
        13 => 'FECHA CALAVERA',
        14 => 'PROMOCION',
        15 => 'BRONCE',
        16 => 'PLATA',
        17 => 'ORO',
    ];

    public function __construct(private readonly VeterancyService $veterancies)
    {
    }

    public function isConfigured(MemberProcedureSetting $setting): bool
    {
        return $this->hasCredentials()
            && filled($this->spreadsheetId($setting));
    }

    public function hasCredentials(): bool
    {
        if (! (bool) config('newslot.procedures.google_sheets.enabled')) {
            return false;
        }

        $path = $this->credentialsPath();

        return $path !== '' && is_file($path) && is_readable($path);
    }

    /**
     * Lectura genérica para otras hojas privadas que usan la misma Service Account.
     *
     * @return array<int, array<int, mixed>>
     */
    public function readSpreadsheetValues(
        string $spreadsheetId,
        string $range,
        string $renderOption = 'UNFORMATTED_VALUE',
    ): array {
        $spreadsheetId = trim($spreadsheetId);
        if ($spreadsheetId === '') {
            throw new RuntimeException('Falta el Spreadsheet ID.');
        }

        $this->assertCredentialsAvailable();

        return $this->getValues($spreadsheetId, $range, $renderOption);
    }

    /**
     * Escritura genérica para hojas privadas que usan la misma Service Account.
     *
     * @param  array<int, array<int, mixed>>  $values
     */
    public function writeSpreadsheetValues(
        string $spreadsheetId,
        string $range,
        array $values,
        string $inputOption = 'RAW',
    ): void {
        $spreadsheetId = trim($spreadsheetId);
        if ($spreadsheetId === '') {
            throw new RuntimeException('Falta el Spreadsheet ID.');
        }

        $this->assertCredentialsAvailable();
        $this->updateValues($spreadsheetId, $range, $values, $inputOption);
    }

    /**
     * @param  list<array{range:string,values:array}>  $data
     */
    public function batchWriteSpreadsheetValues(string $spreadsheetId, array $data): void
    {
        $spreadsheetId = trim($spreadsheetId);
        if ($spreadsheetId === '') {
            throw new RuntimeException('Falta el Spreadsheet ID.');
        }

        if ($data === []) {
            return;
        }

        $this->assertCredentialsAvailable();
        $this->batchUpdateValues($spreadsheetId, $data);
    }

    /** @return array<string, mixed> */
    public function testConnection(MemberProcedureSetting $setting): array
    {
        $this->assertConfigured($setting);

        $spreadsheetId = $this->spreadsheetId($setting);
        $sheetTitle = $this->sheetTitle($setting);
        $this->assertExpectedHeaders($spreadsheetId, $sheetTitle);

        // B1 contiene "ID Web". Reescribimos exactamente el mismo valor para
        // comprobar también permiso de escritura sin alterar datos ni formato.
        $header = $this->getValues($spreadsheetId, $sheetTitle . '!B1:B1', 'FORMATTED_VALUE');
        $value = (string) (($header[0][0] ?? ''));
        if ($this->normalizeHeader($value) !== 'ID WEB') {
            throw new RuntimeException('La pestaña seleccionada no contiene "ID Web" en B1.');
        }

        $this->updateValues($spreadsheetId, $sheetTitle . '!B1:B1', [[$value]], 'RAW');
        $verification = $this->getValues($spreadsheetId, $sheetTitle . '!B1:B1', 'FORMATTED_VALUE');
        if ((string) (($verification[0][0] ?? '')) !== $value) {
            throw new RuntimeException('Google Sheets aceptó la escritura, pero la verificación posterior no coincide.');
        }

        return [
            'spreadsheet_id' => $spreadsheetId,
            'sheet' => $sheetTitle,
            'read' => true,
            'write' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function transferRecruitment(User $user, ContactSubmission $submission, MemberProcedureSetting $setting): array
    {
        $this->assertConfigured($setting);
        $this->assertPersonalDataAvailable($submission);

        $spreadsheetId = $this->spreadsheetId($setting);
        $sheetTitle = $this->sheetTitle($setting);
        $this->assertExpectedHeaders($spreadsheetId, $sheetTitle);

        $values = $this->rowValues($user, $submission);
        $rowNumber = $this->findRowByWebId($spreadsheetId, $sheetTitle, (int) $user->id);
        $action = 'updated';

        if ($rowNumber === null) {
            $rowNumber = $this->appendRow($spreadsheetId, $sheetTitle, $values);
            $action = 'created';
        } else {
            $this->writeTransferRow($spreadsheetId, $sheetTitle, $rowNumber, $values);
        }

        $this->verifyTransfer($spreadsheetId, $sheetTitle, $rowNumber, $user, $submission);

        return [
            'action' => $action,
            'row' => $rowNumber,
            'web_id' => (int) $user->id,
            'verified' => true,
            'sheet' => $sheetTitle,
        ];
    }

    /** @return array<string, mixed> */
    public function syncOperational(User $user, MemberProcedureSetting $setting): array
    {
        $this->assertConfigured($setting);

        $spreadsheetId = $this->spreadsheetId($setting);
        $sheetTitle = $this->sheetTitle($setting);
        $this->assertExpectedHeaders($spreadsheetId, $sheetTitle);

        $rowNumber = $this->findRowByWebId($spreadsheetId, $sheetTitle, (int) $user->id);
        if ($rowNumber === null) {
            throw new RuntimeException('No existe una fila en Google Sheets con ID Web ' . $user->id . '. Completa primero el registro del miembro.');
        }

        $user->refresh()->loadMissing(['status', 'promo']);
        $operational = $this->operationalValues($user);

        $data = [
            ['range' => $this->a1($sheetTitle, "A{$rowNumber}:B{$rowNumber}"), 'values' => [[$operational['A'], $operational['B']]]],
            ['range' => $this->a1($sheetTitle, "I{$rowNumber}:N{$rowNumber}"), 'values' => [[
                $operational['I'], $operational['J'], $operational['K'],
                $operational['L'], $operational['M'], $operational['N'],
            ]]],
        ];

        $this->batchUpdateValues($spreadsheetId, $data);
        $this->writeVeterancyDatesWhenSafe($spreadsheetId, $sheetTitle, $rowNumber, $operational);

        $verification = $this->getValues($spreadsheetId, $sheetTitle . "!A{$rowNumber}:N{$rowNumber}", 'FORMATTED_VALUE');
        $row = $verification[0] ?? [];
        if ((string) ($row[1] ?? '') !== (string) $user->id) {
            throw new RuntimeException('La verificación de Google Sheets no encontró el ID Web esperado después de sincronizar.');
        }
        if ($this->normalizeHeader((string) ($row[11] ?? '')) !== $this->normalizeHeader((string) $operational['L'])) {
            throw new RuntimeException('Google Sheets no refleja el estado esperado después de sincronizar.');
        }

        return [
            'action' => 'operational_synced',
            'row' => $rowNumber,
            'web_id' => (int) $user->id,
            'status' => (string) $operational['L'],
            'verified' => true,
            'sheet' => $sheetTitle,
        ];
    }

    public function manualTsvForUser(User $user): string
    {
        $submission = $this->recruitmentSubmission($user);
        if (! $submission) {
            throw new RuntimeException('No se encontró la solicitud de alistamiento vinculada.');
        }

        $this->assertPersonalDataAvailable($submission);

        return $this->manualTsv($user, $submission);
    }

    public function manualTsv(User $user, ContactSubmission $submission): string
    {
        $values = $this->rowValues($user, $submission);
        foreach ([6, 9, 12, 14, 15, 16] as $dateOffset) {
            if (isset($values[$dateOffset]) && is_numeric($values[$dateOffset])) {
                $values[$dateOffset] = \Illuminate\Support\Carbon::create(1899, 12, 30)
                    ->addDays((int) $values[$dateOffset])
                    ->format('Y-m-d');
            }
        }

        return implode("\t", array_map(function (mixed $value): string {
            if ($value === null) {
                return '';
            }

            return str_replace(["\t", "\r", "\n"], ' ', (string) $value);
        }, $values));
    }

    /** @return array<int, mixed> */
    private function rowValues(User $user, ContactSubmission $submission): array
    {
        $user->refresh()->loadMissing(['status', 'promo']);
        [$name, $surnames] = $this->splitFullName((string) $submission->full_name);
        $operational = $this->operationalValues($user, $submission);

        return [
            $operational['A'],
            $operational['B'],
            $name,
            $surnames,
            (string) ($submission->phone_whatsapp ?? ''),
            (string) ($submission->email ?? ''),
            $this->dateSerial($submission->birth_date),
            (string) ($submission->residence ?? ''),
            $operational['I'],
            $operational['J'],
            $operational['K'],
            $operational['L'],
            $operational['M'],
            $operational['N'],
            $operational['O'],
            $operational['P'],
            $operational['Q'],
        ];
    }

    /** @return array<string, mixed> */
    private function operationalValues(User $user, ?ContactSubmission $submission = null): array
    {
        $user->loadMissing(['status', 'promo']);
        $period = RecruitmentPeriod::query()
            ->where('user_id', $user->id)
            ->with('tutor')
            ->orderByDesc('period_number')
            ->orderByDesc('id')
            ->first();

        $submission ??= $this->recruitmentSubmission($user);
        $ingreso = $this->firstRecruitDate($user, $submission, $period);
        $summary = $this->veterancies->summary($user);
        $reserveDays = (int) ($summary['reserve_days'] ?? 0);

        return [
            'A' => (string) $user->nick,
            'B' => (int) $user->id,
            'I' => (string) ($period?->tutor_nick_snapshot ?: $period?->tutor?->nick ?: ''),
            'J' => $this->dateSerial($ingreso),
            'K' => $reserveDays,
            'L' => strtoupper(trim((string) ($user->status?->name ?? ''))),
            'M' => $this->dateSerial($user->member_at),
            'N' => $user->promo_id ? (int) $user->promo_id : null,
            // La hoja histórica calcula estas fechas como FECHA CALAVERA +
            // 1/3/5 años + los días acumulados en RESERVA. Al reactivarse
            // el miembro, la sincronización vuelve a calcular las tres fechas.
            'O' => $this->dateSerial($this->projectedVeterancyDate($user, VeterancyService::BRONZE, $reserveDays)),
            'P' => $this->dateSerial($this->projectedVeterancyDate($user, VeterancyService::SILVER, $reserveDays)),
            'Q' => $this->dateSerial($this->projectedVeterancyDate($user, VeterancyService::GOLD, $reserveDays)),
        ];
    }

    private function projectedVeterancyDate(User $user, string $level, int $reserveDays): ?\Carbon\CarbonInterface
    {
        if (! $user->member_at || ! isset(VeterancyService::LEVELS[$level])) {
            return null;
        }

        $years = (int) VeterancyService::LEVELS[$level]['years'];

        return \Illuminate\Support\Carbon::parse($user->member_at)
            ->startOfDay()
            ->addYears($years)
            ->addDays(max(0, $reserveDays));
    }

    private function firstRecruitDate(User $user, ?ContactSubmission $submission, ?RecruitmentPeriod $period): ?\Carbon\CarbonInterface
    {
        // INGRESO es histórico: la PRIMERA vez que el usuario entró en RECLUTA,
        // no el inicio del periodo de reclutamiento más reciente.
        $history = DB::table('user_status_histories as h')
            ->join('status as s', 's.id', '=', 'h.to_status_id')
            ->where('h.user_id', $user->id)
            ->whereRaw('UPPER(TRIM(s.name)) = ?', ['RECLUTA'])
            ->orderBy('h.changed_at')
            ->orderBy('h.id')
            ->value('h.changed_at');

        if ($history) {
            return \Illuminate\Support\Carbon::parse($history);
        }

        $firstPeriodStartedAt = RecruitmentPeriod::query()
            ->where('user_id', $user->id)
            ->whereNotNull('started_at')
            ->orderBy('period_number')
            ->orderBy('id')
            ->value('started_at');

        if ($firstPeriodStartedAt) {
            return \Illuminate\Support\Carbon::parse($firstPeriodStartedAt);
        }

        $firstSubmissionStartedAt = ContactSubmission::query()
            ->where('is_recruitment', true)
            ->where('recruitment_review_status', ContactSubmission::REVIEW_APPROVED)
            ->where('recruitment_matched_user_id', $user->id)
            ->whereNotNull('recruited_at')
            ->orderBy('recruited_at')
            ->value('recruited_at');

        if ($firstSubmissionStartedAt) {
            return \Illuminate\Support\Carbon::parse($firstSubmissionStartedAt);
        }

        return $submission?->recruited_at ?? $period?->started_at;
    }

    private function recruitmentSubmission(User $user): ?ContactSubmission
    {
        return ContactSubmission::query()
            ->where('is_recruitment', true)
            ->where('recruitment_review_status', ContactSubmission::REVIEW_APPROVED)
            ->where('recruitment_matched_user_id', $user->id)
            ->latest('id')
            ->first();
    }

    private function writeTransferRow(string $spreadsheetId, string $sheetTitle, int $rowNumber, array $values): void
    {
        $this->batchUpdateValues($spreadsheetId, [
            [
                'range' => $this->a1($sheetTitle, "A{$rowNumber}:J{$rowNumber}"),
                'values' => [array_slice($values, 0, 10)],
            ],
            [
                'range' => $this->a1($sheetTitle, "L{$rowNumber}:N{$rowNumber}"),
                'values' => [[...array_slice($values, 11, 3)]],
            ],
        ]);

        $operational = [
            'K' => $values[10] ?? null,
            'O' => $values[14] ?? null,
            'P' => $values[15] ?? null,
            'Q' => $values[16] ?? null,
        ];
        $this->writeCalculatedColumnsWhenSafe($spreadsheetId, $sheetTitle, $rowNumber, $operational);
    }

    /** @param array<string, mixed> $operational */
    private function writeVeterancyDatesWhenSafe(string $spreadsheetId, string $sheetTitle, int $rowNumber, array $operational): void
    {
        $this->writeCalculatedColumnsWhenSafe($spreadsheetId, $sheetTitle, $rowNumber, [
            'K' => $operational['K'] ?? null,
            'O' => $operational['O'] ?? null,
            'P' => $operational['P'] ?? null,
            'Q' => $operational['Q'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $values */
    private function writeCalculatedColumnsWhenSafe(string $spreadsheetId, string $sheetTitle, int $rowNumber, array $values): void
    {
        $formulaRow = $this->getValues(
            $spreadsheetId,
            $sheetTitle . "!K{$rowNumber}:Q{$rowNumber}",
            'FORMULA',
        )[0] ?? [];

        $columnOffsets = ['K' => 0, 'O' => 4, 'P' => 5, 'Q' => 6];
        $data = [];

        foreach ($columnOffsets as $column => $offset) {
            $existing = $formulaRow[$offset] ?? null;
            if (is_string($existing) && str_starts_with($existing, '=')) {
                continue;
            }

            if ($values[$column] === null && in_array($column, ['O', 'P', 'Q'], true)) {
                continue;
            }

            $data[] = [
                'range' => $this->a1($sheetTitle, "{$column}{$rowNumber}"),
                'values' => [[$values[$column]]],
            ];
        }

        if ($data !== []) {
            $this->batchUpdateValues($spreadsheetId, $data);
        }
    }

    private function appendRow(string $spreadsheetId, string $sheetTitle, array $values): int
    {
        $range = $this->a1($sheetTitle, 'A:Q');
        $path = '/spreadsheets/' . rawurlencode($spreadsheetId)
            . '/values/' . rawurlencode($range)
            . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS&includeValuesInResponse=false';

        $response = $this->client()->post($path, [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => [$values],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets rechazó la creación de la fila (HTTP ' . $response->status() . ').');
        }

        $updatedRange = (string) data_get($response->json(), 'updates.updatedRange', '');
        if (preg_match('/![A-Z]+(\d+):[A-Z]+(\d+)$/', $updatedRange, $matches)) {
            return (int) $matches[1];
        }

        $rowNumber = $this->findRowByWebId($spreadsheetId, $sheetTitle, (int) ($values[1] ?? 0));
        if ($rowNumber === null) {
            throw new RuntimeException('Google Sheets creó la fila, pero NewSlot no pudo localizarla después por ID Web.');
        }

        return $rowNumber;
    }

    private function verifyTransfer(
        string $spreadsheetId,
        string $sheetTitle,
        int $rowNumber,
        User $user,
        ContactSubmission $submission,
    ): void {
        $values = $this->getValues($spreadsheetId, $sheetTitle . "!A{$rowNumber}:Q{$rowNumber}", 'FORMATTED_VALUE');
        $row = $values[0] ?? [];
        [$expectedName, $expectedSurnames] = $this->splitFullName((string) $submission->full_name);

        $checks = [
            0 => (string) $user->nick,
            1 => (string) $user->id,
            2 => $expectedName,
            3 => $expectedSurnames,
            4 => (string) ($submission->phone_whatsapp ?? ''),
            5 => (string) ($submission->email ?? ''),
            7 => (string) ($submission->residence ?? ''),
        ];

        foreach ($checks as $offset => $expected) {
            if (trim((string) ($row[$offset] ?? '')) !== trim($expected)) {
                throw new RuntimeException('Google Sheets respondió correctamente, pero la verificación de la fila no coincide en una de las columnas transferidas. No se borrarán los datos personales.');
            }
        }

        if ($submission->birth_date && blank($row[6] ?? null)) {
            throw new RuntimeException('Google Sheets no refleja la fecha de nacimiento transferida. No se borrarán los datos personales.');
        }

        if ($user->promo_id && (string) ($row[13] ?? '') !== (string) $user->promo_id) {
            throw new RuntimeException('Google Sheets no refleja la promoción esperada. No se borrarán los datos personales.');
        }

        if ($user->member_at) {
            foreach ([14 => 'BRONCE', 15 => 'PLATA', 16 => 'ORO'] as $offset => $label) {
                if (blank($row[$offset] ?? null)) {
                    throw new RuntimeException("Google Sheets no refleja la fecha de veteranía {$label}. No se borrarán los datos personales.");
                }
            }
        }
    }

    private function findRowByWebId(string $spreadsheetId, string $sheetTitle, int $webId): ?int
    {
        $values = $this->getValues($spreadsheetId, $sheetTitle . '!B2:B', 'UNFORMATTED_VALUE');

        foreach ($values as $index => $row) {
            $candidate = $row[0] ?? null;
            if ($candidate !== null && (string) $candidate === (string) $webId) {
                return $index + 2;
            }
        }

        return null;
    }

    private function assertPersonalDataAvailable(ContactSubmission $submission): void
    {
        $required = [
            'full_name' => 'nombre y apellidos',
            'phone_whatsapp' => 'teléfono',
            'email' => 'email',
            'residence' => 'residencia',
            'birth_date' => 'fecha de nacimiento',
        ];

        $missing = [];
        foreach ($required as $field => $label) {
            if (blank($submission->{$field})) {
                $missing[] = $label;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Faltan datos personales en la solicitud para trasladarlos a Google Sheets: ' . implode(', ', $missing) . '.');
        }
    }

    private function assertExpectedHeaders(string $spreadsheetId, string $sheetTitle): void
    {
        $rows = $this->getValues($spreadsheetId, $sheetTitle . '!A1:Q1', 'FORMATTED_VALUE');
        $headers = $rows[0] ?? [];

        foreach (self::EXPECTED_HEADERS as $columnNumber => $expected) {
            $actual = (string) ($headers[$columnNumber - 1] ?? '');
            if ($this->normalizeHeader($actual) !== $expected) {
                throw new RuntimeException("La estructura de Google Sheets no coincide: columna {$columnNumber} debería ser {$expected} y actualmente es \"{$actual}\".");
            }
        }
    }

    private function sheetTitle(MemberProcedureSetting $setting): string
    {
        $configured = trim((string) config('newslot.procedures.google_sheets.sheet_name'));
        $spreadsheetId = $this->spreadsheetId($setting);
        $response = $this->client()->get('/spreadsheets/' . rawurlencode($spreadsheetId), [
            'fields' => 'sheets.properties(sheetId,title,index)',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('No se pudo abrir la hoja de cálculo de Google (HTTP ' . $response->status() . ').');
        }

        $sheets = (array) ($response->json('sheets') ?? []);
        if ($configured !== '') {
            foreach ($sheets as $sheet) {
                if ((string) data_get($sheet, 'properties.title') === $configured) {
                    return $configured;
                }
            }
        }

        $gid = (string) ($setting->google_general_sheet_gid ?? '');
        if ($gid !== '') {
            foreach ($sheets as $sheet) {
                if ((string) data_get($sheet, 'properties.sheetId') === $gid) {
                    return (string) data_get($sheet, 'properties.title');
                }
            }
        }

        throw new RuntimeException('No se encontró la pestaña General configurada en Google Sheets.');
    }

    private function spreadsheetId(MemberProcedureSetting $setting): string
    {
        return trim((string) ($setting->google_spreadsheet_id ?: config('newslot.procedures.google_sheets.spreadsheet_id')));
    }

    private function assertConfigured(MemberProcedureSetting $setting): void
    {
        if (! filled($this->spreadsheetId($setting))) {
            throw new RuntimeException('Google Sheets no está configurado. Revisa el Spreadsheet ID.');
        }

        $this->assertCredentialsAvailable();
    }

    private function assertCredentialsAvailable(): void
    {
        if (! (bool) config('newslot.procedures.google_sheets.enabled')) {
            throw new RuntimeException('Google Sheets está desactivado. Activa GOOGLE_SHEETS_ENABLED.');
        }

        $path = $this->credentialsPath();
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('El archivo de credenciales de Google Sheets no existe o no puede leerse dentro del contenedor.');
        }
    }

    private function credentialsPath(): string
    {
        $path = trim((string) config('newslot.procedures.google_sheets.credentials'));
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            return $path;
        }

        return base_path($path);
    }

    /** @return array<string, mixed> */
    private function credentials(): array
    {
        $path = $this->credentialsPath();
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('No se pudieron leer las credenciales de la cuenta de servicio de Google.');
        }

        $credentials = json_decode($json, true);
        if (! is_array($credentials)
            || ($credentials['type'] ?? null) !== 'service_account'
            || blank($credentials['client_email'] ?? null)
            || blank($credentials['private_key'] ?? null)) {
            throw new RuntimeException('El JSON configurado no contiene credenciales válidas de una cuenta de servicio de Google.');
        }

        return $credentials;
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $clientEmail = (string) $credentials['client_email'];
        $cacheKey = 'newslot:google-sheets-token:' . sha1($clientEmail);

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($credentials): string {
            $now = time();
            $header = ['alg' => 'RS256', 'typ' => 'JWT'];
            if (filled($credentials['private_key_id'] ?? null)) {
                $header['kid'] = (string) $credentials['private_key_id'];
            }

            $claims = [
                'iss' => (string) $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => (string) ($credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI),
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $unsigned = $this->base64Url(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
                . '.' . $this->base64Url(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $signature = '';
            if (! openssl_sign($unsigned, $signature, (string) $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('No se pudo firmar la autenticación de Google con la clave de la cuenta de servicio.');
            }

            $assertion = $unsigned . '.' . $this->base64Url($signature);
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('newslot.procedures.google_sheets.timeout', 15))
                ->post((string) ($credentials['token_uri'] ?? self::DEFAULT_TOKEN_URI), [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if (! $response->successful() || blank($response->json('access_token'))) {
                throw new RuntimeException('Google rechazó la autenticación de la cuenta de servicio (HTTP ' . $response->status() . ').');
            }

            return (string) $response->json('access_token');
        });
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::SHEETS_BASE_URL)
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('newslot.procedures.google_sheets.timeout', 15));
    }

    /** @return array<int, array<int, mixed>> */
    private function getValues(string $spreadsheetId, string $range, string $renderOption): array
    {
        $path = '/spreadsheets/' . rawurlencode($spreadsheetId) . '/values/' . rawurlencode($this->a1Raw($range));
        $response = $this->client()->get($path, [
            'majorDimension' => 'ROWS',
            'valueRenderOption' => $renderOption,
            'dateTimeRenderOption' => 'FORMATTED_STRING',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets rechazó la lectura del documento (HTTP ' . $response->status() . ').');
        }

        return array_values((array) ($response->json('values') ?? []));
    }

    private function updateValues(string $spreadsheetId, string $range, array $values, string $inputOption = 'USER_ENTERED'): void
    {
        $path = '/spreadsheets/' . rawurlencode($spreadsheetId)
            . '/values/' . rawurlencode($this->a1Raw($range))
            . '?valueInputOption=' . rawurlencode($inputOption);

        $response = $this->client()->put($path, [
            'range' => $this->a1Raw($range),
            'majorDimension' => 'ROWS',
            'values' => $values,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets rechazó la escritura en el documento (HTTP ' . $response->status() . ').');
        }
    }

    /** @param list<array{range:string,values:array}> $data */
    private function batchUpdateValues(string $spreadsheetId, array $data): void
    {
        $response = $this->client()->post('/spreadsheets/' . rawurlencode($spreadsheetId) . '/values:batchUpdate', [
            'valueInputOption' => 'RAW',
            'data' => $data,
            'includeValuesInResponse' => false,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets rechazó la actualización de la fila (HTTP ' . $response->status() . ').');
        }
    }

    private function a1(string $sheetTitle, string $cells): string
    {
        return "'" . str_replace("'", "''", $sheetTitle) . "'!{$cells}";
    }

    private function a1Raw(string $range): string
    {
        return $range;
    }

    /** @return array{0:string,1:string} */
    private function splitFullName(string $fullName): array
    {
        $fullName = trim((string) preg_replace('/\s+/u', ' ', $fullName));
        if ($fullName === '') {
            return ['', ''];
        }

        if (str_contains($fullName, ',')) {
            [$surnames, $name] = array_pad(array_map('trim', explode(',', $fullName, 2)), 2, '');
            return [$name, $surnames];
        }

        $parts = preg_split('/\s+/u', $fullName) ?: [];
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        $givenNameCount = count($parts) >= 4 ? 2 : 1;

        return [
            implode(' ', array_slice($parts, 0, $givenNameCount)),
            implode(' ', array_slice($parts, $givenNameCount)),
        ];
    }

    private function dateSerial(?\Carbon\CarbonInterface $date): ?int
    {
        if (! $date) {
            return null;
        }

        $epoch = \Illuminate\Support\Carbon::create(1899, 12, 30)->startOfDay();

        return $epoch->diffInDays(\Illuminate\Support\Carbon::instance($date)->startOfDay());
    }

    private function normalizeHeader(string $value): string
    {
        return strtoupper(trim(Str::ascii($value)));
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
