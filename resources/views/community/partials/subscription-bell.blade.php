<form
    method="POST"
    action="{{ route('community.subscriptions.toggle', [$type, $subject->id]) }}"
    class="community-subscription"
    title="{{ $subscribed ? 'Desactivar notificaciones' : 'Avisarme cuando haya novedades' }}"
>
    @csrf
    <button
        type="submit"
        @class(['community-subscription__button', 'is-active' => $subscribed])
        aria-label="{{ $subscribed ? 'Desactivar notificaciones' : 'Activar notificaciones' }}"
        aria-pressed="{{ $subscribed ? 'true' : 'false' }}"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>
</form>
