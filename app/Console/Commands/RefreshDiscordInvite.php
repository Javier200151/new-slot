<?php

namespace App\Console\Commands;

use App\Models\HomepageSetting;
use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\DiscordService;
use Illuminate\Console\Command;
use Throwable;

class RefreshDiscordInvite extends Command
{
    protected $signature = 'discord:refresh-invite {--force : Regenera la invitación aunque aún no esté cerca de caducar}';

    protected $description = 'Renueva la invitación pública de Discord usada en el pie de página';

    public function handle(DiscordService $discord): int
    {
        $homepage = HomepageSetting::current();

        if (! $this->option('force') && ! $homepage->discord_invite_auto_refresh) {
            $this->info('Renovación automática de Discord desactivada.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && $homepage->discord_invite_expires_at
            && $homepage->discord_invite_expires_at->gt(now()->addDay())) {
            $this->info('La invitación de Discord todavía no necesita renovación.');

            return self::SUCCESS;
        }

        try {
            $result = $discord->refreshPublicInvite(MemberProcedureSetting::current(), $homepage);

            $this->info('Invitación de Discord renovada: ' . ($result['url'] ?? 'OK'));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
