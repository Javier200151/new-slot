<?php

namespace App\Filament\Widgets;

use App\Services\ActiveMemberInactivityService;
use Filament\Widgets\Widget;

class ActiveMemberInactivityWidget extends Widget
{
    protected string $view = 'filament.widgets.active-member-inactivity';

    protected int | string | array $columnSpan = 'full';

    public string $period = 'top5';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('users.view');
    }

    public function results(): array
    {
        return app(ActiveMemberInactivityService::class)->list($this->period);
    }
}
