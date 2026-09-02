@php
    $showEnableToggle = $showEnableToggle ?? false;
    $canUseCandidates = $canUseCandidates ?? false;
    $candidateCount = $candidateCount ?? 0;
    $pollOpen = $pollOpen ?? false;
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
            <h3>Configuración</h3>
        </div>
        <small>Todo se gestiona desde el propio hilo.</small>
    </div>

    @if($canUseCandidates)
        <label class="forum-switch">
            <input type="checkbox" name="use_candidates" value="1" @checked(old('use_candidates')) data-forum-candidate-toggle>
            <span>
                <strong>Usar las postulaciones como opciones</strong>
                <small>{{ $candidateCount }} candidatura(s) activa(s). Las opciones se generarán automáticamente.</small>
            </span>
        </label>
    @endif

    <div class="forum-form-grid forum-form-grid--2">
        <div class="forum-field forum-field--full">
            <label for="poll-title-{{ $showEnableToggle ? 'new' : 'thread' }}">Título de la votación</label>
            <input id="poll-title-{{ $showEnableToggle ? 'new' : 'thread' }}" name="poll_title" value="{{ old('poll_title') }}" maxlength="180" placeholder="Si lo dejas vacío usará el título del hilo">
        </div>

        <div class="forum-field forum-field--full">
            <label for="poll-description-{{ $showEnableToggle ? 'new' : 'thread' }}">Descripción breve</label>
            <textarea id="poll-description-{{ $showEnableToggle ? 'new' : 'thread' }}" name="poll_description" rows="3" placeholder="Qué se está decidiendo, contexto, criterios...">{{ old('poll_description') }}</textarea>
        </div>

        <div class="forum-field forum-field--full" data-forum-manual-options @if($canUseCandidates && old('use_candidates')) hidden @endif>
            <label for="poll-options-{{ $showEnableToggle ? 'new' : 'thread' }}">Opciones</label>
            <textarea id="poll-options-{{ $showEnableToggle ? 'new' : 'thread' }}" name="poll_options" rows="5" placeholder="Una opción por línea&#10;Opción A&#10;Opción B&#10;Opción C">{{ old('poll_options') }}</textarea>
            <small>Una opción por línea. Máximo 30.</small>
        </div>

        <div class="forum-field">
            <label>Tipo de selección</label>
            <select name="poll_selection_mode" data-forum-poll-mode>
                <option value="single" @selected(old('poll_selection_mode', 'single') === 'single')>Una sola opción</option>
                <option value="multiple" @selected(old('poll_selection_mode') === 'multiple')>Múltiples opciones</option>
            </select>
        </div>

        <div class="forum-field forum-multiple-only" data-forum-multiple-only>
            <label>Mínimo de opciones</label>
            <input type="number" name="poll_min_choices" min="1" max="30" value="{{ old('poll_min_choices', 1) }}">
        </div>

        <div class="forum-field forum-multiple-only" data-forum-multiple-only>
            <label>Máximo de opciones</label>
            <input type="number" name="poll_max_choices" min="1" max="30" value="{{ old('poll_max_choices', 2) }}">
        </div>

        <div class="forum-field">
            <label>Resultados</label>
            <select name="poll_results_visibility">
                <option value="always" @selected(old('poll_results_visibility', 'always') === 'always')>Siempre visibles</option>
                <option value="after_vote" @selected(old('poll_results_visibility') === 'after_vote')>Después de votar</option>
                <option value="after_close" @selected(old('poll_results_visibility') === 'after_close')>Solo al cerrar</option>
                <option value="hidden" @selected(old('poll_results_visibility') === 'hidden')>Ocultos</option>
            </select>
        </div>

        <div class="forum-field">
            <label>Inicio</label>
            <input type="datetime-local" name="poll_starts_at" value="{{ old('poll_starts_at') }}">
        </div>

        <div class="forum-field">
            <label>Cierre</label>
            <input type="datetime-local" name="poll_ends_at" value="{{ old('poll_ends_at') }}">
        </div>

        <div class="forum-field">
            <label>Quórum mínimo (%)</label>
            <input type="number" name="poll_quorum_percent" min="1" max="100" value="{{ old('poll_quorum_percent') }}" placeholder="Opcional">
        </div>
    </div>

    <div class="forum-check-grid">
        <label class="forum-switch">
            <input type="checkbox" name="poll_allow_vote_change" value="1" @checked(old('poll_allow_vote_change', true))>
            <span><strong>Permitir cambiar el voto</strong></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_is_anonymous" value="1" @checked(old('poll_is_anonymous'))>
            <span><strong>Voto anónimo</strong></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_show_voter_names" value="1" @checked(old('poll_show_voter_names'))>
            <span><strong>Mostrar nombres de votantes</strong><small>Se ignora si el voto es anónimo.</small></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_show_participation" value="1" @checked(old('poll_show_participation', true))>
            <span><strong>Mostrar participación</strong></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_allow_abstain" value="1" @checked(old('poll_allow_abstain'))>
            <span><strong>Permitir abstención</strong></span>
        </label>
        <label class="forum-switch">
            <input type="checkbox" name="poll_randomize_options" value="1" @checked(old('poll_randomize_options'))>
            <span><strong>Orden aleatorio de opciones</strong></span>
        </label>
    </div>
</div>
