<?php

namespace App\Support;

use App\Models\User;
use App\Models\NotificationRule;
use Illuminate\Support\Facades\Schema;

final class ChangelogAccess
{
    public static function canView(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if (in_array(strtoupper(trim((string) $user->status?->name)), ['RECLUTA', 'ACTIVO', 'RESERVA'], true)) {
            return true;
        }

        // Los nuevos destinatarios configurados también deben poder abrir
        // el changelog desde el aviso, aunque su estado no sea uno habitual.
        if (! Schema::hasColumn('notification_rules', 'recipient_status_ids')) {
            return false;
        }

        $statusIds = NotificationRule::query()->find('changelog_published')?->recipient_status_ids ?? [];
        return in_array((int) $user->status_id, array_map('intval', $statusIds), true);
    }
}
