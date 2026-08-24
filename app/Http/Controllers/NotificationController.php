<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Metopa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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

            'event_comment_reply' =>
                $this->redirectToEventComments(
                    (int) (
                        $data['event_id'] ?? 0
                    )
                ),

            'event_slot_changed' =>
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

    public function poll(
        Request $request,
    ): JsonResponse {
        $user = $request->user();

        $latestNotification = $user
            ->notifications()
            ->latest()
            ->first();

        $unreadCount = $user
            ->unreadNotifications()
            ->count();

        $signature =
            ($latestNotification?->id ?? 'none')
            . ':'
            . $unreadCount;

        $currentSignature =
            (string) $request->query(
                'signature',
                ''
            );

        /*
        * Si no ha cambiado nada desde
        * la última comprobación, no
        * renderizamos todo el panel.
        */
        if (
            $currentSignature === $signature
        ) {
            return response()
                ->json([
                    'changed' => false,
                    'signature' => $signature,
                ])
                ->header(
                    'Cache-Control',
                    'no-store, no-cache, must-revalidate'
                );
        }

        /*
        * Hay una notificación nueva o
        * ha cambiado el número de no leídas.
        */
        return response()
            ->json([
                'changed' => true,

                'signature' =>
                    $signature,

                'html' => view(
                    'partials.notification-bell'
                )->render(),
            ])
            ->header(
                'Cache-Control',
                'no-store, no-cache, must-revalidate'
            );
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
    private function redirectToEventComments(
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
            ->to(
                route(
                    'events.show',
                    $event
                )
                . '#comentarios'
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