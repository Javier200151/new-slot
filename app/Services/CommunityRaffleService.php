<?php

namespace App\Services;

use App\Models\CommunityRaffle;
use App\Models\CommunityRaffleDraw;
use App\Models\CommunityRafflePrize;
use App\Models\User;
use App\Notifications\CommunityRafflePrizeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CommunityRaffleService
{
    public function canManage(?User $user): bool
    {
        return $user !== null && ($user->hasRole('admin') || $user->can('raffles.manage'));
    }

    /**
     * Solo el gestor y las personas inscritas pueden consultar el sorteo.
     * La inscripción se conserva tras ganar y al cerrar el sorteo.
     */
    public function canView(CommunityRaffle $raffle, ?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->canManage($user)) {
            return true;
        }

        return in_array($raffle->status, ['active', 'closed'], true)
            && $raffle->entries()->where('user_id', $user->id)->exists();
    }

    public function featured(?User $user = null): ?CommunityRaffle
    {
        $user ??= auth()->user();
        if ($user === null || ! Schema::hasTable('community_raffles')) {
            return null;
        }

        return CommunityRaffle::query()
            ->where('show_in_sidebar', true)
            ->whereIn('status', ['active', 'closed'])
            ->when(! $this->canManage($user), fn ($query) => $query->whereHas(
                'entries', fn ($entries) => $entries->where('user_id', $user->id)
            ))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();
    }

    public function activate(CommunityRaffle $raffle): void
    {
        DB::transaction(function () use ($raffle): void {
            $locked = CommunityRaffle::query()->lockForUpdate()->findOrFail($raffle->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['sorteo' => 'Solo puede activarse un sorteo en preparación.']);
            }
            if (! $locked->prizes()->where('quantity', '>', 0)->exists()) {
                throw ValidationException::withMessages(['sorteo' => 'Añade al menos un premio antes de abrir el sorteo.']);
            }
            if (CommunityRaffle::query()->where('active_key', 1)->exists()) {
                throw ValidationException::withMessages(['sorteo' => 'Ya hay otro sorteo activo. Ciérralo antes de abrir uno nuevo.']);
            }
            $locked->update([
                'status' => 'active', 'active_key' => 1, 'opened_at' => now(),
                'show_in_sidebar' => true,
            ]);
        });
    }

    public function close(CommunityRaffle $raffle): void
    {
        DB::transaction(function () use ($raffle): void {
            $locked = CommunityRaffle::query()->lockForUpdate()->findOrFail($raffle->id);
            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['sorteo' => 'Este sorteo no está activo.']);
            }
            $this->finalizePendingLocked($locked);
            if ($locked->draws()->where('status', 'spinning')->exists()) {
                throw ValidationException::withMessages(['sorteo' => 'Espera a que termine la tirada actual.']);
            }
            $locked->update(['status' => 'closed', 'active_key' => null, 'closed_at' => now()]);
        });
    }

    /** @param array<int,int> $userIds */
    public function saveParticipants(CommunityRaffle $raffle, array $userIds): void
    {
        DB::transaction(function () use ($raffle, $userIds): void {
            $locked = CommunityRaffle::query()->lockForUpdate()->findOrFail($raffle->id);
            $this->assertActive($locked);
            $this->finalizePendingLocked($locked);
            if ($locked->draws()->where('status', 'spinning')->exists()) {
                throw ValidationException::withMessages(['participantes' => 'No puedes cambiar participantes durante una tirada.']);
            }
            $ids = array_values(array_unique(array_map('intval', $userIds)));
            $validIds = User::query()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($validIds) !== count($ids)) {
                throw ValidationException::withMessages(['participantes' => 'Hay usuarios que no están disponibles para el sorteo.']);
            }
            // La elección se guarda como lista explícita, incluso al quitar a alguien
            // después de haber seleccionado un estado completo.
            DB::table('community_raffle_entries')
                ->where('raffle_id', $locked->id)
                ->when($ids !== [], fn ($q) => $q->whereNotIn('user_id', $ids))
                ->delete();
            if ($ids === []) {
                $locked->update(['participants_updated_at' => now(), 'participants_updated_by' => auth()->id()]);
                return;
            }
            $time = now();
            foreach (array_chunk($ids, 250) as $chunk) {
                DB::table('community_raffle_entries')->insertOrIgnore(array_map(
                    fn ($id) => ['raffle_id' => $locked->id, 'user_id' => $id, 'created_at' => $time, 'updated_at' => $time],
                    $chunk,
                ));
            }
            // Un único cambio auditable por guardar la selección, no una fila
            // en la auditoría por cada miembro añadido o retirado.
            $locked->update(['participants_updated_at' => now(), 'participants_updated_by' => auth()->id()]);
        });
    }

    public function spin(CommunityRaffle $raffle, int $prizeId, User $actor, bool $allowClosedRepeat = false, ?int $excludeWinnerId = null): CommunityRaffleDraw
    {
        return DB::transaction(function () use ($raffle, $prizeId, $actor, $allowClosedRepeat, $excludeWinnerId): CommunityRaffleDraw {
            $locked = CommunityRaffle::query()->lockForUpdate()->findOrFail($raffle->id);
            if ($allowClosedRepeat) {
                if (! in_array($locked->status, ['active', 'closed'], true)) {
                    throw ValidationException::withMessages(['sorteo' => 'Este sorteo no permite repetir tiradas.']);
                }
            } else {
                $this->assertActive($locked);
            }
            $this->finalizePendingLocked($locked);
            if ($locked->draws()->where('status', 'spinning')->exists()) {
                throw ValidationException::withMessages(['ruleta' => 'Ya hay una tirada en curso.']);
            }
            $prize = CommunityRafflePrize::query()->where('raffle_id', $locked->id)->findOrFail($prizeId);
            $used = $locked->draws()->where('prize_id', $prize->id)->whereIn('status', ['completed', 'spinning'])->count();
            if ($used >= $prize->quantity) {
                throw ValidationException::withMessages(['premio' => 'No quedan unidades disponibles de este premio.']);
            }
            $snapshot = $this->eligibleUsers($locked);
            if ($snapshot === []) {
                throw ValidationException::withMessages(['participantes' => 'No hay participantes disponibles. Comprueba la selección o los ganadores anteriores.']);
            }
            if ($excludeWinnerId !== null && count($snapshot) > 1) {
                $snapshot = array_values(array_filter($snapshot, fn ($person) => $person['id'] !== $excludeWinnerId));
            }
            $winnerIndex = random_int(0, count($snapshot) - 1);
            $winner = $snapshot[$winnerIndex];
            $messages = collect($locked->winner_messages ?: [
                ['text' => '{nick}, el destino ha decidido que te llevas {premio}.'],
            ])->pluck('text')->filter(fn ($text) => filled($text))->values()->all();
            $phrase = $messages ? $messages[random_int(0, count($messages) - 1)] : '¡Enhorabuena, {nick}!';
            $phrase = str_replace(['{nick}', '{premio}'], [$winner['nick'], $prize->name], $phrase);
            $now = now();
            return $locked->draws()->create([
                'prize_id' => $prize->id,
                'winner_user_id' => $winner['id'],
                'winner_nick' => $winner['nick'],
                'prize_name' => $prize->name,
                'phrase' => $phrase,
                'status' => 'spinning',
                'winner_index' => $winnerIndex,
                'candidate_count' => count($snapshot),
                'wheel_snapshot' => $snapshot,
                'started_at' => $now,
                'ends_at' => $now->copy()->addSeconds(11),
                'created_by' => $actor->id,
            ]);
        });
    }

    public function reroll(CommunityRaffle $raffle, int $drawId, User $actor): CommunityRaffleDraw
    {
        return DB::transaction(function () use ($raffle, $drawId, $actor): CommunityRaffleDraw {
            $locked = CommunityRaffle::query()->lockForUpdate()->findOrFail($raffle->id);
            if (! in_array($locked->status, ['active', 'closed'], true)) {
                throw ValidationException::withMessages(['sorteo' => 'No se puede repetir una tirada de este sorteo.']);
            }
            $this->finalizePendingLocked($locked);
            if ($locked->draws()->where('status', 'spinning')->exists()) {
                throw ValidationException::withMessages(['ruleta' => 'Espera a que termine la tirada actual.']);
            }
            $draw = $locked->draws()->where('status', 'completed')->lockForUpdate()->findOrFail($drawId);
            $draw->update(['status' => 'void', 'voided_at' => now()]);
            // La repetición y la anulación son atómicas: si no quedan candidatos,
            // se conserva el premio original y no se envían avisos.
            $replacement = $this->spin($locked, (int) $draw->prize_id, $actor, true, $draw->winner_user_id ? (int) $draw->winner_user_id : null);
            $draw->winner?->notify(new CommunityRafflePrizeNotification($draw, cancelled: true));
            return $replacement;
        });
    }

    public function changePrize(CommunityRaffle $raffle, int $drawId, int $prizeId): void
    {
        DB::transaction(function () use ($raffle, $drawId, $prizeId): void {
            $locked = CommunityRaffle::query()->lockForUpdate()->findOrFail($raffle->id);
            if (! in_array($locked->status, ['active', 'closed'], true)) {
                throw ValidationException::withMessages(['sorteo' => 'Este sorteo no permite cambiar premios.']);
            }
            $this->finalizePendingLocked($locked);
            if ($locked->draws()->where('status', 'spinning')->exists()) {
                throw ValidationException::withMessages(['ruleta' => 'Espera a que termine la tirada actual.']);
            }
            $draw = $locked->draws()->where('status', 'completed')->lockForUpdate()->findOrFail($drawId);
            $prize = $locked->prizes()->whereKey($prizeId)->lockForUpdate()->firstOrFail();
            if ((int) $draw->prize_id === (int) $prize->id) {
                throw ValidationException::withMessages(['premio' => 'El ganador ya tiene ese premio.']);
            }
            $used = $locked->draws()->where('prize_id', $prize->id)->whereIn('status', ['completed', 'spinning'])->count();
            if ($used >= $prize->quantity) {
                throw ValidationException::withMessages(['premio' => 'No quedan unidades disponibles de ese premio.']);
            }
            $oldName = $draw->prize_name;
            $draw->update([
                'prize_id' => $prize->id,
                'prize_name' => $prize->name,
                'phrase' => str_replace($oldName, $prize->name, $draw->phrase ?? ''),
            ]);
            $draw->winner?->notify(new CommunityRafflePrizeNotification($draw, changed: true));
        });
    }

    public function settle(CommunityRaffle $raffle): void
    {
        // Solo entra en una transacción de escritura cuando realmente hay
        // una tirada vencida. Las visitas normales son consultas sin cambios.
        if (! $raffle->draws()->where('status', 'spinning')->where('ends_at', '<=', now())->exists()) {
            return;
        }
        DB::transaction(function () use ($raffle): void {
            $locked = CommunityRaffle::query()->lockForUpdate()->findOrFail($raffle->id);
            $this->finalizePendingLocked($locked);
        });
    }

    public function settleDue(): void
    {
        if (! Schema::hasTable('community_raffles')) {
            return;
        }
        CommunityRaffle::query()
            ->whereHas('draws', fn ($query) => $query->where('status', 'spinning')->where('ends_at', '<=', now()))
            ->chunkById(50, function ($raffles): void {
                foreach ($raffles as $raffle) {
                    $this->settle($raffle);
                }
            });
    }

    public function state(CommunityRaffle $raffle): array
    {
        $this->settle($raffle);
        $raffle->refresh();
        $prizes = $raffle->prizes()->get();
        $draws = $raffle->draws()->latest('id')->get();
        $live = $draws->first(fn ($draw) => $draw->status === 'spinning');
        $latest = $live ?? $draws->first(fn ($draw) => $draw->status === 'completed');
        $winners = $draws->where('status', 'completed')->values();
        $eligible = $raffle->status === 'active' ? $this->eligibleUsers($raffle) : [];
        // Conservar el círculo y la posición real de la última tirada.
        // El siguiente giro genera una fotografía nueva de los participantes.
        $wheel = $latest ? ($latest->wheel_snapshot ?? []) : $eligible;

        return [
            'id' => $raffle->id,
            'server_now_ms' => now()->getTimestampMs(),
            'title' => $raffle->title,
            'description' => $raffle->description,
            'claim_message' => $raffle->claim_message,
            'status' => $raffle->status,
            'can_manage' => $this->canManage(auth()->user()),
            'prizes' => $prizes->map(function ($prize) use ($draws) {
                $used = $draws->where('prize_id', $prize->id)->whereIn('status', ['completed', 'spinning'])->count();
                return [
                    'id' => $prize->id,
                    'name' => $prize->name,
                    'description' => $prize->description,
                    'image_url' => $prize->image_path && preg_match('~^raffle-prizes/[A-Za-z0-9._-]+$~', $prize->image_path)
                        && Storage::disk('public')->exists($prize->image_path)
                        ? route('raffles.image', ['filename' => basename($prize->image_path)])
                        : null,
                    'quantity' => $prize->quantity,
                    'remaining' => max(0, $prize->quantity - $used),
                ];
            })->values()->all(),
            'wheel' => $wheel,
            'eligible_count' => count($eligible),
            'spin' => $latest ? [
                'id' => $latest->id,
                'status' => $latest->status,
                'winner_index' => $latest->winner_index,
                'winner_nick' => $latest->winner_nick,
                'prize_name' => $latest->prize_name,
                'phrase' => $latest->status === 'completed' ? $latest->phrase : null,
                'started_at' => $latest->started_at?->getTimestampMs(),
                'ends_at' => $latest->ends_at?->getTimestampMs(),
                'wheel' => $latest->wheel_snapshot,
            ] : null,
            'winners' => $winners->map(fn ($draw) => [
                'id' => $draw->id,
                'nick' => $draw->winner_nick,
                'prize' => $draw->prize_name,
                'phrase' => $draw->phrase,
                'prize_id' => $draw->prize_id,
            ])->all(),
            'participants_count' => $raffle->entries()->count(),
        ];
    }

    /** @return array<int, array{id: int, nick: string}> */
    private function eligibleUsers(CommunityRaffle $raffle): array
    {
        $previousWinners = $raffle->draws()
            ->whereIn('status', ['completed', 'spinning'])
            ->whereNotNull('winner_user_id')
            ->pluck('winner_user_id')->all();
        return User::query()
            ->join('community_raffle_entries as entries', 'entries.user_id', '=', 'users.id')
            ->where('entries.raffle_id', $raffle->id)
            ->when($previousWinners !== [], fn ($q) => $q->whereNotIn('users.id', $previousWinners))
            ->orderBy('users.nick')->orderBy('users.id')
            ->get(['users.id', 'users.nick'])
            ->map(fn ($user) => ['id' => (int) $user->id, 'nick' => (string) $user->nick])
            ->all();
    }

    private function finalizePendingLocked(CommunityRaffle $raffle): void
    {
        foreach ($raffle->draws()->where('status', 'spinning')->where('ends_at', '<=', now())->lockForUpdate()->get() as $draw) {
            $draw->update(['status' => 'completed', 'completed_at' => now()]);
            $draw->winner?->notify(new CommunityRafflePrizeNotification($draw));
        }
    }

    private function assertActive(CommunityRaffle $raffle): void
    {
        if ($raffle->status !== 'active') {
            throw ValidationException::withMessages(['sorteo' => 'Este sorteo no está abierto.']);
        }
    }
}
