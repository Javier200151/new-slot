<?php

namespace App\Services;

use App\Models\ChangelogEntry;
use App\Models\NotificationRule;
use App\Models\Status;
use App\Models\User;
use App\Notifications\ChangelogPublishedNotification;
use App\Support\NotificationRulePolicy;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/** Envío idempotente al centro de avisos; no utiliza correo ni la cola de PBO. */
final class ChangelogNotificationService
{
    public function notifyEntry(int $entryId): int
    {
        $entry = ChangelogEntry::query()->find($entryId);

        if (! $entry || ! $entry->is_published || $entry->notified_at
            || ! $entry->published_at || $entry->published_at->isFuture()) {
            return 0;
        }

        // Los avisos desactivados se consideran procesados para no enviar
        // publicaciones históricas si se activa la regla más adelante.
        if (! NotificationRulePolicy::allows('changelog_published', $entry->published_at)) {
            $this->markNotified($entryId);
            return 0;
        }

        $rule = NotificationRule::query()->find('changelog_published');
        $statusIds = $rule?->recipient_status_ids;
        if (! is_array($statusIds)) {
            $statusIds = Status::query()->whereIn('name', ['ACTIVO', 'RESERVA'])->pluck('id')->all();
        }
        $statusIds = array_values(array_unique(array_filter(array_map('intval', $statusIds))));

        if ($statusIds === []) {
            $this->markNotified($entryId);
            return 0;
        }

        $notification = new ChangelogPublishedNotification($entry);
        $now = now();
        $count = 0;

        // Identificador estable por destinatario/entrada: si se interrumpe
        // el envío, al reintentarlo no se duplican las notificaciones previas.
        User::query()->whereIn('status_id', $statusIds)->select('id', 'status_id')
            ->chunkById(200, function ($users) use ($notification, $entryId, $now, &$count): void {
                $records = [];
                foreach ($users as $user) {
                    $records[] = [
                        'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, 'squadalpha-changelog-' . $entryId . '-user-' . $user->id)->toString(),
                        'type' => ChangelogPublishedNotification::class,
                        'notifiable_type' => $user->getMorphClass(),
                        'notifiable_id' => $user->id,
                        'data' => json_encode($notification->toDatabase($user), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                        'read_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                $count += DB::table('notifications')->insertOrIgnore($records);
            });

        $this->markNotified($entryId);
        return $count;
    }

    /** Para changelogs con fecha programada; solo busca entradas pendientes. */
    public function notifyDue(): int
    {
        $total = 0;
        ChangelogEntry::query()
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('notified_at')
            ->select('id')
            ->chunkById(50, function ($entries) use (&$total): void {
                foreach ($entries as $entry) {
                    $total += $this->notifyEntry((int) $entry->id);
                }
            });
        return $total;
    }

    private function markNotified(int $entryId): void
    {
        DB::table('changelog_entries')->where('id', $entryId)
            ->whereNull('notified_at')->update(['notified_at' => now()]);
    }
}
