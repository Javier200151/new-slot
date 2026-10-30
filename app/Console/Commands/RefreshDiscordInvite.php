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

        if (! $this->option('force') && ! $discord->publicInviteNeedsRefresh($homepage)) {
            $this->info('La invitación de Discord no necesita actualización.');

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
