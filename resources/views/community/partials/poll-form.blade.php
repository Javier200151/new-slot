@php
    $showEnableToggle = $showEnableToggle ?? false;
    $canUseCandidates = $canUseCandidates ?? false;
    $candidateCount = $candidateCount ?? 0;
    $pollOpen = $pollOpen ?? false;
    $editingPoll = $poll ?? null;
    $formSuffix = $editingPoll ? ('edit-' . $editingPoll->id) : ($showEnableToggle ? 'new' : 'thread');
    $defaultUseCandidates = $editingPoll
        ? $editingPoll->options->contains(fn ($option) => filled($option->candidate_user_id))
        : false;
    $useCandidatesValue = (bool) old('use_candidates', $defaultUseCandidates);
    $manualOptions = $editingPoll
        ? $editingPoll->options->sortBy([['sort_order', 'asc'], ['id', 'asc']])->pluck('label')->implode("\n")
        : '';
    $dateValue = static fn ($value): string => $value ? $value->format('Y-m-d\TH:i') : '';
@endphp

@if($showEnableToggle)
    <label class="forum-switch forum-switch--major">
        <input type="checkbox" name="poll_enabled" value="1" @checked(old('poll_enabled')) data-forum-poll-toggle>
        <span>
            <strong>Añadir una votación al hilo</strong>
            <small>La votación queda embebida en este hilo del Foro.</small>
        </span>
    </label>
@endif

<div class="forum-poll-config" data-forum-poll-config @if($showEnableToggle && !old('poll_enabled')) hidden @endif>
    <div class="forum-config-head">
        <div>
            <span class="community-kicker">VOTACIÓN</span>
            <h3>{{ $editingPoll ? 'Editar configuración' : 'Configuración' }}</h3>
        </div>
        <small>{{ $editingPoll ? 'Los votos existentes se conservan. Las opciones con votos no se pueden eliminar ni renombrar.' : 'Todo se gestiona desde el propio hilo.' }}</small>
    </div>

    @if($canUseCandidates)
        <label class="forum-switch">
            <input type="checkbox" name="use_candidates" value="1" @checked($useCandidatesValue) data-forum-candidate-toggle>
            <span>
                <strong>Usar las postulaciones como opciones</strong>
                <small>{{ $candidateCount }} candidatura(s) activa(s). Las opciones se generan automáticamente.</small>
            </span>
        </label>
    @endif

    <div class="forum-form-grid forum-form-grid--2">
        <div class="forum-field forum-field--full">
            <label for="poll-title-{{ $formSuffix }}">Título de la votación</label>
            <input
                id="poll-title-{{ $formSuffix }}"
                name="poll_title"
                value="{{ old('poll_title', $editingPoll?->title) }}"
                maxlength="180"
                placeholder="Si lo dejas vacío usará el título del hilo"
            >
        </div>

        <div class="forum-field forum-field--full">
            @include('partials.bbcode-editor', [
                'id' => 'poll-description-' . $formSuffix,
                'name' => 'poll_description',
                'label' => 'Descripción breve',
                'value' => old('poll_description', $editingPoll?->description),
                'rows' => 3,
                'maxlength' => 5000,
                'required' => false,
                'placeholder' => 'Qué se está decidiendo, contexto, criterios...',
            ])
        </div>

        <div class="forum-field forum-field--full" data-forum-manual-options @if($canUseCandidates && $useCandidatesValue) hidden @endif>
            <label for="poll-options-{{ $formSuffix }}">Opciones</label>
            <textarea id="poll-options-{{ $formSuffix }}" name="poll_options" rows="5" placeholder="Una opción por línea&#10;Opción A&#10;Opción B&#10;Opción C">{{ old('poll_options', $manualOptions) }}</textarea>
            <small>Una opción por línea. Máximo 30. Este campo es estructurado y no usa BBCode.</small>
        </div>

        <div class="forum-field">
            <label>Tipo de selección</label>
            @php($selectionMode = old('poll_selection_mode', $editingPoll?->selection_mode ?? 'single'))
            <select name="poll_selection_mode" data-forum-poll-mode>
                <option value="single" @selected($selectionMode === 'single')>Una sola opción</option>
                <option value="multiple" @selected($selectionMode === 'multiple')>Múltiples opciones</option>
            </select>
        </div>

        <div class="forum-field forum-multiple-only" data-forum-multiple-only>
            <label>Mínimo de opciones</label>
            <input type="number" name="poll_min_choices" min="1" max="30" value="{{ old('poll_min_choices', $editingPoll?->min_choices ?? 1) }}">
        </div>

        <div class="forum-field forum-multiple-only" data-forum-multiple-only>
            <label>Máximo de opciones</label>
            <input type="number" name="poll_max_choices" min="1" max="30" value="{{ old('poll_max_choices', $editingPoll?->max_choices ?? 2) }}">
        </div>

        <div class="forum-field">
            <label>Resultados</label>
            @php($resultsVisibility = old('poll_results_visibility', $editingPoll?->results_visibility ?? 'always'))
            <select name="poll_results_visibility">
                <option value="always" @selected($resultsVisibility === 'always')>Siempre visibles</option>
                <option value="after_vote" @selected($resultsVisibility === 'after_vote')>Después de votar</option>
                <option value="after_close" @selected($resultsVisibility === 'after_close')>Solo al cerrar</option>
                <option value="hidden" @selected($resultsVisibility === 'hidden')>Ocultos</option>
            </select>
        </div>

        <div class="forum-field">
            <label>Inicio</label>
            <input type="datetime-local" name="poll_starts_at" value="{{ old('poll_starts_at', $dateValue($editingPoll?->starts_at)) }}">
        </div>

        <div class="forum-field">
            <label>Cierre</label>
            <input type="datetime-local" name="poll_ends_at" value="{{ old('poll_ends_at', $dateValue($editingPoll?->ends_at)) }}">
        </div>

        <div class="forum-field">
            <label>Quórum mínimo (%)</label>
            <input type="number" name="poll_quorum_percent" min="1" max="100" value="{{ old('poll_quorum_percent', $editingPoll?->quorum_percent) }}" placeholder="Opcional">
        </div>
    </div>

    <div class="forum-check-grid">
        <label class="forum-switch">
            <input type="checkbox" name="poll_allow_vote_change" value="1" @checked((bool) old('poll_allow_vote_change', $editingPoll?->allow_vote_change ?? true))>
            <span><strong>Permitir cambiar el voto</strong></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_is_anonymous" value="1" @checked((bool) old('poll_is_anonymous', $editingPoll?->is_anonymous ?? false))>
            <span><strong>Voto anónimo</strong></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_show_voter_names" value="1" @checked((bool) old('poll_show_voter_names', $editingPoll?->show_voter_names ?? false))>
            <span><strong>Mostrar nombres de votantes</strong><small>Se ignora si el voto es anónimo.</small></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_show_participation" value="1" @checked((bool) old('poll_show_participation', $editingPoll?->show_participation ?? true))>
            <span><strong>Mostrar participación</strong></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_allow_abstain" value="1" @checked((bool) old('poll_allow_abstain', $editingPoll?->allow_abstain ?? false))>
            <span><strong>Permitir abstención</strong></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_randomize_options" value="1" @checked((bool) old('poll_randomize_options', $editingPoll?->randomize_options ?? false))>
            <span><strong>Orden aleatorio de opciones</strong></span>
        </label>
    </div>
</div>
