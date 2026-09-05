<?php

namespace App\Services;

use App\Models\CampaignAar;
use App\Models\Event;
use App\Models\Metopa;
use App\Models\User;
use App\Notifications\CampaignAarPublishedNotification;
use App\Notifications\EventPublishedNotification;
use App\Notifications\MetopaAwardedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CommunityNotificationService
{
    public function eventPublished(
        Event $event,
    ): void {
        /*
         * Evita mandar dos veces la misma publicación.
         *
         * Por ejemplo:
         *
         * ACTIVO -> FINALIZADO -> ACTIVO
         */
        $alreadySent = DatabaseNotification::query()
            ->where(
                'type',
                EventPublishedNotification::class,
            )
            ->where(
                'data->event_id',
                $event->id,
            )
            ->exists();

        if ($alreadySent) {
            return;
        }

        User::query()
            ->select('id')
            ->chunkById(
                200,
                function ($users) use ($event): void {
                    Notification::send(
                        $users,
                        new EventPublishedNotification(
                            $event
                        ),
                    );
                }
            );
    }


    public function campaignAarPublished(
        CampaignAar $aar,
    ): void {
        $alreadySent = DatabaseNotification::query()
            ->where(
                'type',
                CampaignAarPublishedNotification::class,
            )
            ->where(
                'data->aar_id',
                $aar->id,
            )
            ->exists();

        if ($alreadySent) {
            return;
        }

        /*
         * "Miembro" en NewSlot corresponde al estado ACTIVO.
         * No notificamos borradores: este método se llama únicamente en la
         * primera transición del AAR a publicado.
         */
        User::query()
            ->select('users.id')
            ->whereHas(
                'status',
                fn ($query) => $query->where('name', 'ACTIVO'),
            )
            ->chunkById(
                200,
                function ($users) use ($aar): void {
                    Notification::send(
                        $users,
                        new CampaignAarPublishedNotification($aar),
                    );
                },
                column: 'users.id',
                alias: 'id',
            );
    }

    public function metopaAwarded(
        User $awardedUser,
        Metopa $metopa,
    ): void {
        Cache::lock(
            'newslot:metopa-notification:' . $metopa->id,
            10,
        )->block(5, function () use ($awardedUser, $metopa): void {
            $recent = DatabaseNotification::query()
                ->where('type', MetopaAwardedNotification::class)
                ->where('data->metopa_id', $metopa->id)
                ->where('updated_at', '>=', now()->subMinutes(10))
                ->latest('updated_at')
                ->first();

            $batchId = is_string($recent?->data['batch_id'] ?? null)
                ? $recent->data['batch_id']
                : null;

            if ($recent && filled($batchId)) {
                $awardedUsers = collect($recent->data['awarded_users'] ?? [])
                    ->filter(fn ($user): bool => is_array($user))
                    ->map(fn (array $user): array => [
                        'id' => (int) ($user['id'] ?? 0),
                        'nick' => (string) ($user['nick'] ?? ''),
                    ])
                    ->filter(
                        fn (array $user): bool =>
                            $user['id'] > 0 && $user['nick'] !== '',
                    );

                if ($awardedUsers->isEmpty()) {
                    $legacyId = (int) ($recent->data['awarded_user_id'] ?? 0);
                    $legacyNick = trim((string) ($recent->data['awarded_user_nick'] ?? ''));

                    if ($legacyId > 0 && $legacyNick !== '') {
                        $awardedUsers->push([
                            'id' => $legacyId,
                            'nick' => $legacyNick,
                        ]);
                    }
                }

                $awardedUsers->push([
                    'id' => (int) $awardedUser->id,
                    'nick' => (string) $awardedUser->nick,
                ]);

                $awardedUsers = $awardedUsers
                    ->unique(fn (array $user): int => $user['id'])
                    ->values();

                $firstAwardedUser = $awardedUsers->first();
                $data = $recent->data;
                $data['awarded_users'] = $awardedUsers->all();
                $data['awarded_users_count'] = $awardedUsers->count();
                $data['awarded_user_id'] = $firstAwardedUser['id'] ?? $awardedUser->id;
                $data['awarded_user_nick'] = $firstAwardedUser['nick'] ?? $awardedUser->nick;

                DatabaseNotification::query()
                    ->where('type', MetopaAwardedNotification::class)
                    ->where('data->batch_id', $batchId)
                    ->update([
                        'data' => json_encode(
                            $data,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                        ),
                        // Sigue siendo una única fila de notificación, pero si
                        // el usuario ya la había leído, una nueva concesión del
                        // mismo lote debe volver a llamar su atención.
                        'read_at' => null,
                        'updated_at' => now(),
                    ]);

                return;
            }

            $batchId = (string) Str::uuid();
            $awardedUsers = [[
                'id' => (int) $awardedUser->id,
                'nick' => (string) $awardedUser->nick,
            ]];

            User::query()
                ->select('id')
                ->chunkById(
                    200,
                    function ($users) use (
                        $awardedUser,
                        $metopa,
                        $batchId,
                        $awardedUsers,
                    ): void {
                        Notification::send(
                            $users,
                            new MetopaAwardedNotification(
                                $awardedUser,
                                $metopa,
                                $batchId,
                                $awardedUsers,
                            ),
                        );
                    },
                );
        });
    }
}