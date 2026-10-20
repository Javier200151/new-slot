<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proyecta la fecha documentada del primer reclutamiento en el historial visible.
 * No altera estados, periodos, member_at ni los contadores de veterania.
 */
class FirstRecruitmentHistoryService
{
    public const SOURCE = 'documented_recruitment';

    public function syncUser(User $user): void
    {
        $this->sync(
            (int) $user->id,
            $user->recruited_at?->toDateString(),
            $user->member_at?->toDateString(),
        );
    }

    public function sync(int $userId, ?string $date, ?string $memberAt = null): void
    {
        if (! Schema::hasTable('user_status_histories') || ! Schema::hasTable('status')) {
            return;
        }

        $hash = hash('sha256', self::SOURCE . '|' . $userId);
        $row = DB::table('user_status_histories')->where('source_hash', $hash)->first();

        // Solo se eliminan las filas derivadas que crea este servicio.
        if ($date === null || $date === '') {
            if ($row && $row->source === self::SOURCE) {
                DB::table('user_status_histories')->where('id', $row->id)->delete();
            }
            return;
        }

        // Fechas incoherentes no producen transiciones documentadas.
        // El formulario debe validarlas antes de guardar.
        if ($memberAt !== null && $date > substr($memberAt, 0, 10)) {
            if ($row && $row->source === self::SOURCE) {
                DB::table('user_status_histories')->where('id', $row->id)->delete();
            }
            return;
        }

        $from = DB::table('status')->whereRaw('UPPER(TRIM(name)) = ?', ['USUARIO'])->value('id');
        $to = DB::table('status')->whereRaw('UPPER(TRIM(name)) = ?', ['RECLUTA'])->value('id');
        if (! $from || ! $to) {
            return;
        }

        // Si ya hay una transicion real del mismo dia, no la duplicamos.
        $actualExists = DB::table('user_status_histories')
            ->where('user_id', $userId)
            ->where('from_status_id', $from)
            ->where('to_status_id', $to)
            ->whereDate('changed_at', $date)
            ->where('source', '!=', self::SOURCE)
            ->exists();

        if ($actualExists) {
            if ($row && $row->source === self::SOURCE) {
                DB::table('user_status_histories')->where('id', $row->id)->delete();
            }
            return;
        }

        $values = [
            'from_status_id' => $from,
            'to_status_id' => $to,
            'changed_at' => $date . ' 00:00:00',
            // No atribuimos el ingreso de hace anos al administrador que lo documenta hoy.
            'changed_by_user_id' => null,
            'note' => 'Primer ingreso como recluta documentado.',
            'updated_at' => now(),
        ];

        if ($row) {
            if ($row->source === self::SOURCE) {
                DB::table('user_status_histories')->where('id', $row->id)->update($values);
            }
            return;
        }

        DB::table('user_status_histories')->insertOrIgnore([
            'user_id' => $userId,
            'source' => self::SOURCE,
            'source_hash' => $hash,
            'retutored_by' => null,
            'created_at' => now(),
            ...$values,
        ]);
    }
}
