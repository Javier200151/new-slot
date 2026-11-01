<?php

namespace App\Http\Controllers;

use App\Models\Stream;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use App\Services\HomepageVodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StreamerVodController extends Controller
{
    public function store(Request $request, HomepageVodService $vodService): RedirectResponse
    {
        $streamer = $request->user()?->streamer;
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('streamer-vods.create') || $streamer?->enable, 403);
        abort_unless($streamer, 403);
        $data = $request->validate(['url' => ['required', 'url', 'max:500'], 'title' => ['nullable', 'string', 'max:255'], 'event_id' => ['nullable', 'integer', 'exists:events,id,deleted_at,NULL']]);
        $url = trim($data['url']);
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $platform = null;
        $videoId = null;
        if (($parts['scheme'] ?? '') === 'https' && in_array($host, ['twitch.tv', 'www.twitch.tv', 'm.twitch.tv'], true) && preg_match('~^videos/(\d+)$~', $path, $m)) {
            $platform = 'twitch'; $videoId = $m[1]; $url = 'https://www.twitch.tv/videos/' . $videoId;
        } elseif (($parts['scheme'] ?? '') === 'https' && in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            if ($host === 'youtu.be' && preg_match('~^([A-Za-z0-9_-]{11})$~', $path, $m)) $videoId = $m[1];
            elseif (preg_match('~^(?:live|watch|shorts)/([A-Za-z0-9_-]{11})$~', $path, $m)) $videoId = $m[1];
            elseif ($path === 'watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                if (preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($query['v'] ?? ''))) $videoId = $query['v'];
            }
            if ($videoId) { $platform = 'youtube'; $url = 'https://www.youtube.com/watch?v=' . $videoId; }
        }
        if (! $platform) throw ValidationException::withMessages(['url' => 'Utiliza un enlace directo a un VOD de Twitch o a un vídeo de YouTube.']);
        // Los streamers pueden compartir VODs públicos de cualquier canal.
        // La propiedad para poder retirarlos corresponde al streamer que los añadió.

        $existing = Stream::query()->where('stream_url', $url)->first();
        if ($existing && (int) $existing->streamer_id !== (int) $streamer->id) {
            throw ValidationException::withMessages(['url' => 'Este VOD ya pertenece a otro streamer en Squad ALPHA.']);
        }
        if ($existing && $existing->auto_detected) {
            // No cambia la visibilidad de vídeos retirados previamente por moderación.
            return back()->with('success', 'Este vídeo ya está registrado en el sistema.');
        }
        $record = $existing ?: new Stream();
        $record->fill([
            'streamer_id' => $streamer->id,
            'event_id' => $data['event_id'] ?? $record->event_id,
            'platform' => $platform,
            'stream_url' => $url,
            'twitch_video_id' => $platform === 'twitch' ? $videoId : null,
            'title' => trim((string) ($data['title'] ?? '')) ?: 'VOD de ' . ($request->user()->nick ?? 'Squad ALPHA'),
            'is_vod' => true,
            'enabled' => false,
            'visible_on_home' => true,
            'auto_detected' => false,
            'published_at' => $record->published_at ?? now(),
            'ended_at' => $record->ended_at ?? now(),
            'thumbnail_url' => $platform === 'youtube' ? 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg' : $record->thumbnail_url,
        ])->save();
        $vodService->invalidateCache();
        return back()->with('success', 'VOD añadido a la comunidad.');
    }

    public function lookupEvents(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('streamer-vods.create') || $user->streamer?->enable), 403);
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json([]);
        $events = Event::query()->where('name', 'like', '%' . addcslashes($q, '%_\\') . '%')
            ->whereHas('eventStatus', fn ($query) => $query->where('name', 'FINALIZADO'))
            ->orderByDesc('date')->limit(20)->get(['id', 'name', 'date']);
        return response()->json($events->map(fn (Event $e) => ['id' => $e->id, 'label' => $e->name . ' · ' . $e->date?->format('d/m/Y')])->all());
    }

    public function destroy(Request $request, Stream $stream, HomepageVodService $vodService): RedirectResponse
    {
        $streamer = $request->user()?->streamer;
        abort_unless($stream->isVodForHome() && (
            $request->user()->hasRole('admin')
            || $request->user()->can('streamer-vods.manage')
            || ($streamer && (int) $stream->streamer_id === (int) $streamer->id && ($streamer->enable || $request->user()->can('streamer-vods.delete')))
        ), 403);
        $stream->update(['visible_on_home' => false]);
        $vodService->invalidateCache();
        return back()->with('success', 'VOD retirado de la portada. El vídeo original no se elimina.');
    }
}
