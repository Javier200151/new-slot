<?php

namespace App\Http\Controllers;

use App\Services\MissionControl\MissionControlClient;
use App\Services\MissionControl\MissionControlException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MissionControlLogDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        string $server,
        string $source,
        string $file,
        MissionControlClient $client,
    ): StreamedResponse {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('admin') || $user->can('game-servers.logs')), 403);

        try {
            $stream = $client->downloadLog($server, $source, $file);
        } catch (MissionControlException $e) {
            abort($e->httpStatus >= 400 && $e->httpStatus < 500 ? $e->httpStatus : 502,
                'No se pudo descargar el log solicitado.');
        }

        return response()->stream(static function () use ($stream): void {
            try {
                while (! $stream->eof()) {
                    $chunk = $stream->read(65536);
                    if ($chunk === '') break;
                    echo $chunk;
                    if (connection_aborted()) break;
                    flush();
                }
            } finally {
                $stream->close();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$file.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
