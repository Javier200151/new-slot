<?php

namespace App\Console\Commands;

use App\Models\Streamer;
use App\Services\AutomaticLiveStreamService;
use Illuminate\Console\Command;

class CheckAutomaticStreams extends Command
{
    protected $signature = 'streams:check
        {--refresh : Ignora la caché de detección automática y consulta de nuevo las plataformas}';

    protected $description = 'Comprueba la detección automática de directos de Twitch y YouTube';

    public function handle(AutomaticLiveStreamService $automatic): int
    {
        $streamers = Streamer::query()
            ->where('enable', true)
            ->with('user')
            ->orderBy('id')
            ->get();

        $this->info('Detección automática: ' . ($automatic->enabled() ? 'ACTIVA' : 'DESACTIVADA'));
        $this->line('Twitch Client ID: ' . (filled(config('services.twitch.client_id')) ? 'configurado' : 'NO configurado'));
        $this->line('Twitch Client Secret: ' . (filled(config('services.twitch.client_secret')) ? 'configurado' : 'NO configurado'));
        $this->newLine();

        if ($streamers->isEmpty()) {
            $this->warn('No hay streamers habilitados en NewSlot.');

            return self::SUCCESS;
        }

        $live = $automatic->discover(
            $streamers,
            (bool) $this->option('refresh'),
        );

        $rows = $streamers->map(function (Streamer $streamer) use ($live): array {
            $detected = $live
                ->where('streamer_id', $streamer->id)
                ->pluck('platform')
                ->map(fn ($platform): string => strtoupper((string) $platform))
                ->implode(', ');

            return [
                $streamer->user?->nick ?? ('#' . $streamer->id),
                filled($streamer->twitch_channel) ? 'Sí' : '—',
                filled($streamer->youtube_channel) ? 'Sí' : '—',
                $detected ?: 'Offline',
            ];
        })->all();

        $this->table(
            ['Streamer', 'Twitch', 'YouTube', 'Detectado ahora'],
            $rows,
        );

        if ($streamers->contains(fn (Streamer $streamer): bool => filled($streamer->twitch_channel))
            && (! filled(config('services.twitch.client_id')) || ! filled(config('services.twitch.client_secret')))) {
            $this->newLine();
            $this->warn('Twitch no puede comprobarse automáticamente hasta configurar TWITCH_CLIENT_ID y TWITCH_CLIENT_SECRET.');
        }

        $this->newLine();
        $this->info($live->isEmpty()
            ? 'No se ha detectado ningún directo activo.'
            : 'Directos detectados: ' . $live->count());

        return self::SUCCESS;
    }
}
