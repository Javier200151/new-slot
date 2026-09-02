<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\BirthdayNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

class BirthdayNotificationService
{
    public function notifyToday(): int
    {
        $today = now('Europe/Madrid');
        $dateKey = $today->format('Y-m-d');
        $sent = 0;

        $birthdayUsers = User::query()
            ->with('status')
            ->whereNotNull('birth_at')
            ->whereMonth('birth_at', $today->month)
            ->whereDay('birth_at', $today->day)
            ->whereHas('status', fn ($query) => $query->whereIn('name', ['ACTIVO', 'RESERVA']))
            ->get();

        foreach ($birthdayUsers as $birthdayUser) {
            $alreadySent = DatabaseNotification::query()
                ->where('type', BirthdayNotification::class)
                ->where('data->birthday_user_id', $birthdayUser->id)
                ->where('data->birthday_date', $dateKey)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            User::query()
                ->select('id')
                ->chunkById(200, function ($users) use ($birthdayUser, $dateKey): void {
                    Notification::send(
                        $users,
                        new BirthdayNotification($birthdayUser, $dateKey),
                    );
                });

            $sent++;
        }

        return $sent;
    }

    public function isBirthdayToday(User $user): bool
    {
        if (! $user->birth_at) {
            return false;
        }

        $today = now('Europe/Madrid');

        return (int) $user->birth_at->month === (int) $today->month
            && (int) $user->birth_at->day === (int) $today->day;
    }
}
