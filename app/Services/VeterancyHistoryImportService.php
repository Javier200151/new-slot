<?php

namespace App\Services;

use App\Models\Status;
use App\Models\User;
use App\Models\UserStatusHistory;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use Throwable;

class VeterancyHistoryImportService
{
    public function import(string $path, bool $dryRun = false): array
    {
        $rows = $this->readRows($path);
        $result = [
            'dry_run' => $dryRun,
            'total' => count($rows),
            'imported' => 0,
            'duplicates' => 0,
            'before_member_at' => 0,
            'not_found' => [],
            'without_member_at' => [],
            'invalid' => [],
        ];

        $statusIds = Status::withTrashed()
            ->whereIn('name', ['ACTIVO', 'RESERVA'])
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name): array => [strtoupper((string) $name) => (int) $id])
            ->all();

        if (! isset($statusIds['ACTIVO'], $statusIds['RESERVA'])) {
            throw new \RuntimeException('No se encontraron los estados ACTIVO y RESERVA necesarios para la importación.');
        }

        $entries = [];

        foreach ($rows as $rowNumber => $row) {
            $displayRow = $rowNumber + 2;
            $nick = trim((string) ($row['nombre'] ?? ''));
            $stateText = trim((string) ($row['estado'] ?? ''));
            $targetName = $this->targetStatusName($stateText);
            $date = $this->parseDate($row['fecha'] ?? null);

            if ($nick === '' || ! $date || ! $targetName || ! isset($statusIds[$targetName])) {
                $result['invalid'][] = "Fila {$displayRow}: nombre, fecha o estado no válidos.";
                continue;
            }

            $user = User::withTrashed()
                ->whereRaw('LOWER(nick) = ?', [Str::lower($nick)])
                ->first();

            if (! $user) {
                $result['not_found'][] = $nick;
                continue;
            }

            if (! $user->member_at) {
                $result['without_member_at'][] = $user->nick;
                continue;
            }

            $memberAt = Carbon::parse($user->member_at)->startOfDay();
            $changedAt = $date->copy()->startOfDay();
            if ($changedAt->lt($memberAt)) {
                $result['before_member_at']++;
                continue;
            }

            $entries[] = [
                'row' => $displayRow,
                'user' => $user,
                'changed_at' => $changedAt,
                'target_name' => $targetName,
                'target_status_id' => $statusIds[$targetName],
                'state_text' => $stateText,
                'retutored_by' => trim((string) ($row['retuto_por'] ?? '')) ?: null,
            ];
        }

        usort($entries, function (array $left, array $right): int {
            $userCompare = ((int) $left['user']->id) <=> ((int) $right['user']->id);
            if ($userCompare !== 0) {
                return $userCompare;
            }

            $dateCompare = $left['changed_at']->getTimestamp() <=> $right['changed_at']->getTimestamp();

            return $dateCompare !== 0 ? $dateCompare : ($left['row'] <=> $right['row']);
        });

        $seenHashes = [];
        $simulated = [];

        foreach ($entries as $entry) {
            /** @var User $user */
            $user = $entry['user'];
            /** @var Carbon $changedAt */
            $changedAt = $entry['changed_at'];
            $targetStatusId = (int) $entry['target_status_id'];
            $retutoredBy = $entry['retutored_by'];

            $hash = hash('sha256', implode('|', [
                Str::lower($user->nick),
                $changedAt->format('Y-m-d'),
                $entry['target_name'],
                Str::lower((string) $retutoredBy),
            ]));

            if (isset($seenHashes[$hash]) || UserStatusHistory::query()->where('source_hash', $hash)->exists()) {
                $result['duplicates']++;
                continue;
            }
            $seenHashes[$hash] = true;

            $alreadyExists = UserStatusHistory::query()
                ->where('user_id', $user->id)
                ->whereDate('changed_at', $changedAt->toDateString())
                ->where('to_status_id', $targetStatusId)
                ->exists();

            if ($alreadyExists) {
                $result['duplicates']++;
                continue;
            }

            [$previousStatusId, $previousChangedAt] = $this->statusBefore(
                $user,
                $changedAt,
                $statusIds['ACTIVO'],
            );

            if ($dryRun && isset($simulated[$user->id])) {
                $simulatedEntry = $simulated[$user->id];
                if (
                    $simulatedEntry['changed_at']->lt($changedAt)
                    && ($previousChangedAt === null || $simulatedEntry['changed_at']->gt($previousChangedAt))
                ) {
                    $previousStatusId = $simulatedEntry['status_id'];
                }
            }

            if ($previousStatusId === $targetStatusId) {
                $result['duplicates']++;
                continue;
            }

            if (! $dryRun) {
                UserStatusHistory::create([
                    'user_id' => $user->id,
                    'from_status_id' => $previousStatusId,
                    'to_status_id' => $targetStatusId,
                    'changed_at' => $changedAt,
                    'changed_by_user_id' => Auth::id(),
                    'source' => 'historical_import',
                    'source_hash' => $hash,
                    'retutored_by' => $retutoredBy,
                    'note' => $entry['state_text'],
                ]);
            } else {
                $simulated[$user->id] = [
                    'status_id' => $targetStatusId,
                    'changed_at' => $changedAt,
                ];
            }

            $result['imported']++;
        }

        $result['not_found'] = array_values(array_unique($result['not_found']));
        $result['without_member_at'] = array_values(array_unique($result['without_member_at']));

        return $result;
    }

    private function readRows(string $path): array
    {
        $reader = ReaderFactory::createFromFile($path);
        $reader->open($path);
        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $header = null;
                foreach ($sheet->getRowIterator() as $row) {
                    $values = $row->toArray();
                    if ($header === null) {
                        $header = array_map(fn ($value): string => $this->normalizeHeader((string) $value), $values);
                        continue;
                    }

                    if (collect($values)->filter(fn ($value): bool => filled($value))->isEmpty()) {
                        continue;
                    }

                    $mapped = [];
                    foreach ($header as $index => $key) {
                        if ($key !== '') {
                            $mapped[$key] = $values[$index] ?? null;
                        }
                    }
                    $rows[] = $mapped;
                }
                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function normalizeHeader(string $value): string
    {
        $value = Str::of($value)->ascii()->lower()->trim()->replace(['-', ' '], '_')->toString();

        return match ($value) {
            'nombre', 'nick', 'usuario' => 'nombre',
            'fecha', 'date' => 'fecha',
            'estado', 'status' => 'estado',
            're_tuto_por', 'retuto_por', 're_tutor_por', 'retutor_por' => 'retuto_por',
            default => $value,
        };
    }

    private function targetStatusName(string $value): ?string
    {
        $normalized = Str::of($value)->ascii()->upper()->trim()->toString();

        if (str_contains($normalized, 'RESERVA')) {
            return 'RESERVA';
        }

        if (str_contains($normalized, 'ACTIVO')) {
            return 'ACTIVO';
        }

        return null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            try {
                // Serial de Excel (base 1899-12-30).
                return Carbon::create(1899, 12, 30)->addDays((int) $value);
            } catch (Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!' . $format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function statusBefore(User $user, Carbon $changedAt, int $defaultActiveId): array
    {
        $history = DB::table('user_status_histories')
            ->where('user_id', $user->id)
            ->where('changed_at', '<', $changedAt->toDateTimeString())
            ->orderByDesc('changed_at')
            ->orderByDesc('id')
            ->first(['to_status_id', 'changed_at']);

        if (! $history) {
            return [$defaultActiveId, null];
        }

        return [
            (int) $history->to_status_id,
            Carbon::parse($history->changed_at),
        ];
    }
}
