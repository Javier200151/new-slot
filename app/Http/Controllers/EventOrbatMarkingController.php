<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventOrbatMarkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventOrbatMarkingController extends Controller
{
    public function update(Request $request, Event $event, EventOrbatMarkingService $service): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['required', 'string', 'max:191'],
            'second_in_command' => ['nullable', 'string', 'max:191'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.slot_key' => ['required', 'string', 'max:191'],
            'items.*.number' => ['present', 'nullable', 'integer', 'min:1', 'max:999'],
            'items.*.color' => ['required', 'in:white,green,yellow,red,blue'],
        ]);

        $changed = $service->save(
            $event,
            $request->user(),
            $validated['group'],
            $validated['items'],
            $validated['second_in_command'] ?? null,
        );

        return response()->json([
            'saved' => true,
            'changed' => $changed,
            'updated_by' => $changed ? $request->user()->nick : null,
            'updated_at' => $changed ? now()->format('d/m/Y H:i') : null,
            'message' => $changed ? 'Organización táctica guardada.' : 'No hay cambios que guardar.',
        ])->header('Cache-Control', 'no-store');
    }
}
