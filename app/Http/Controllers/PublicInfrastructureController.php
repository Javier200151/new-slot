<?php

namespace App\Http\Controllers;

use App\Services\InfrastructureStatusService;
use App\Models\InfrastructureSetting;
use Illuminate\Http\JsonResponse;

class PublicInfrastructureController extends Controller
{
    public function credential(int $index): JsonResponse
    {
        $user = request()->user();
        $statusName = strtoupper(trim((string) $user?->status?->name));
        abort_unless($user && (
            $user->hasRole('admin') || in_array($statusName, ['ACTIVO', 'RECLUTA'], true)
        ), 403);
        $settings = InfrastructureSetting::current();
        $password = $settings->serverAccessPassword($index);
        abort_if($password === null, 404);
        return response()->json(['password' => $password])->header('Cache-Control', 'no-store, private');
    }

    public function status(InfrastructureStatusService $statusService): JsonResponse
    {
        $user = request()->user();
        $statusName = strtoupper(trim((string) $user?->status?->name));

        abort_unless(
            $user && ($user->hasRole('admin') || in_array($statusName, ['ACTIVO', 'RECLUTA'], true)),
            403,
        );

        return response()->json($statusService->snapshot());
    }
}
