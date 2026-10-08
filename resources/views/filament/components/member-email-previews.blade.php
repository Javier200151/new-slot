<div class="ns-email-preview-actions">
    <a
        class="ns-email-preview-action"
        href="{{ route('member-procedure-email-preview.show', ['type' => 'welcome']) }}"
        target="_blank"
        rel="noopener noreferrer"
    >
        Previsualizar correo de alta
        <span aria-hidden="true">↗</span>
    </a>

    <a
        class="ns-email-preview-action"
        href="{{ route('member-procedure-email-preview.show', ['type' => 'reactivation']) }}"
        target="_blank"
        rel="noopener noreferrer"
    >
        Previsualizar correo de reactivación
        <span aria-hidden="true">↗</span>
    </a>
</div>

<style>
    .ns-email-preview-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        width: 100%;
    }
    .ns-email-preview-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        min-height: 2.4rem;
        padding: .58rem .85rem;
        border: 1px solid rgba(245, 158, 11, .42);
        border-radius: .65rem;
        background: rgba(245, 158, 11, .10);
        color: #fbbf24;
        font-size: .78rem;
        font-weight: 800;
        line-height: 1.2;
        text-decoration: none;
        white-space: nowrap;
    }
    .ns-email-preview-action:hover { border-color: rgba(245,158,11,.70); background: rgba(245,158,11,.16); }
    @media (max-width:640px) {
        .ns-email-preview-actions { display:grid; grid-template-columns:1fr; }
        .ns-email-preview-action { width:100%; }
    }
</style>
