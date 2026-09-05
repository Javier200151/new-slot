<?php

namespace App\Notifications;

use App\Models\Metopa;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MetopaAwardedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, array{id:int,nick:string}>|null  $awardedUsers
     */
    public function __construct(
        public User $awardedUser,
        public Metopa $metopa,
        public ?string $batchId = null,
        public ?array $awardedUsers = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $awardedUsers = collect(
            $this->awardedUsers ?: [[
                'id' => (int) $this->awardedUser->id,
                'nick' => (string) $this->awardedUser->nick,
            ]],
        )
            ->filter(
                fn (array $user): bool =>
                    (int) ($user['id'] ?? 0) > 0
                    && filled($user['nick'] ?? null),
            )
            ->unique(fn (array $user): int => (int) $user['id'])
            ->values()
            ->all();

        $firstAwardedUser = $awardedUsers[0] ?? [
            'id' => (int) $this->awardedUser->id,
            'nick' => (string) $this->awardedUser->nick,
        ];

        return [
            'type' => 'metopa_awarded',
            'batch_id' => $this->batchId,
            'metopa_id' => $this->metopa->id,
            'metopa_name' => $this->metopa->name,
            // Campos legacy: se conservan para notificaciones antiguas y
            // cualquier consumidor que espere todavía un único destinatario.
            'awarded_user_id' => $firstAwardedUser['id'],
            'awarded_user_nick' => $firstAwardedUser['nick'],
            'awarded_users' => $awardedUsers,
            'awarded_users_count' => count($awardedUsers),
        ];
    }
}
