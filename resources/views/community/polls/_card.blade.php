@php
    $poll = $pollData['poll'];
    $mine = $pollData['myVotes'] ?? collect();
    $canVote = $canVote ?? false;
    $hasVoted = $mine->isNotEmpty();
    $selectedIds = $mine->where('is_abstain', false)->pluck('community_poll_option_id')->map(fn ($id) => (int) $id);
    $abstained = $mine->contains(fn ($vote) => (bool) $vote->is_abstain);
    $participants = (int) ($poll->participants_count ?? 0);
    $denominator = max(1, $participants);
    $resultsVisible = $poll->canShowResults($hasVoted);
    $canSubmit = $canVote && $poll->isOpen() && (! $hasVoted || $poll->allow_vote_change);

    if (! $poll->is_published) {
        $stateLabel = 'BORRADOR';
    } elseif ($poll->starts_at && $poll->starts_at->isFuture()) {
        $stateLabel = 'PROGRAMADA';
    } elseif ($poll->isOpen()) {
        $stateLabel = 'ABIERTA';
    } else {
        $stateLabel = 'CERRADA';
    }

    $displayOptions = $poll->randomize_options ? $poll->options->shuffle() : $poll->options;
@endphp

<section class="community-panel thread-poll" id="poll-{{ $poll->id }}">
    <div class="poll-head">
        <div>
            <span class="community-kicker">VOTACIÓN DEL HILO</span>
            <h2>{{ $poll->title }}</h2>
            <small>
                @if($poll->starts_at && $poll->starts_at->isFuture())
                    Abre {{ $poll->starts_at->format('d/m/Y H:i') }}
                @elseif($poll->ends_at)
                    Cierra {{ $poll->ends_at->format('d/m/Y H:i') }}
                @else
                    Sin fecha de cierre
                @endif
            </small>
        </div>
        <span class="process-status">{{ $stateLabel }}</span>
    </div>

    @if($poll->description)
        <p class="thread-poll__description">{{ $poll->description }}</p>
    @endif

    <div class="poll-rules">
        <span>{{ $poll->isMultipleChoice() ? 'Selección múltiple' : 'Una opción' }}</span>
        @if($poll->isMultipleChoice())
            <span>{{ $poll->min_choices }}–{{ $poll->max_choices ?: $poll->options->count() }} opciones</span>
        @endif
        <span>{{ $poll->is_anonymous ? 'Voto anónimo' : 'Voto nominal' }}</span>
        <span>{{ $poll->allow_vote_change ? 'Voto modificable' : 'Voto definitivo' }}</span>
        @if($poll->allow_abstain)<span>Permite abstención</span>@endif
    </div>

    @if($poll->show_participation)
        <div class="poll-participation">
            <strong>{{ $participants }}</strong> participante(s)
            @if($poll->quorum_percent)
                · Quórum: {{ $poll->quorum_current_percent }}% / {{ $poll->quorum_percent }}%
                @if($poll->quorum_current_percent >= $poll->quorum_percent)
                    · <strong>alcanzado</strong>
                @endif
            @endif
        </div>
    @endif

    @if($poll->isOpen())
        <form method="POST" action="{{ route('community.polls.vote', $poll) }}">
            @csrf

            @foreach($displayOptions as $option)
                @php
                    $percent = $participants > 0
                        ? min(100, (int) round(($option->votes_count / $denominator) * 100))
                        : 0;
                    $checked = $selectedIds->contains((int) $option->id);
                @endphp
                <label class="poll-option">
                    <input
                        type="{{ $poll->isMultipleChoice() ? 'checkbox' : 'radio' }}"
                        name="option_ids[]"
                        value="{{ $option->id }}"
                        @checked($checked)
                        @disabled(! $canSubmit)
                    >
                    <span>
                        @if($option->candidate)
                            <a
                                href="{{ route('users.show', $option->candidate) }}"
                                class="poll-candidate"
                                style="--candidate-color:{{ $option->candidate->getFrontendColor() }}"
                                onclick="event.stopPropagation()"
                            >{{ $option->label }}</a>
                        @else
                            {{ $option->label }}
                        @endif
                        @if($resultsVisible)
                            <span class="poll-option__bar"><span style="width:{{ $percent }}%"></span></span>
                        @endif
                        @if($resultsVisible && $poll->show_voter_names && ! $poll->is_anonymous && $option->voter_names?->isNotEmpty())
                            <small class="poll-voters">{{ $option->voter_names->join(', ') }}</small>
                        @endif
                    </span>
                    @if($resultsVisible)
                        <small>{{ $option->votes_count }} · {{ $percent }}%</small>
                    @endif
                </label>
            @endforeach

            @if($poll->allow_abstain)
                @php
                    $abstainPercent = $participants > 0
                        ? min(100, (int) round(($poll->abstain_count / $denominator) * 100))
                        : 0;
                @endphp
                <label class="poll-option poll-option--abstain">
                    <input type="checkbox" name="abstain" value="1" @checked($abstained) @disabled(! $canSubmit)>
                    <span>
                        Abstención
                        @if($resultsVisible)
                            <span class="poll-option__bar"><span style="width:{{ $abstainPercent }}%"></span></span>
                        @endif
                    </span>
                    @if($resultsVisible)<small>{{ $poll->abstain_count }} · {{ $abstainPercent }}%</small>@endif
                </label>
            @endif

            @if($canSubmit)
                <button class="community-btn" type="submit" style="margin-top:14px">
                    {{ $hasVoted ? 'Actualizar voto' : 'Votar' }}
                </button>
            @elseif(!$canVote)
                <div class="community-notice" style="margin-top:14px">
                    Puedes consultar esta votación, pero solo los miembros con acceso a Votaciones pueden emitir voto.
                </div>
            @elseif($hasVoted && ! $poll->allow_vote_change)
                <div class="community-notice" style="margin-top:14px">
                    Tu voto está registrado y no puede modificarse.
                </div>
            @endif
        </form>
    @elseif($resultsVisible)
        @foreach($displayOptions as $option)
            @php
                $percent = $participants > 0
                    ? min(100, (int) round(($option->votes_count / $denominator) * 100))
                    : 0;
            @endphp
            <div class="poll-option">
                <span></span>
                <span>
                    {{ $option->label }}
                    <span class="poll-option__bar"><span style="width:{{ $percent }}%"></span></span>
                    @if($poll->show_voter_names && ! $poll->is_anonymous && $option->voter_names?->isNotEmpty())
                        <small class="poll-voters">{{ $option->voter_names->join(', ') }}</small>
                    @endif
                </span>
                <small>{{ $option->votes_count }} · {{ $percent }}%</small>
            </div>
        @endforeach
    @else
        <div class="community-notice">Los resultados de esta votación no están disponibles todavía.</div>
    @endif
</section>
