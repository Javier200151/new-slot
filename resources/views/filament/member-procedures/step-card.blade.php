@php
    /** @var \App\Models\MemberProcedureStep $record */
    $instructions = data_get($record->meta ?? [], 'instructions');
    $kindLabel = match ($record->kind) {
        \App\Models\MemberProcedureStep::KIND_AUTOMATIC => 'Automático',
        \App\Models\MemberProcedureStep::KIND_MANUAL => 'Manual',
        \App\Models\MemberProcedureStep::KIND_WAITING => 'Espera automática',
        default => (string) $record->kind,
    };
    $kindClass = match ($record->kind) {
        \App\Models\MemberProcedureStep::KIND_AUTOMATIC => 'is-automatic',
        \App\Models\MemberProcedureStep::KIND_MANUAL => 'is-manual',
        \App\Models\MemberProcedureStep::KIND_WAITING => 'is-waiting',
        default => '',
    };
    $statusClass = match ($record->status) {
        \App\Models\MemberProcedureStep::STATUS_COMPLETED => 'is-completed',
        \App\Models\MemberProcedureStep::STATUS_ERROR => 'is-error',
        \App\Models\MemberProcedureStep::STATUS_MANUAL,
        \App\Models\MemberProcedureStep::STATUS_WAITING => 'is-warning',
        \App\Models\MemberProcedureStep::STATUS_SKIPPED => 'is-skipped',
        default => 'is-info',
    };
@endphp

<article class="ns-procedure-step-card">
    <header class="ns-procedure-step-card__header">
        <div class="ns-procedure-step-card__number">Paso {{ $record->position }}</div>
        <div class="ns-procedure-step-card__title">{{ $record->label }}</div>
        <div class="ns-procedure-step-card__badges">
            <span class="ns-procedure-step-badge {{ $kindClass }}">{{ $kindLabel }}</span>
            <span class="ns-procedure-step-badge {{ $statusClass }}">{{ $record->statusLabel() }}</span>
        </div>
    </header>

    <section class="ns-procedure-step-card__instructions">
        <div class="ns-procedure-step-card__field-label">Instrucciones</div>
        <div class="ns-procedure-step-card__instructions-text">
            {{ filled($instructions) ? $instructions : '—' }}
        </div>
    </section>

    <div class="ns-procedure-step-card__meta-grid">
        <div class="ns-procedure-step-card__meta-item">
            <span class="ns-procedure-step-card__field-label">Tipo</span>
            <strong>{{ $kindLabel }}</strong>
        </div>
        <div class="ns-procedure-step-card__meta-item">
            <span class="ns-procedure-step-card__field-label">Estado</span>
            <strong>{{ $record->statusLabel() }}</strong>
        </div>
        <div class="ns-procedure-step-card__meta-item">
            <span class="ns-procedure-step-card__field-label">Intentos</span>
            <strong>{{ $record->attempts }}</strong>
        </div>
        <div class="ns-procedure-step-card__meta-item">
            <span class="ns-procedure-step-card__field-label">Completado por</span>
            <strong>{{ $record->completedBy?->nick ?: '—' }}</strong>
        </div>
        <div class="ns-procedure-step-card__meta-item">
            <span class="ns-procedure-step-card__field-label">Completado</span>
            <strong>{{ $record->completed_at?->format('d/m/Y H:i') ?: '—' }}</strong>
        </div>
        <div class="ns-procedure-step-card__meta-item ns-procedure-step-card__meta-item--wide">
            <span class="ns-procedure-step-card__field-label">Detalle / error</span>
            <strong class="ns-procedure-step-card__detail">{{ filled($record->last_error) ? $record->last_error : '—' }}</strong>
        </div>
    </div>
</article>
