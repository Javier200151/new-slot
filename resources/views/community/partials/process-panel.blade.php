@php
    $status = $process->effectiveStatus();
    $statusLabel = \App\Models\CommunityProcess::statusOptions()[$status] ?? $status;
    $typeLabel = \App\Models\CommunityProcess::typeOptions()[$process->type] ?? $process->type;
    $canApply = $process->canApply(auth()->user());
    $activeApplications = $process->activeApplications;
    $canManageProcess = $canManageProcess ?? false;
    $isCall = $process->type === \App\Models\CommunityProcess::TYPE_CALL;
    $phaseTwo = $isCall ? 'Postulaciones' : 'Debate';
@endphp

<section class="community-panel process-panel">
    <div class="process-panel__head">
        <div>
            <span class="community-kicker">{{ $typeLabel }}</span>
            <h2>{{ $isCall ? 'Proceso de convocatoria' : 'Proceso comunitario' }}</h2>
        </div>
        <span class="process-status">{{ $statusLabel }}</span>
    </div>

    <div class="process-timeline" aria-label="Fases del proceso">
        <span class="is-done">{{ $isCall ? 'Convocatoria' : $typeLabel }}</span>
        <span @class([
            'is-done' => in_array($status, ['applications_closed','voting','finalized','archived'], true),
            'is-current' => in_array($status, ['applications_open','discussion'], true),
        ])>{{ $phaseTwo }}</span>
        <span @class(['is-done' => in_array($status, ['finalized','archived'], true), 'is-current' => $status === 'voting'])>Votación</span>
        <span @class(['is-current' => $status === 'finalized'])>Resultado</span>
    </div>

    @if($isCall && $process->applications_enabled)
        <div class="process-meta">
            @if($process->applications_start_at)
                <span>Inicio: <strong>{{ $process->applications_start_at->format('d/m/Y H:i') }}</strong></span>
            @endif
            @if($process->applications_end_at)
                <span>Cierre: <strong>{{ $process->applications_end_at->format('d/m/Y H:i') }}</strong></span>
            @endif
            @if($process->max_winners)
                <span>Plazas/elegidos: <strong>{{ $process->max_winners }}</strong></span>
            @endif
            <span>
                Pueden postularse:
                <strong>{{ collect($process->eligible_statuses ?: ['ACTIVO'])->join(', ') }}</strong>
            </span>
        </div>
    @elseif(!$isCall)
        <div class="community-notice">
            Este hilo está marcado como <strong>{{ $typeLabel }}</strong>. El debate ocurre aquí mismo y el autor puede añadir una votación al hilo cuando corresponda.
        </div>
    @endif

    @if($isCall && $activeApplications->isNotEmpty())
        <div class="process-candidates">
            <div class="process-candidates__head">
                <h3>Postulaciones · {{ $activeApplications->count() }}</h3>
                @if(!$process->poll && $activeApplications->count() >= 2)
                    <small>El autor/moderador puede convertirlas en opciones de votación desde este mismo hilo.</small>
                @endif
            </div>
            @foreach($activeApplications as $application)
                <article class="process-candidate">
                    <div class="process-candidate__head">
                        <a
                            href="{{ route('users.show', $application->user) }}"
                            style="color: {{ $application->user?->getFrontendColor() ?? '#fff' }}"
                        >
                            {{ $application->user?->nick ?? 'Usuario eliminado' }}
                        </a>
                        <small>{{ $application->updated_at->format('d/m/Y H:i') }}</small>
                    </div>
                    <div class="forum-rich process-candidate__body">{!! \App\Support\ForumMarkup::render($application->body) !!}</div>
                </article>
            @endforeach
        </div>
    @endif

    @if($isCall && $myApplication && ! $myApplication->isWithdrawn())
        <div class="community-flash process-my-application">Tu candidatura está registrada.</div>
    @endif

    @if($isCall && ($canApply || ($myApplication && ! $myApplication->isWithdrawn() && $process->applicationsAreOpen() && $process->allow_application_edit)))
        <details class="process-application-form" @if(!$myApplication) open @endif>
            <summary class="community-btn community-btn--ghost">
                {{ $myApplication && ! $myApplication->isWithdrawn() ? 'Editar mi candidatura' : 'Presentar candidatura' }}
            </summary>
            <form method="POST" action="{{ route('community.processes.apply', $process) }}" class="community-form">
                @csrf
                @include('community.partials.editor', [
                    'id' => 'application-body',
                    'name' => 'application_body',
                    'label' => 'Motivación / propuesta',
                    'value' => old('application_body', $myApplication?->body),
                    'rows' => 8,
                ])
                <button class="community-btn" type="submit">Guardar candidatura</button>
            </form>
        </details>
    @endif

    @if($isCall && $myApplication && ! $myApplication->isWithdrawn() && $process->applicationsAreOpen() && $process->allow_application_withdraw)
        <form method="POST" action="{{ route('community.processes.withdraw', $process) }}" class="process-withdraw-form" onsubmit="return confirm('¿Retirar tu candidatura?')">
            @csrf @method('DELETE')
            <button class="community-btn community-btn--danger" type="submit">Retirar candidatura</button>
        </form>
    @endif

    @if($canManageProcess && $isCall)
        <details class="process-admin-form">
            <summary class="community-btn community-btn--ghost">Gestionar convocatoria</summary>
            <form method="POST" action="{{ route('community.processes.update', $process) }}" class="community-form">
                @csrf @method('PATCH')

                <label class="forum-switch forum-switch--major">
                    <input type="checkbox" name="applications_enabled" value="1" @checked($process->applications_enabled)>
                    <span><strong>Postulaciones activas</strong><small>También puedes cerrarlas estableciendo una fecha de cierre ya alcanzada.</small></span>
                </label>

                <div class="forum-form-grid forum-form-grid--3">
                    <div class="forum-field">
                        <label>Inicio</label>
                        <input type="datetime-local" name="applications_start_at" value="{{ $process->applications_start_at?->format('Y-m-d\\TH:i') }}">
                    </div>
                    <div class="forum-field">
                        <label>Cierre</label>
                        <input type="datetime-local" name="applications_end_at" value="{{ $process->applications_end_at?->format('Y-m-d\\TH:i') }}">
                    </div>
                    <div class="forum-field">
                        <label>Máximo de elegidos</label>
                        <input type="number" name="max_winners" min="1" max="20" value="{{ $process->max_winners }}">
                    </div>
                </div>

                <div class="forum-check-grid">
                    <label class="forum-switch">
                        <input type="checkbox" name="allow_application_edit" value="1" @checked($process->allow_application_edit)>
                        <span><strong>Permitir editar candidaturas</strong></span>
                    </label>
                    <label class="forum-switch">
                        <input type="checkbox" name="allow_application_withdraw" value="1" @checked($process->allow_application_withdraw)>
                        <span><strong>Permitir retirar candidaturas</strong></span>
                    </label>
                </div>

                <div class="forum-field">
                    <label>Quién puede postularse</label>
                    <div class="forum-inline-checks">
                        @foreach(['ACTIVO' => 'Miembro', 'RESERVA' => 'Reserva', 'RECLUTA' => 'Recluta'] as $candidateStatus => $candidateLabel)
                            <label>
                                <input
                                    type="checkbox"
                                    name="eligible_statuses[]"
                                    value="{{ $candidateStatus }}"
                                    @checked(in_array($candidateStatus, $process->eligible_statuses ?: ['ACTIVO'], true))
                                >
                                {{ $candidateLabel }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <button class="community-btn" type="submit">Guardar configuración</button>
            </form>
        </details>
    @endif
</section>
