<?php

namespace App\Services;

use App\Models\CommunityClip;
use App\Models\Streamer;
use App\Support\StreamingChannelParser;
use App\Support\ClipImportWindow;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TwitchClipImportService
{
    /** @return array{streamers:int,created:int,skipped:int,errors:int} */
    public function run(): array
    {
        $result = ['streamers' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0];
        $streamers = Streamer::query()->where('enable', true)->where('auto_import_clips', true)->with('user')->get();
        if ($streamers->isEmpty()) return $result;
        $clips = app(CommunityClipService::class);
        $headers = $clips->twitchClient();
        if (! $headers) {
            Log::warning('Importación de clips: faltan credenciales de Twitch o no se ha conseguido el acceso.');
            $result['errors']++;
            return $result;
        }

        foreach ($streamers as $streamer) {
            $result['streamers']++;
            try {
                $id = trim((string) $streamer->twitch_user_id);
                if ($id === '') {
                    $login = StreamingChannelParser::twitchLogin($streamer->twitch_channel);
                    if (! $login) continue;
                    $user = Http::timeout(8)->withHeaders($headers)->get('https://api.twitch.tv/helix/users', ['login' => $login]);
                    if (! $user->successful()) throw new \RuntimeException('No se pudo consultar el canal Twitch');
                    $id = (string) $user->json('data.0.id', '');
                    if ($id === '') continue;
                    $streamer->forceFill(['twitch_user_id' => $id])->save();
                }
                $cursor = null;
                $windowEnd = now()->utc();
                $windowStart = ClipImportWindow::start($streamer);
                // Twitch limits Clip results to roughly 1,000 per query window.
                for ($page = 0; $page < 10; $page++) {
                    $params = [
                        'broadcaster_id' => $id, 'first' => 100,
                        'started_at' => $windowStart->toIso8601String(),
                        'ended_at' => $windowEnd->toIso8601String(),
                    ];
                    if ($cursor) $params['after'] = $cursor;
                    $response = Http::timeout(12)->withHeaders($headers)->get('https://api.twitch.tv/helix/clips', $params);
                    if (! $response->successful()) throw new \RuntimeException('Twitch devolvió HTTP ' . $response->status());
                    $data = $response->json('data', []);
                    foreach ($data as $item) {
                        if (! is_array($item) || ! preg_match('/^[A-Za-z0-9_-]{5,150}$/', (string) ($item['id'] ?? ''))) continue;
                        if ($streamer->clip_import_mode === 'arma' && ! $this->allowedGame((string) ($item['game_id'] ?? ''), $headers)) {
                            $result['skipped']++; continue;
                        }
                        $exists = CommunityClip::withTrashed()->where('platform', 'twitch')->where('external_id', $item['id'])->exists();
                        if ($exists) { $result['skipped']++; continue; }
                        CommunityClip::create([
                            'user_id' => $streamer->user_id,
                            'streamer_id' => $streamer->id,
                            'platform' => 'twitch',
                            'external_id' => $item['id'],
                            'url' => 'https://clips.twitch.tv/' . $item['id'],
                            'source' => 'automatic',
                            'title' => (string) ($item['title'] ?? 'Clip de Twitch'),
                            'thumbnail_url' => $item['thumbnail_url'] ?? null,
                            'broadcaster_name' => $item['broadcaster_name'] ?? null,
                            'game_id' => $item['game_id'] ?? null,
                            'duration_seconds' => (int) ceil((float) ($item['duration'] ?? 0)) ?: null,
                            'clip_created_at' => Carbon::parse($item['created_at'] ?? now()),
                            'is_visible' => true,
                        ]);
                        $result['created']++;
                    }
                    $cursor = $response->json('pagination.cursor');
                    if (! $cursor || count($data) < 100) break;
                }
            } catch (Throwable $e) {
                $result['errors']++;
                Log::warning('No se pudieron importar clips del streamer', ['streamer_id' => $streamer->id, 'error' => $e->getMessage()]);
            }
        }
        return $result;
    }

    private array $armaIds = [];

    private function allowedGame(string $gameId, array $headers): bool
    {
        if ($gameId === '') return false;
        if (! $this->armaIds) {
            foreach (['Arma 3', 'Arma Reforger'] as $name) {
                $response = Http::timeout(8)->withHeaders($headers)->get('https://api.twitch.tv/helix/games', ['name' => $name]);
                if ($response->successful() && $response->json('data.0.id')) $this->armaIds[] = (string) $response->json('data.0.id');
            }
            if (! $this->armaIds) $this->armaIds = ['__no_match__'];
        }
        return in_array($gameId, $this->armaIds, true);
    }
}
