<?php

namespace App\Services\Treasury;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class TreasurySheetParser
{
    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{bank:?float,paypal:?float,total:?float}
     */
    public function summary(array $rows): array
    {
        $bank = $this->labeledNumber($rows, ['BANCO N26', 'BANCO ING', 'BANCO']);
        $paypal = $this->labeledNumber($rows, ['PAYPAL']);
        $total = $this->labeledNumber($rows, ['TESORERIA TOTAL', 'TOTAL TESORERIA', 'TOTAL']);

        if ($total === null && $bank !== null && $paypal !== null) {
            $total = $bank + $paypal;
        }

        return [
            'bank' => $bank,
            'paypal' => $paypal,
            'total' => $total,
        ];
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array{date:string,date_iso:string,category:string,account:string,amount:float,concept:string}>
     */
    public function expenses(array $rows, CarbonInterface $from): array
    {
        [$headerRow, $headers] = $this->headers($rows, [
            'FECHA',
            'TIPO',
            'CATEGORIA',
            'IMPORTE',
        ]);

        if ($headerRow === null) {
            return [];
        }

        $dateIndex = $this->headerIndex($headers, ['FECHA']);
        $typeIndex = $this->headerIndex($headers, ['TIPO']);
        $categoryIndex = $this->headerIndex($headers, ['CATEGORIA']);
        $originIndex = $this->headerIndex($headers, ['CUENTA ORIGEN']);
        $destinationIndex = $this->headerIndex($headers, ['CUENTA DESTINO']);
        $amountIndex = $this->headerIndex($headers, ['IMPORTE', 'IMPORTE EUR']);
        $conceptIndex = $this->headerIndex($headers, ['CONCEPTO NOTAS', 'CONCEPTO', 'NOTAS']);

        if ($dateIndex === null || $typeIndex === null || $amountIndex === null) {
            return [];
        }

        $result = [];
        $threshold = CarbonImmutable::instance($from)->startOfDay();

        foreach (array_slice($rows, $headerRow + 1) as $offset => $row) {
            if ($this->normalize((string) ($row[$typeIndex] ?? '')) !== 'GASTO') {
                continue;
            }

            $date = $this->date($row[$dateIndex] ?? null);
            if (! $date || $date->lt($threshold)) {
                continue;
            }

            $amount = $this->number($row[$amountIndex] ?? null);
            if ($amount === null) {
                continue;
            }

            $origin = trim((string) ($originIndex !== null ? ($row[$originIndex] ?? '') : ''));
            $destination = trim((string) ($destinationIndex !== null ? ($row[$destinationIndex] ?? '') : ''));

            $result[] = [
                'date' => $date->format('d/m/Y'),
                'date_iso' => $date->format('Y-m-d'),
                'category' => trim((string) ($categoryIndex !== null ? ($row[$categoryIndex] ?? '') : '')) ?: 'Otros',
                'account' => $origin !== '' ? $origin : ($destination !== '' ? $destination : '—'),
                'amount' => $amount,
                'concept' => trim((string) ($conceptIndex !== null ? ($row[$conceptIndex] ?? '') : '')) ?: 'Sin concepto',
                '_order' => $date->timestamp * 10000 + $offset,
            ];
        }

        usort($result, fn (array $a, array $b): int => ($b['_order'] ?? 0) <=> ($a['_order'] ?? 0));

        return array_map(function (array $row): array {
            unset($row['_order']);

            return $row;
        }, $result);
    }

    /**
     * @param  array<int, array<int, mixed>>  $playersRows
     * @param  array<int, array<int, mixed>>  $duesRows
     * @param  array<int, array<int, mixed>>  $movementRows
     * @return array{found:bool,player_id:?int,remanent:?float,next_quarter:?string,last_payment:?array,state:?string}
     */
    public function member(array $playersRows, array $duesRows, array $movementRows, string $nick): array
    {
        [$playersHeaderRow, $playerHeaders] = $this->headers($playersRows, ['ID', 'NICK', 'REMANENTE']);

        if ($playersHeaderRow === null) {
            return $this->emptyMember();
        }

        $idIndex = $this->headerIndex($playerHeaders, ['ID']);
        $nickIndex = $this->headerIndex($playerHeaders, ['NICK', 'JUGADOR']);
        $remanentIndex = $this->headerIndex($playerHeaders, ['REMANENTE ACTUAL EUR', 'REMANENTE ACTUAL', 'REMANENTE']);
        $stateIndex = $this->headerIndex($playerHeaders, ['ESTADO']);

        if ($idIndex === null || $nickIndex === null) {
            return $this->emptyMember();
        }

        $normalizedNick = $this->normalize($nick);
        $player = null;

        foreach (array_slice($playersRows, $playersHeaderRow + 1) as $row) {
            $rowNick = $this->normalize((string) ($row[$nickIndex] ?? ''));

            // La unión entre la web y Tesorería es únicamente el nickname.
            if ($rowNick !== '' && $rowNick === $normalizedNick) {
                $player = $row;
                break;
            }
        }

        if (! is_array($player)) {
            return $this->emptyMember();
        }

        $playerId = (int) ($player[$idIndex] ?? 0);
        if ($playerId <= 0) {
            $playerId = null;
        }

        return [
            'found' => true,
            'player_id' => $playerId,
            'remanent' => $remanentIndex !== null ? $this->number($player[$remanentIndex] ?? null) : null,
            'next_quarter' => $this->duesStatus($duesRows, $nick),
            'last_payment' => $this->lastPayment($movementRows, $nick),
            'state' => $stateIndex !== null ? (trim((string) ($player[$stateIndex] ?? '')) ?: null) : null,
        ];
    }

    /**
     * Interpreta la tabla privada de Jugadores sin modificarla.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{
     *     valid:bool,reason:?string,header_row:?int,nick_column:?string,state_column:?string,date_column:?string,
     *     entries:array<int,array{row:int,id:?int,nick:string,state:string,date:mixed}>,next_row:?int
     * }
     */
    public function playersTable(array $rows): array
    {
        [$headerRow, $headers] = $this->headers($rows, ['ID', 'NICK', 'ESTADO']);

        if ($headerRow === null) {
            return [
                'valid' => false,
                'reason' => 'No se encontró la cabecera de la pestaña Jugadores.',
                'header_row' => null,
                'nick_column' => null,
                'state_column' => null,
                'date_column' => null,
                'entries' => [],
                'next_row' => null,
            ];
        }

        $idIndex = $this->headerIndex($headers, ['ID']);
        $nickIndex = $this->headerIndex($headers, ['NICK', 'JUGADOR']);
        $stateIndex = $this->headerIndex($headers, ['ESTADO']);
        $dateIndex = $this->headerIndex($headers, ['FECHA ALTA', 'ALTA']);

        if ($nickIndex === null || $stateIndex === null || $dateIndex === null) {
            return [
                'valid' => false,
                'reason' => 'Faltan Nick, Estado o Fecha alta en la pestaña Jugadores.',
                'header_row' => $headerRow + 1,
                'nick_column' => $nickIndex !== null ? $this->columnLetter($nickIndex + 1) : null,
                'state_column' => $stateIndex !== null ? $this->columnLetter($stateIndex + 1) : null,
                'date_column' => $dateIndex !== null ? $this->columnLetter($dateIndex + 1) : null,
                'entries' => [],
                'next_row' => null,
            ];
        }

        $entries = [];
        $lastOccupiedRow = $headerRow + 1;

        foreach (array_slice($rows, $headerRow + 1) as $offset => $row) {
            $sheetRow = $headerRow + 2 + $offset;
            $nick = trim((string) ($row[$nickIndex] ?? ''));

            if ($nick === '') {
                continue;
            }

            $lastOccupiedRow = max($lastOccupiedRow, $sheetRow);
            $id = $idIndex !== null ? (int) ($row[$idIndex] ?? 0) : 0;

            $entries[] = [
                'row' => $sheetRow,
                'id' => $id > 0 ? $id : null,
                'nick' => $nick,
                'state' => trim((string) ($row[$stateIndex] ?? '')),
                'date' => $row[$dateIndex] ?? null,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'header_row' => $headerRow + 1,
            'nick_column' => $this->columnLetter($nickIndex + 1),
            'state_column' => $this->columnLetter($stateIndex + 1),
            'date_column' => $this->columnLetter($dateIndex + 1),
            'entries' => $entries,
            // Se escribe siempre después del último nick real, nunca rellenando
            // huecos intermedios que puedan haberse dejado a propósito.
            'next_row' => $lastOccupiedRow + 1,
        ];
    }

    /**
     * Comprueba si el usuario tiene registrada una señal real de al menos 6 €.
     * Se suman varios movimientos de tipo Señal por si el pago se fraccionó.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{paid:bool,total:float,count:int,last_date:?string}
     */
    public function signalPayment(array $rows, string $nick): array
    {
        [$headerRow, $headers] = $this->headers($rows, ['FECHA', 'TIPO', 'IMPORTE']);
        if ($headerRow === null) {
            return ['paid' => false, 'total' => 0.0, 'count' => 0, 'last_date' => null];
        }

        $dateIndex = $this->headerIndex($headers, ['FECHA']);
        $typeIndex = $this->headerIndex($headers, ['TIPO']);
        $playerIndex = $this->headerIndex($headers, ['JUGADOR ID NICK', 'JUGADOR']);
        $amountIndex = $this->headerIndex($headers, ['IMPORTE', 'IMPORTE EUR']);

        if ($typeIndex === null || $playerIndex === null || $amountIndex === null) {
            return ['paid' => false, 'total' => 0.0, 'count' => 0, 'last_date' => null];
        }

        $normalizedNick = $this->normalize($nick);
        $total = 0.0;
        $count = 0;
        $lastDate = null;

        foreach (array_slice($rows, $headerRow + 1) as $row) {
            if ($this->normalize((string) ($row[$typeIndex] ?? '')) !== 'SENAL') {
                continue;
            }

            if (! $this->movementNickMatches((string) ($row[$playerIndex] ?? ''), $normalizedNick)) {
                continue;
            }

            $amount = $this->number($row[$amountIndex] ?? null);
            if ($amount === null || $amount <= 0) {
                continue;
            }

            $total += $amount;
            $count++;

            if ($dateIndex !== null) {
                $date = $this->date($row[$dateIndex] ?? null);
                if ($date && ($lastDate === null || $date->format('Y-m-d') > $lastDate)) {
                    $lastDate = $date->format('Y-m-d');
                }
            }
        }

        return [
            'paid' => $total >= 6.0 - 0.00001,
            'total' => round($total, 2),
            'count' => $count,
            'last_date' => $lastDate,
        ];
    }

    /** @return array{found:bool,player_id:null,remanent:null,next_quarter:null,last_payment:null,state:null} */
    private function emptyMember(): array
    {
        return [
            'found' => false,
            'player_id' => null,
            'remanent' => null,
            'next_quarter' => null,
            'last_payment' => null,
            'state' => null,
        ];
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function duesStatus(array $rows, string $nick): ?string
    {
        [$headerRow, $headers] = $this->headers($rows, ['JUGADOR', 'REMANENTE']);
        if ($headerRow === null) {
            return null;
        }

        $nickIndex = $this->headerIndex($headers, ['JUGADOR', 'NICK']);
        $nextIndex = $this->headerIndex($headers, [
            'PROX TRIMESTRE',
            'PROXIMO TRIMESTRE',
            'SIGUIENTE TRIMESTRE',
        ]);

        if ($nickIndex === null || $nextIndex === null) {
            return null;
        }

        $normalizedNick = $this->normalize($nick);
        foreach (array_slice($rows, $headerRow + 1) as $row) {
            if ($this->normalize((string) ($row[$nickIndex] ?? '')) !== $normalizedNick) {
                continue;
            }

            $value = trim((string) ($row[$nextIndex] ?? ''));

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{date:string,date_iso:string,amount:float,type:string,concept:string}|null
     */
    private function lastPayment(array $rows, string $nick): ?array
    {
        [$headerRow, $headers] = $this->headers($rows, ['FECHA', 'TIPO', 'IMPORTE']);
        if ($headerRow === null) {
            return null;
        }

        $dateIndex = $this->headerIndex($headers, ['FECHA']);
        $typeIndex = $this->headerIndex($headers, ['TIPO']);
        $playerIndex = $this->headerIndex($headers, ['JUGADOR ID NICK', 'JUGADOR']);
        $amountIndex = $this->headerIndex($headers, ['IMPORTE', 'IMPORTE EUR']);
        $conceptIndex = $this->headerIndex($headers, ['CONCEPTO NOTAS', 'CONCEPTO', 'NOTAS']);

        if ($dateIndex === null || $typeIndex === null || $amountIndex === null) {
            return null;
        }

        $normalizedNick = $this->normalize($nick);
        $matches = [];

        foreach (array_slice($rows, $headerRow + 1) as $offset => $row) {
            $type = $this->normalize((string) ($row[$typeIndex] ?? ''));
            if (! in_array($type, ['PAGO JUGADOR', 'SENAL'], true)) {
                continue;
            }

            if ($playerIndex === null || ! $this->movementNickMatches((string) ($row[$playerIndex] ?? ''), $normalizedNick)) {
                continue;
            }

            $date = $this->date($row[$dateIndex] ?? null);
            $amount = $this->number($row[$amountIndex] ?? null);
            if (! $date || $amount === null) {
                continue;
            }

            $matches[] = [
                'date' => $date->format('d/m/Y'),
                'date_iso' => $date->format('Y-m-d'),
                'amount' => $amount,
                'type' => trim((string) ($row[$typeIndex] ?? '')),
                'concept' => trim((string) ($conceptIndex !== null ? ($row[$conceptIndex] ?? '') : '')),
                '_order' => $date->timestamp * 10000 + $offset,
            ];
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, fn (array $a, array $b): int => $b['_order'] <=> $a['_order']);
        $latest = $matches[0];
        unset($latest['_order']);

        return $latest;
    }

    /**
     * Convierte el remanente mensual de la hoja en una lectura trimestral para el usuario.
     * La cuota base es de 9 € por trimestre (3 meses x 3 €). Durante el trimestre,
     * las X ya cargadas se suman temporalmente al remanente para reconstruir cuánto
     * dinero había disponible para cubrir el trimestre completo y evitar mostrar una
     * falsa deuda a medida que se consumen los meses.
     *
     * El trimestre que se muestra cambia durante el mes anterior a su inicio:
     * MAR -> T2, JUN -> T3, SEP -> T4 y DIC -> T1 del año siguiente.
     *
     * @param  array<int, array<int, mixed>>  $controlRows
     * @return array{
     *     quarter:int,quarter_year:int,quarter_label:string,quarter_period:string,
     *     quarter_price:float,quarter_paid:bool,quarter_missing:float,
     *     quarter_available:float,display_balance:float,consumed_months:int,
     *     gifted_months:int,excluded_months:int,billable_months:int,pending_current_month:float,arrears_due:float,quarter_due:float
     * }
     */
    public function quarterlyBalance(
        array $controlRows,
        string $nick,
        ?float $remanent,
        CarbonInterface $date,
    ): array {
        $target = $this->billingQuarter($date);
        $quarterPrice = 9.0;
        $rawRemanent = (float) ($remanent ?? 0.0);
        $consumedMonths = 0;
        $giftedMonths = 0;
        $excludedMonths = 0;
        $pendingCurrentMonth = 0.0;

        [$headerRow, $headers] = $this->headers($controlRows, ['JUGADOR', 'ENE', 'DIC']);

        if ($headerRow !== null) {
            $controlYear = null;
            foreach (array_slice($controlRows, 0, $headerRow + 1) as $row) {
                foreach ($row as $value) {
                    if (preg_match('/\\b(20\\d{2})\\b/', (string) $value, $matches)) {
                        $controlYear = (int) $matches[1];
                        break 2;
                    }
                }
            }

            if ($controlYear === (int) $date->year) {
                $nickIndex = $this->headerIndex($headers, ['JUGADOR', 'NICK']);
                $normalizedNick = $this->normalize($nick);
                $memberRow = null;

                if ($nickIndex !== null) {
                    foreach (array_slice($controlRows, $headerRow + 1) as $row) {
                        if ($this->normalize((string) ($row[$nickIndex] ?? '')) === $normalizedNick) {
                            $memberRow = $row;
                            break;
                        }
                    }
                }

                if (is_array($memberRow)) {
                    if ($target['year'] === (int) $date->year) {
                        foreach ($target['months'] as $month) {
                            $monthIndex = $this->headerIndex($headers, [$month]);
                            if ($monthIndex === null) {
                                continue;
                            }

                            $rawMarker = trim((string) ($memberRow[$monthIndex] ?? ''));
                            $marker = $this->normalize($rawMarker);

                            if ($marker === 'X') {
                                $consumedMonths++;
                            }

                            if ($marker === 'G') {
                                // G = mes regalado manualmente por Tesorería. No consume
                                // remanente y tampoco forma parte del importe exigible.
                                $giftedMonths++;
                                $excludedMonths++;
                                continue;
                            }

                            if ($rawMarker === '-' || $marker === 'R') {
                                $excludedMonths++;
                            }
                        }

                        $quarterPrice = max(0.0, (3 - $excludedMonths) * 3.0);
                    }

                    // MAR/JUN/SEP/DIC son meses de cobro del siguiente trimestre.
                    // Hasta que llegue el día 15, la X del mes actual todavía puede
                    // estar vacía. Reservamos esos 3 € antes de calcular cuánto saldo
                    // queda realmente disponible para el trimestre siguiente.
                    if (in_array((int) $date->month, [3, 6, 9, 12], true)) {
                        $currentMonths = [
                            3 => 'MAR', 6 => 'JUN', 9 => 'SEP', 12 => 'DIC',
                        ];
                        $currentMonth = $currentMonths[(int) $date->month];
                        $currentIndex = $this->headerIndex($headers, [$currentMonth]);
                        $stateIndex = $this->headerIndex($headers, ['ESTADO']);
                        $state = $stateIndex !== null
                            ? $this->normalize((string) ($memberRow[$stateIndex] ?? ''))
                            : '';
                        $currentRaw = $currentIndex !== null
                            ? trim((string) ($memberRow[$currentIndex] ?? ''))
                            : '';

                        if (
                            $currentRaw === ''
                            && in_array($state, ['MIEMBRO', 'ACTIVO', 'RECLUTA'], true)
                        ) {
                            $pendingCurrentMonth = 3.0;
                        }
                    }
                }
            }
        }

        $quarterAvailable = $rawRemanent + ($consumedMonths * 3.0) - $pendingCurrentMonth;
        $arrearsDue = max(0.0, -$quarterAvailable);
        $usableForQuarter = max(0.0, $quarterAvailable);
        $quarterDue = max(0.0, $quarterPrice - $usableForQuarter);
        $quarterMissing = $arrearsDue + $quarterDue;
        $quarterPaid = $quarterMissing <= 0.00001;
        $displayBalance = $quarterPaid
            ? max(0.0, $usableForQuarter - $quarterPrice)
            : $usableForQuarter;

        return [
            'quarter' => $target['quarter'],
            'quarter_year' => $target['year'],
            'quarter_label' => $target['label'],
            'quarter_period' => $target['period'],
            'quarter_price' => $quarterPrice,
            'quarter_paid' => $quarterPaid,
            'quarter_missing' => $quarterMissing,
            'quarter_available' => max(0.0, $quarterAvailable),
            'display_balance' => $displayBalance,
            'consumed_months' => $consumedMonths,
            'gifted_months' => $giftedMonths,
            'excluded_months' => $excludedMonths,
            'billable_months' => max(0, 3 - $excludedMonths),
            'pending_current_month' => $pendingCurrentMonth,
            'arrears_due' => $arrearsDue,
            'quarter_due' => $quarterDue,
        ];
    }

    /**
     * @return array{quarter:int,year:int,label:string,period:string,months:array<int,string>}
     */
    private function billingQuarter(CarbonInterface $date): array
    {
        $month = (int) $date->month;
        $year = (int) $date->year;

        if ($month === 12) {
            $quarter = 1;
            $year++;
        } elseif ($month <= 2) {
            $quarter = 1;
        } elseif ($month <= 5) {
            $quarter = 2;
        } elseif ($month <= 8) {
            $quarter = 3;
        } else {
            $quarter = 4;
        }

        $months = match ($quarter) {
            1 => ['ENE', 'FEB', 'MAR'],
            2 => ['ABR', 'MAY', 'JUN'],
            3 => ['JUL', 'AGO', 'SEP'],
            default => ['OCT', 'NOV', 'DIC'],
        };

        $ordinal = match ($quarter) {
            1 => '1.er',
            2 => '2.º',
            3 => '3.er',
            default => '4.º',
        };

        return [
            'quarter' => $quarter,
            'year' => $year,
            'label' => $ordinal . ' trimestre ' . $year,
            'period' => implode(' · ', $months),
            'months' => $months,
        ];
    }

    /**
     * Prepara las celdas vacías del mes que deben rellenarse el día 15.
     * Cualquier valor ya existente se considera manual y nunca se modifica.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{
     *     valid:bool,reason:?string,month:string,year:int,header_row:?int,month_column:?string,
     *     updates:array<int,array{row:int,range:string,value:string,nick:string,state:string}>,
     *     existing:int,ignored:int
     * }
     */
    public function monthlyControl(array $rows, CarbonInterface $date): array
    {
        $months = [
            1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN',
            7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
        ];
        $month = $months[(int) $date->month];
        $year = (int) $date->year;

        [$headerRow, $headers] = $this->headers($rows, ['JUGADOR', 'ESTADO', $month]);
        if ($headerRow === null) {
            return $this->invalidMonthlyControl($month, $year, 'No se encontró la cabecera de Control mensual.');
        }

        $top = array_slice($rows, 0, $headerRow + 1);
        $yearFound = false;
        foreach ($top as $row) {
            foreach ($row as $value) {
                if (preg_match('/(?:^|\D)' . preg_quote((string) $year, '/') . '(?:\D|$)/', (string) $value)) {
                    $yearFound = true;
                    break 2;
                }
            }
        }
        if (! $yearFound) {
            return $this->invalidMonthlyControl($month, $year, 'La hoja Control mensual no corresponde al año ' . $year . '.');
        }

        $nickIndex = $this->headerIndex($headers, ['JUGADOR', 'NICK']);
        $stateIndex = $this->headerIndex($headers, ['ESTADO']);
        $monthIndex = $this->headerIndex($headers, [$month]);
        if ($nickIndex === null || $stateIndex === null || $monthIndex === null) {
            return $this->invalidMonthlyControl($month, $year, 'Faltan columnas necesarias en Control mensual.');
        }

        $updates = [];
        $existing = 0;
        $ignored = 0;

        foreach (array_slice($rows, $headerRow + 1) as $offset => $row) {
            $nick = trim((string) ($row[$nickIndex] ?? ''));
            if ($nick === '') {
                continue;
            }

            $stateRaw = trim((string) ($row[$stateIndex] ?? ''));
            $state = $this->normalize($stateRaw);
            $value = match (true) {
                in_array($state, ['MIEMBRO', 'ACTIVO', 'RECLUTA'], true) => 'X',
                $state === 'RESERVA' => 'R',
                in_array($state, ['CESADO', 'CESE'], true) => '-',
                default => null,
            };

            if ($value === null) {
                $ignored++;
                continue;
            }

            // Prioridad absoluta a lo escrito manualmente: si la celda contiene
            // cualquier cosa (X, R, G, -, texto, etc.), no se toca.
            if (trim((string) ($row[$monthIndex] ?? '')) !== '') {
                $existing++;
                continue;
            }

            $sheetRow = $headerRow + 2 + $offset;
            $column = $this->columnLetter($monthIndex + 1);
            $updates[] = [
                'row' => $sheetRow,
                'range' => $column . $sheetRow . ':' . $column . $sheetRow,
                'value' => $value,
                'nick' => $nick,
                'state' => $stateRaw,
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'month' => $month,
            'year' => $year,
            'header_row' => $headerRow + 1,
            'month_column' => $this->columnLetter($monthIndex + 1),
            'updates' => $updates,
            'existing' => $existing,
            'ignored' => $ignored,
        ];
    }

    /** @return array{valid:false,reason:string,month:string,year:int,header_row:null,month_column:null,updates:array,existing:int,ignored:int} */
    private function invalidMonthlyControl(string $month, int $year, string $reason): array
    {
        return [
            'valid' => false,
            'reason' => $reason,
            'month' => $month,
            'year' => $year,
            'header_row' => null,
            'month_column' => null,
            'updates' => [],
            'existing' => 0,
            'ignored' => 0,
        ];
    }

    private function movementNickMatches(string $playerText, string $normalizedNick): bool
    {
        if ($normalizedNick === '') {
            return false;
        }

        $parts = preg_split('/\|/', $playerText, 2);
        $candidate = count($parts) === 2 ? (string) $parts[1] : $playerText;

        return $this->normalize($candidate) === $normalizedNick;
    }

    private function columnLetter(int $columnNumber): string
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)) . $letter;
            $columnNumber = intdiv($columnNumber, 26);
        }

        return $letter;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $aliases
     */
    private function labeledNumber(array $rows, array $aliases): ?float
    {
        $aliases = array_map(fn (string $value): string => $this->normalize($value), $aliases);

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                if (! in_array($this->normalize((string) $value), $aliases, true)) {
                    continue;
                }

                foreach ([
                    [$rowIndex + 1, $columnIndex],
                    [$rowIndex, $columnIndex + 1],
                    [$rowIndex + 1, $columnIndex + 1],
                    [$rowIndex + 2, $columnIndex],
                ] as [$candidateRow, $candidateColumn]) {
                    $number = $this->number($rows[$candidateRow][$candidateColumn] ?? null);
                    if ($number !== null) {
                        return $number;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $required
     * @return array{0:?int,1:array<int,string>}
     */
    private function headers(array $rows, array $required): array
    {
        $required = array_map(fn (string $value): string => $this->normalize($value), $required);

        foreach ($rows as $rowIndex => $row) {
            $headers = array_map(fn (mixed $value): string => $this->normalize((string) $value), $row);
            $matched = 0;

            foreach ($required as $requiredHeader) {
                if ($this->headerIndex($headers, [$requiredHeader]) !== null) {
                    $matched++;
                }
            }

            if ($matched === count($required)) {
                return [$rowIndex, $headers];
            }
        }

        return [null, []];
    }

    /** @param array<int, string> $headers @param array<int, string> $aliases */
    private function headerIndex(array $headers, array $aliases): ?int
    {
        $aliases = array_map(fn (string $value): string => $this->normalize($value), $aliases);

        // Primero coincidencias exactas. Evita que alias cortos como «ID»
        // capturen antes columnas compuestas como «Jugador (ID | Nick)».
        foreach ($headers as $index => $header) {
            $header = $this->normalize($header);
            if (in_array($header, $aliases, true)) {
                return (int) $index;
            }
        }

        foreach ($headers as $index => $header) {
            $header = $this->normalize($header);

            foreach ($aliases as $alias) {
                if (strlen($alias) >= 4 && str_contains($header, $alias)) {
                    return (int) $index;
                }
            }
        }

        return null;
    }

    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(["\xc2\xa0", '€', ' '], '', $value);

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d+(?:\.\d+)?$/', trim($value)))) {
            $serial = (int) floor((float) $value);
            if ($serial > 1000) {
                return CarbonImmutable::create(1899, 12, 30)->addDays($serial)->startOfDay();
            }
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'j/n/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->startOfDay();
                }
            } catch (\Throwable) {
                // Try the next known format.
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = strtoupper(Str::ascii(trim($value)));
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
