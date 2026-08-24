@php
    $notificationUser = auth()->user();

    $headerNotifications = $notificationUser
        ->notifications()
        ->latest()
        ->limit(10)
        ->get();

    $unreadNotificationCount = $notificationUser
        ->unreadNotifications()
        ->count();

    $latestNotification =
        $headerNotifications->first();

    $notificationSignature =
        ($latestNotification?->id ?? 'none')
        . ':'
        . $unreadNotificationCount;

    $notificationEventIds = $headerNotifications
        ->filter(
            fn ($notification) =>
                ($notification->data['type'] ?? null)
                === 'event_published'
        )
        ->map(
            fn ($notification) =>
                (int) (
                    $notification->data['event_id']
                    ?? 0
                )
        )
        ->filter()
        ->unique()
        ->values();

    $notificationEvents = \App\Models\Event::query()
        ->with('slots')
        ->whereIn(
            'id',
            $notificationEventIds
        )
        ->get()
        ->keyBy('id');
@endphp

<div
    class="notification-center"

    data-notification-center

    data-notification-poll-url="{{
        route('notifications.poll')
    }}"

    data-notification-signature="{{
        $notificationSignature
    }}"
>
    <button
        type="button"
        class="notification-bell"
        aria-label="Notificaciones"
        aria-expanded="false"
        data-notification-toggle
    >
        <svg
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path
                d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>

        @if($unreadNotificationCount > 0)
            <span class="notification-badge">
                {{ $unreadNotificationCount > 99
                    ? '99+'
                    : $unreadNotificationCount
                }}
            </span>
        @endif
    </button>

    <div
        class="notification-panel"
        data-notification-panel
        hidden
    >
        <header class="notification-panel__header">
            <div>
                <span>Centro de avisos</span>
                <strong>Notificaciones</strong>
            </div>

            @if($unreadNotificationCount > 0)
                <form
                    method="POST"
                    action="{{ route(
                        'notifications.read-all'
                    ) }}"
                >
                    @csrf

                    <button type="submit">
                        Marcar todas como leídas
                    </button>
                </form>
            @endif
        </header>

        <div class="notification-list">
            @forelse(
                $headerNotifications
                as $notification
            )
                @php
                    $data = $notification->data;

                    $type =
                        $data['type'] ?? null;

                    $event = $type === 'event_published'
                        ? $notificationEvents->get(
                            (int) (
                                $data['event_id']
                                ?? 0
                            )
                        )
                        : null;

                    $remainingSlots = $event
                        ? $event
                            ->getAvailableVisibleSlotsCount()
                        : null;

                    $eventDate = $event?->date;

                    if (
                        ! $eventDate
                        && filled(
                            $data['event_date']
                            ?? null
                        )
                    ) {
                        $eventDate =
                            \Illuminate\Support\Carbon::parse(
                                $data['event_date']
                            );
                    }
                @endphp

                <a
                    href="{{ route(
                        'notifications.open',
                        $notification->id
                    ) }}"
                    @class([
                        'notification-item',
                        'is-unread' =>
                            $notification->read_at === null,
                    ])
                >
                    <span class="notification-item__icon">

                        @if(
                            $type
                            === 'metopa_awarded'
                        )
                            ★

                        @elseif(
                            $type
                            === 'event_comment_reply'
                        )
                            ↩

                        @elseif(
                            $type
                            === 'event_slot_changed'
                        )
                            @if(
                                ($data['action'] ?? null)
                                === 'removed'
                            )
                                ×
                            @else
                                ↔
                            @endif

                        @else
                            !
                        @endif

                    </span>

                    <span class="notification-item__content">

                        @if(
                            $type
                            === 'event_published'
                        )
                            <strong>
                                Nuevo evento publicado
                            </strong>

                            <span>
                                {{ $data['event_name']
                                    ?? 'Evento'
                                }}
                            </span>

                            <small>
                                @if($eventDate)
                                    {{ ucfirst(
                                        $eventDate
                                            ->locale('es')
                                            ->translatedFormat(
                                                'l d/m/Y · H:i'
                                            )
                                    ) }}
                                @endif

                                @if(
                                    $remainingSlots
                                    !== null
                                )
                                    ·

                                    {{ $remainingSlots }}

                                    {{ $remainingSlots === 1
                                        ? 'slot libre'
                                        : 'slots libres'
                                    }}
                                @endif
                            </small>

                        @elseif(
                            $type
                            === 'metopa_awarded'
                        )
                            <strong>
                                Nueva metopa
                            </strong>

                            <span>
                                <b>
                                    {{ $data[
                                        'awarded_user_nick'
                                    ] ?? 'Un usuario' }}
                                </b>

                                ha conseguido

                                <b>
                                    {{ $data[
                                        'metopa_name'
                                    ] ?? 'una metopa' }}
                                </b>
                            </span>
                        @elseif(
                            $type
                            === 'event_comment_reply'
                        )
                            <strong>
                                Nueva respuesta
                            </strong>

                            <span>
                                <b>
                                    {{ $data[
                                        'reply_user_nick'
                                    ] ?? 'Un usuario' }}
                                </b>

                                ha respondido a tu comentario en

                                <b>
                                    {{ $data[
                                        'event_name'
                                    ] ?? 'un evento' }}
                                </b>
                            </span>
                        @elseif(
                            $type
                            === 'event_slot_changed'
                        )

                            @php
                                $slotAction =
                                    $data['action']
                                    ?? 'moved';

                                $fromSlotParts = array_filter([
                                    $data['from_slot_group']
                                        ?? null,

                                    $data['from_slot_name']
                                        ?? null,
                                ]);

                                $toSlotParts = array_filter([
                                    $data['to_slot_group']
                                        ?? null,

                                    $data['to_slot_name']
                                        ?? null,
                                ]);

                                $fromSlotLabel =
                                    implode(
                                        ' · ',
                                        $fromSlotParts
                                    );

                                $toSlotLabel =
                                    implode(
                                        ' · ',
                                        $toSlotParts
                                    );
                            @endphp


                            @if(
                                $slotAction
                                === 'removed'
                            )

                                <strong>
                                    Has sido eliminado del ORBAT
                                </strong>

                                <span>
                                    <b>
                                        {{ $data[
                                            'changed_by_user_nick'
                                        ] ?? 'Un administrador' }}
                                    </b>

                                    te ha eliminado del ORBAT de

                                    <b>
                                        {{ $data[
                                            'event_name'
                                        ] ?? 'un evento' }}
                                    </b>
                                </span>

                                @if(filled($fromSlotLabel))
                                    <small>
                                        {{ $fromSlotLabel }}
                                    </small>
                                @endif


                            @else

                                <strong>
                                    Tu slot ha cambiado
                                </strong>

                                <span>
                                    <b>
                                        {{ $data[
                                            'changed_by_user_nick'
                                        ] ?? 'Un administrador' }}
                                    </b>

                                    te ha movido en

                                    <b>
                                        {{ $data[
                                            'event_name'
                                        ] ?? 'un evento' }}
                                    </b>
                                </span>

                                @if(
                                    filled($fromSlotLabel)
                                    || filled($toSlotLabel)
                                )
                                    <small>
                                        @if(filled($fromSlotLabel))
                                            {{ $fromSlotLabel }}
                                        @endif

                                        @if(
                                            filled($fromSlotLabel)
                                            && filled($toSlotLabel)
                                        )
                                            →
                                        @endif

                                        @if(filled($toSlotLabel))
                                            {{ $toSlotLabel }}
                                        @endif
                                    </small>
                                @endif

                            @endif
                        @else
                            <strong>
                                Notificación
                            </strong>
                        @endif

                        <small>
                            {{ $notification
                                ->created_at
                                ?->format(
                                    'd/m/Y H:i'
                                )
                            }}
                        </small>
                    </span>

                    @if(
                        $notification->read_at
                        === null
                    )
                        <span
                            class="notification-item__unread"
                            aria-label="Sin leer"
                        ></span>
                    @endif
                </a>

            @empty
                <div class="notification-empty">
                    <strong>
                        Sin notificaciones
                    </strong>

                    <span>
                        Aquí aparecerán los nuevos
                        eventos y reconocimientos.
                    </span>
                </div>
            @endforelse
        </div>
    </div>
</div>