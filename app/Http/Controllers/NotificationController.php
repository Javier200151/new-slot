<?php

namespace App\Http\Controllers;

use App\Models\CommunityDiary;
use App\Models\CampaignAar;
use App\Models\CommunityPost;
use App\Models\Event;
use App\Models\Metopa;
use App\Models\User;
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

            'community_roulette_winner' =>
                $this->redirectToEvent(
                    (int) (
                        $data['event_id'] ?? 0
                    )
                ),

            'campaign_aar_pending' =>
                $this->redirectToCampaignAar(
                    (int) ($data['aar_id'] ?? 0),
                    true,
                ),

            'campaign_aar_published' =>
                $this->redirectToCampaignAar(
                    (int) ($data['aar_id'] ?? 0),
                    false,
                ),

            'metopa_awarded' =>
                $this->redirectToMetopa(
                    (int) (
                        $data['metopa_id'] ?? 0
                    )
                ),

            'birthday' =>
                $this->redirectToUser(
                    (int) (
                        $data['birthday_user_id'] ?? 0
                    )
                ),

            'community_subscription_update' =>
                $this->redirectToCommunitySubscription($data),

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

        $latestNotificationChange = $user
            ->notifications()
            ->latest('updated_at')
            ->first(['id', 'updated_at']);

        $unreadCount = $user
            ->unreadNotifications()
            ->count();

        $signature =
            ($latestNotificationChange?->id ?? 'none')
            . ':'
            . ($latestNotificationChange?->updated_at?->getTimestamp() ?? 0)
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

    private function redirectToCampaignAar(
        int $aarId,
        bool $editing,
    ): RedirectResponse {
        $aar = CampaignAar::query()
            ->with(['campaign', 'event'])
            ->find($aarId);

        if (! $aar || ! $aar->campaign || ! $aar->event) {
            return redirect()->route('campaigns.index');
        }

        $parameters = [
            'campaign' => $aar->campaign,
            'event' => $aar->event,
        ];

        if ($editing) {
            $parameters['editar'] = 1;
        }

        return redirect()->route(
            'campaigns.aars.show',
            $parameters,
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

    private function redirectToUser(
        int $userId,
    ): RedirectResponse {
        $user = User::query()->find($userId);

        if (! $user) {
            return redirect()->route('users.index');
        }

        return redirect()->route('users.show', ['user' => $user->nick]);
    }
    private function redirectToCommunitySubscription(array $data): RedirectResponse
    {
        $subjectType = (string) ($data['subject_type'] ?? '');
        $subjectId = (int) ($data['subject_id'] ?? 0);

        if ($subjectType === 'forum') {
            $post = CommunityPost::query()->find($subjectId);

            if (! $post) {
                return redirect()->route('community.forum.home');
            }

            return redirect()->to(
                route('community.forum.show', [$post->channel, $post]) . '#respuestas'
            );
        }

        if ($subjectType === 'diary') {
            $diary = CommunityDiary::query()->find($subjectId);

            if (! $diary) {
                return redirect()->route('community.diary.index');
            }

            return redirect()->route('community.diary.show', $diary);
        }

        return redirect()->route('home');
    }

}