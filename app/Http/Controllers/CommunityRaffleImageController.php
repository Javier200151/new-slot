<?php

namespace App\Http\Controllers;

use App\Models\CommunityRafflePrize;
use App\Services\CommunityRaffleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommunityRaffleImageController extends Controller
{
    public function __invoke(Request $request, string $filename, CommunityRaffleService $service): StreamedResponse
    {
        $path = 'raffle-prizes/' . $filename;

        // No revelar fotografías de sorteos ajenos mediante una URL directa.
        $prizes = CommunityRafflePrize::query()
            ->with('raffle')
            ->where('image_path', $path)
            ->get();
        abort_unless($prizes->contains(fn ($prize) => $prize->raffle && $service->canView($prize->raffle, $request->user())), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
