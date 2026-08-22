<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Metopa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function open(
        Request $request,
        string $notification,
    ): RedirectResponse {
        $record = $request
            ->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        $data = $record->data;

        return match (
            $data['type'] ?? null
        ) {
            'event_published' =>
                $this->redirectToEvent(
                    (int) (
                        $data['event_id'] ?? 0
                    )
                ),

            'metopa_awarded' =>
                $this->redirectToMetopa(
                    (int) (
                        $data['metopa_id'] ?? 0
                    )
                ),

            default =>
                redirect()->route('home'),
        };
    }

    public function readAll(
        Request $request,
    ): RedirectResponse {
        $request
            ->user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return back();
    }

    private function redirectToEvent(
        int $eventId,
    ): RedirectResponse {
        $event = Event::query()
            ->whereKey($eventId)
            ->whereHas(
                'eventStatus',
                fn ($query) =>
                    $query->whereIn(
                        'name',
                        [
                            'ACTIVO',
                            'FINALIZADO',
                        ]
                    )
            )
            ->first();

        if (! $event) {
            return redirect()
                ->route('events.index');
        }

        return redirect()
            ->route(
                'events.show',
                $event
            );
    }

    private function redirectToMetopa(
        int $metopaId,
    ): RedirectResponse {
        $metopa = Metopa::query()
            ->find($metopaId);

        if (! $metopa) {
            return redirect()
                ->route('metopas.index');
        }

        return redirect()
            ->route(
                'metopas.show',
                $metopa
            );
    }
}