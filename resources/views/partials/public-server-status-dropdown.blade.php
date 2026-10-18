@php
    // El mismo desplegable para el footer y los accesos rápidos.
    // Solo cambia la apariencia del summary; contenido/endpoint/JS son idénticos.
    $asIcon = ($mode ?? 'footer') === 'quick';
@endphp
<details
    @class(['footer-infrastructure', 'sqa-quick-access__server' => $asIcon])
    data-infrastructure-status
    data-endpoint="{{ route('infrastructure.status') }}"
    data-credential-base="{{ url('/infraestructura/credencial') }}"
    @if($asIcon) data-quick-access-server-panel @endif
>
    @if($asIcon)
        <summary class="sqa-quick-access__button sqa-quick-access__server-trigger" aria-label="{{ $label }}" title="{{ $label }}">
            <svg aria-hidden="true" viewBox="0 0 24 24"><use href="{{ asset('images/quick-access-icons.svg') }}#sqa-{{ $icon }}"></use></svg>
        </summary>
    @else
        <summary class="footer-users-link footer-infrastructure__trigger">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="4" width="18" height="6" rx="2" />
                <rect x="3" y="14" width="18" height="6" rx="2" />
                <path d="M7 7h.01M7 17h.01" />
            </svg>
            {{ $label }}
            <span class="footer-infrastructure__chevron" aria-hidden="true">⌄</span>
        </summary>
    @endif
            <div class="footer-infrastructure__panel {{ $asIcon ? 'sqa-quick-access__server-panel' : '' }}">
                <strong class="footer-infrastructure__title">Estado de servidores</strong>

                <div class="footer-infrastructure__services">
                    <div class="footer-infrastructure__row" data-services-loading>
                        <span class="footer-status-dot is-loading" data-status-dot aria-hidden="true"></span>
                        <span class="footer-infrastructure__service-name">Servidores ArmA</span>
                        <small data-status-detail>Comprobando…</small>
                    </div>
                </div>

                <div class="footer-infrastructure__ts3" data-ts3-block hidden>
                    <div class="footer-infrastructure__separator"></div>
                    <div class="footer-infrastructure__row" data-ts3-row>
                        <span class="footer-status-dot is-loading" data-status-dot aria-hidden="true"></span>
                        <span class="footer-infrastructure__service-name">TeamSpeak 3</span>
                        <small data-ts3-detail>Comprobando…</small>
                    </div>
                    <div class="footer-infrastructure__ts-floating" data-ts3-floating hidden>
                        <div class="footer-infrastructure__ts-header"><strong>Usuarios en TeamSpeak</strong><button type="button" data-ts3-close aria-label="Cerrar usuarios">×</button></div>
                        <ul class="footer-infrastructure__users" data-ts3-users></ul>
                    </div>
                </div>
            </div>
</details>
