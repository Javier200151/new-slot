<?php

namespace App\Console\Commands;

use App\Models\ClipYearContest;
use App\Models\User;
use App\Notifications\ClipYearPhaseNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class NotifyClipYearPhase extends Command
{
    protected $signature = 'clips:notify-year-phase';
    protected $description = 'Notifica las fases del Clip del año a los estados participantes';

    public function handle(): int
    {
        $contest = ClipYearContest::current();
        if (! $contest || ! in_array($contest->phase(), ['nominations', 'voting'], true)) {
            $this->info('No hay una fase de nominación o votación abierta.');
            return self::SUCCESS;
        }

        $phase = $contest->phase();
        $total = 0;
        User::query()->whereIn('status_id', $contest->allowedStatusIds())
            ->chunkById(200, function ($users) use ($contest, $phase, &$total): void {
                foreach ($users as $user) {
                    $alreadySent = DatabaseNotification::query()
                        ->where('type', ClipYearPhaseNotification::class)
                        ->where('notifiable_type', $user->getMorphClass())
                        ->where('notifiable_id', $user->id)
                        ->where('data->contest_id', $contest->id)
                        ->where('data->phase', $phase)
                        ->exists();
                    if ($alreadySent) continue;
                    $user->notify(new ClipYearPhaseNotification($contest, $phase));
                    $total++;
                }
            });
        if ($total > 0) {
            app(\App\Services\AuditLogger::class)->automatic(
                'avisos_clip_del_ano',
                ['contest_id' => $contest->id, 'phase' => $phase, 'recipients' => $total],
                $contest,
                'clips:notify-year-phase',
            );
        }
        $this->info("Avisos enviados: {$total}");
        return self::SUCCESS;
    }
}
