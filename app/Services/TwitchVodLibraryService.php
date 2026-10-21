<?php

namespace App\Services;

use App\Models\Stream;
use App\Models\Streamer;
use App\Models\TwitchCategorySetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Conserva emisiones de Twitch aprobadas y sus grabaciones para moderarlas desde Filament. */
class TwitchVodLibraryService
{
    public function rememberLive(Streamer $streamer, array $live): void
    {
        $streamId = trim((string) ($live['id'] ?? ''));
        if ($streamId === '') {
            return;
        }

        try {
            $started = filled($live['started_at'] ?? null)
                ? Carbon::parse($live['started_at']) : now();

            $record = Stream::query()->firstOrNew(['twitch_stream_id' => $streamId]);
            if (! $record->exists) {
                $record->fill([
                    'streamer_id' => $streamer->id,
                    'platform' => 'twitch',
                    'stream_url' => 'https://www.twitch.tv/' . (string) ($live['user_login'] ?? ''),
                    'title' => (string) ($live['title'] ?? 'Directo de Twitch'),
                    'enabled' => false, // No es una publicación manual.
                    'auto_detected' => true,
                    'is_vod' => false,
                    'visible_on_home' => true,
                    'started_at' => $started,
                ]);
                $event = app(TwitchArmaCategoryService::class)->matchingCalendarEvent($started, now());
                if ($event && ! Stream::query()
                    ->where('event_id', $event->id)
                    ->where('streamer_id', $streamer->id)
                    ->exists()) {
                    $record->event_id = $event->id;
                }
            }

            // Evita escribir en BD cada vez que un visitante refresca /directos.
            if (! $record->exists || ! $record->twitch_last_seen_at || $record->twitch_last_seen_at->lt(now()->subMinutes(5))) {
                $record->twitch_last_seen_at = now();
                $record->save();
            }
        } catch (Throwable $e) {
            Log::warning('No se ha podido registrar el directo de Twitch', [
                'streamer_id' => $streamer->id,
                'stream_id' => $streamId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** La emisión ya fue aceptada mientras estaba en directo. */
    public function wasApprovedLive(Streamer $streamer, array $video): bool
    {
        $interval = $this->videoInterval($video);
        if (! $interval) {
            return false;
        }
        [$start, $end] = $interval;
        // Twitch publica el identificador original de emisión en el propio VOD.
        $streamId = trim((string) ($video['stream_id'] ?? ''));
        if ($streamId !== '') {
            return Stream::query()
                ->where('streamer_id', $streamer->id)
                ->where('twitch_stream_id', $streamId)
                ->where('auto_detected', true)
                ->exists();
        }

        return Stream::query()
            ->where('streamer_id', $streamer->id)
            ->where('platform', 'twitch')
            ->where('auto_detected', true)
            ->whereNotNull('twitch_stream_id')
            ->where('started_at', '<=', $end)
            ->where('started_at', '>=', $start->copy()->subMinutes(30))
            ->where(function ($query) use ($start): void {
                $query->whereNull('twitch_last_seen_at')
                    ->orWhere('twitch_last_seen_at', '>=', $start->copy()->subMinutes(30));
            })
            ->exists();
    }

    /** Una emisión puesta a mano mantiene su aprobación aunque cambie el juego. */
    public function wasManuallyPublished(Streamer $streamer, array $video): bool
    {
        $interval = $this->videoInterval($video);
        if (! $interval) {
            return false;
        }
        [$start, $end] = $interval;

        return Stream::query()
            ->where('streamer_id', $streamer->id)
            ->where('platform', 'twitch')
            ->where('auto_detected', false)
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $end)
            ->where('started_at', '>=', $start->copy()->subMinutes(30))
            ->where(function ($query) use ($start): void {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $start);
            })
            ->exists();
    }

    public function saveVod(Streamer $streamer, array $video): void
    {
        $id = trim((string) ($video['id'] ?? ''));
        $interval = $this->videoInterval($video);
        if ($id === '' || ! $interval) {
            return;
        }

        try {
            [$started, $ended] = $interval;
            $url = 'https://www.twitch.tv/videos/' . $id;
            $record = Stream::query()->where('twitch_video_id', $id)->first();
            if (! $record) {
                $record = Stream::query()->where('platform', 'twitch')
                    ->where('streamer_id', $streamer->id)
                    ->where('stream_url', $url)
                    ->first();
            }
            if (! $record) {
                $streamId = trim((string) ($video['stream_id'] ?? ''));
                if ($streamId !== '') {
                    $record = Stream::query()
                        ->where('streamer_id', $streamer->id)
                        ->where('twitch_stream_id', $streamId)
                        ->where('auto_detected', true)
                        ->first();
                }
            }
            if (! $record && ! filled($video['stream_id'] ?? null)) {
                // Para archivos antiguos sin stream_id, aproximamos por horario.
                $record = Stream::query()
                    ->where('streamer_id', $streamer->id)
                    ->where('auto_detected', true)
                    ->whereNotNull('twitch_stream_id')
                    ->where('is_vod', false)
                    ->whereBetween('started_at', [$started->copy()->subMinutes(30), $ended])
                    ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, started_at, ?))', [$started->toDateTimeString()])
                    ->first();
            }
            if (! $record) {
                $record = new Stream();
                $record->auto_detected = true;
                // Respeta las ocultaciones antiguas si hubo URLs en el filtro v03.
                $legacyHidden = TwitchCategorySetting::current()->excluded_vod_ids ?? [];
                $record->visible_on_home = ! in_array($id, $legacyHidden, true);
                $record->streamer_id = $streamer->id;
                $record->platform = 'twitch';
                $event = app(TwitchArmaCategoryService::class)->matchingCalendarEvent($started, $ended);
                if ($event && ! Stream::query()
                    ->where('event_id', $event->id)
                    ->where('streamer_id', $streamer->id)
                    ->exists()) {
                    $record->event_id = $event->id;
                }
            }

            // Nunca reactiva un VOD que el administrador haya ocultado.
            $record->fill([
                'is_vod' => true,
                'twitch_video_id' => $id,
                'stream_url' => $url,
                'title' => (string) ($video['title'] ?? 'VOD de Twitch'),
                'thumbnail_url' => trim((string) ($video['thumbnail_url'] ?? '')) ?: null,
                'started_at' => $started,
                'ended_at' => $ended,
                'published_at' => filled($video['published_at'] ?? null)
                    ? Carbon::parse($video['published_at']) : $started,
                'enabled' => false,
            ]);
            $record->save();
        } catch (Throwable $e) {
            Log::warning('No se ha podido guardar el VOD de Twitch', [
                'streamer_id' => $streamer->id,
                'vod_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return array{Carbon,Carbon}|null */
    private function videoInterval(array $video): ?array
    {
        if (! filled($video['created_at'] ?? $video['published_at'] ?? null)) {
            return null;
        }
        try {
            $start = Carbon::parse($video['created_at'] ?? $video['published_at']);
            $duration = (string) ($video['duration'] ?? '');
            if (! preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/i', $duration, $parts)) {
                return null;
            }
            $seconds = ((int) ($parts[1] ?? 0) * 3600)
                + ((int) ($parts[2] ?? 0) * 60) + (int) ($parts[3] ?? 0);
            if ($seconds <= 0) {
                return null;
            }
            return [$start, $start->copy()->addSeconds($seconds)];
        } catch (Throwable) {
            return null;
        }
    }
}
