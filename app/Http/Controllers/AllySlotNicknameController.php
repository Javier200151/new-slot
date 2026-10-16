<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventSlot;
use App\Support\AllyNicknameAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AllySlotNicknameController extends Controller
{
    public function update(Request $request, Event $event, string $slotKey): RedirectResponse
    {
        // Nunca autorizar por un ally_id o evento aportado en el cuerpo de la petición.
        $slot = EventSlot::query()
            ->where('event_id', $event->getKey())
            ->where('slot_key', $slotKey)
            ->firstOrFail();

        abort_unless($event->eventStatus?->name === 'ACTIVO', 403);
        abort_unless(AllyNicknameAccess::canEdit($request->user(), $slot), 403);

        $validated = $request->validate([
            'ally_nickname' => ['nullable', 'string', 'max:80'],
        ]);

        $slot->update([
            'ally_nickname' => trim((string) ($validated['ally_nickname'] ?? '')) ?: null,
        ]);

        return redirect()->to(route('events.show', $event) . '#orbat')
            ->with('status', 'Nombre de aliado actualizado.');
    }
}
