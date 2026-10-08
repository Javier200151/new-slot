<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BriefingImageController extends Controller
{
    public function show(string $filename): StreamedResponse
    {
        if (
            $filename === ''
            || basename($filename) !== $filename
            || str_contains($filename, '..')
        ) {
            abort(404);
        }

        $path = 'activities/briefings/' . $filename;
        $disk = Storage::disk('public');

        abort_unless($disk->exists($path), 404);

        $mimeType = rescue(
            fn (): ?string => $disk->mimeType($path),
            null,
            report: false,
        );

        abort_unless(
            is_string($mimeType) && str_starts_with(strtolower($mimeType), 'image/'),
            404,
        );

        return $disk->response(
            $path,
            null,
            [
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
