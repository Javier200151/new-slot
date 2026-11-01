<?php

namespace App\Notifications;

use App\Models\ClipYearContest;
use Illuminate\Notifications\Notification;

class ClipYearPhaseNotification extends Notification
{
    public function __construct(public ClipYearContest $contest, public string $phase) {}

    public function via(object $notifiable): array { return ['database']; }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'clip_year_phase',
            'contest_id' => $this->contest->id,
            'year' => $this->contest->year,
            'phase' => $this->phase,
            'title' => $this->phase === 'nominations' ? 'Presenta tu clip del año' : 'Vota el clip del año',
            'message' => $this->phase === 'nominations'
                ? 'Ya puedes presentar tu clip para el concurso.'
                : 'Ya puedes votar los clips nominados.',
        ];
    }
}
