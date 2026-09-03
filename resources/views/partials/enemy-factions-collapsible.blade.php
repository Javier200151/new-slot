@if(
    ($activity->activityType?->usesEnemyFactions() ?? true)
    && $activity->enemyFactions->isNotEmpty()
)
    <details
        id="facciones-enemigas"
        class="event-detail__section event-detail__collapsible"
    >
        <summary class="event-detail__collapsible-summary event-detail__enemy-factions-summary-row">
            <div>
                <span>Facciones enemigas</span>
                <small>Bandos y fuerzas enemigas de la actividad</small>
            </div>

            <div class="event-detail__enemy-factions-summary">
                <b>
                    {{ $activity->enemyFactions->count() }}
                    {{ $activity->enemyFactions->count() === 1 ? 'facción' : 'facciones' }}
                </b>

                <div
                    class="event-detail__enemy-factions-icons"
                    aria-label="Iconos de bandos y países enemigos"
                >
                    @foreach($activity->enemyFactions as $enemyFaction)
                        <span
                            class="event-detail__enemy-faction-icon-pair"
                            title="{{ $enemyFaction->name }}"
                        >
                            @if(filled($enemyFaction->side?->image))
                                <img
                                    class="event-detail__enemy-faction-side-icon"
                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($enemyFaction->side->image) }}"
                                    alt=""
                                    title="Bando: {{ $enemyFaction->side->name }}"
                                >
                            @endif

                            @if(filled($enemyFaction->army?->country?->image))
                                <img
                                    class="event-detail__enemy-faction-country-icon"
                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($enemyFaction->army->country->image) }}"
                                    alt=""
                                    title="País: {{ $enemyFaction->army->country->name }}"
                                >
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        </summary>

        <div class="event-detail__collapsible-content">
            <div class="event-detail__enemy-factions-list">
                @foreach($activity->enemyFactions as $enemyFaction)
                    <article class="event-detail__enemy-faction">
                        <div
                            class="event-detail__enemy-faction-visuals"
                            aria-hidden="true"
                        >
                            @if(filled($enemyFaction->side?->image))
                                <img
                                    class="event-detail__enemy-faction-side-icon"
                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($enemyFaction->side->image) }}"
                                    alt=""
                                >
                            @endif

                            @if(filled($enemyFaction->army?->country?->image))
                                <img
                                    class="event-detail__enemy-faction-country-icon"
                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($enemyFaction->army->country->image) }}"
                                    alt=""
                                >
                            @endif
                        </div>

                        <div class="event-detail__enemy-faction-copy">
                            <strong>{{ $enemyFaction->name }}</strong>

                            <div class="event-detail__enemy-faction-meta">
                                @if(filled($enemyFaction->side?->name))
                                    <span>{{ $enemyFaction->side->name }}</span>
                                @endif

                                @if(filled($enemyFaction->army?->name))
                                    <span>{{ $enemyFaction->army->name }}</span>
                                @endif

                                @if(filled($enemyFaction->army?->country?->name))
                                    <span>{{ $enemyFaction->army->country->name }}</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </details>
@endif
