<?php

namespace App\Http\Controllers;

use App\Services\InfrastructureStatusService;
use Illuminate\Http\JsonResponse;

class PublicInfrastructureController extends Controller
{
    public function status(InfrastructureStatusService $statusService): JsonResponse
    {
        $statusName = strtoupper(trim((string) request()->user()?->status?->name));

        abort_unless(
            in_array($statusName, ['ACTIVO', 'RECLUTA'], true),
            403,
        );

        return response()->json($statusService->snapshot());
    }
}
