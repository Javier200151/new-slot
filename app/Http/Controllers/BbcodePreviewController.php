<?php

namespace App\Http\Controllers;

use App\Support\BbcodeMarkup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BbcodePreviewController
{
    public function __invoke(Request $request): JsonResponse
    {
        // Preview is deliberately read-only: no forum models or activity log writes.
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:50000'],
        ]);

        return response()->json([
            'html' => BbcodeMarkup::render($data['body'] ?? '')->toHtml(),
        ]);
    }
}
