<section class="sqa-stats__member" aria-labelledby="sqa-member-stats-title">
    <div class="sqa-stats__member-header">
        <div>
            <span class="sqa-stats__kicker">ACTIVIDAD DEL MIEMBRO</span>
            <h2 id="sqa-member-stats-title">Estadísticas de jugador</h2>
        </div>
        <a href="{{ route('statistics.index', ['focus' => 'user', 'metric' => 'participations', 'group' => 'role', 'user' => $user->id, 'period' => $memberFilters['period'], 'platform' => $memberFilters['platform'] ?: null, 'activity_type' => $memberFilters['activity_type'] ?: null, 'role' => $memberFilters['role'] ?: null, 'map' => $memberFilters['map'] ?: null, 'campaign' => $memberFilters['campaign'] ?: null, 'from' => $memberFilters['from'], 'to' => $memberFilters['to']]) }}">Ver estadísticas completas</a>
    </div>

    <div class="sqa-stats__cards">
        <div class="sqa-stats__card"><span>Rol más jugado · Histórico</span><strong>{{ $memberStats['favorite_role'] ?? 'Sin datos' }}</strong><small>{{ $memberStats['favorite_role_count'] }} partidas</small></div>
        <div class="sqa-stats__card"><span>Últimos 12 meses</span><strong>{{ $memberStats['last_year'] }}</strong><small>Partidas registradas</small></div>
        <div class="sqa-stats__card"><span>Total histórico</span><strong>{{ $memberStats['total'] }}</strong><small>Partidas registradas</small></div>
        <div class="sqa-stats__card"><span>Roles diferentes</span><strong>{{ $memberStats['different_roles'] }}</strong><small>Tipos de slot utilizados</small></div>
    </div>

    <div class="sqa-stats__member-grid">
        <form action="{{ route('users.show', ['user' => $user->nick]) }}" method="GET" class="sqa-stats__member-filters">
            <h3>Explorar sus partidas</h3>
            <label for="ms-period">Periodo</label>
            <select id="ms-period" name="stats_period">
                @foreach($memberPeriodOptions as $key => $label)
                    <option value="{{ $key }}" @selected($memberFilters['period'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="sqa-stats__field-row">
                <div>
                    <label for="ms-from">Desde</label>
                    <input type="date" id="ms-from" name="stats_from" value="{{ $memberFilters['from'] }}">
                </div>
                <div>
                    <label for="ms-to">Hasta</label>
                    <input type="date" id="ms-to" name="stats_to" value="{{ $memberFilters['to'] }}">
                </div>
            </div>
            <label for="ms-platform">Plataforma</label>
            <select id="ms-platform" name="stats_platform">
                <option value="">Todas</option>
                @foreach($memberPlatforms as $id => $name)
                    <option value="{{ $id }}" @selected($memberFilters['platform'] === (int) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <label for="ms-type">Tipo de actividad</label>
            <select id="ms-type" name="stats_type">
                <option value="">Todos</option>
                @foreach($memberActivityTypes as $id => $name)
                    <option value="{{ $id }}" @selected($memberFilters['activity_type'] === (int) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <details class="sqa-stats__more" @if($memberFilters['map'] || $memberFilters['campaign']) open @endif>
                <summary>Mapa y campaña</summary>
                <div class="sqa-stats__extra">
                    <label for="ms-map">Mapa</label>
                    <select id="ms-map" name="stats_map">
                        <option value="">Todos</option>
                        @foreach($memberMaps as $id => $name)
                            <option value="{{ $id }}" @selected($memberFilters['map'] === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <label for="ms-campaign">Campaña</label>
                    <select id="ms-campaign" name="stats_campaign">
                        <option value="">Todas</option>
                        @foreach($memberCampaigns as $id => $name)
                            <option value="{{ $id }}" @selected($memberFilters['campaign'] === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </details>
            <label for="ms-role">Tipo de slot</label>
            <select id="ms-role" name="stats_role">
                <option value="">Todos</option>
                @foreach($memberRoleTypes as $id => $name)
                    <option value="{{ $id }}" @selected($memberFilters['role'] === (int) $id)>{{ $name }}</option>
                @endforeach
            </select>
            <button type="submit">Aplicar filtros</button>
        </form>

        <div class="sqa-stats__member-details">
            <div class="sqa-stats__member-subheading"><h3>Roles más jugados</h3><span>{{ $memberStats['filtered_total'] }} partidas</span></div>
            @forelse($memberStats['by_role'] as $role)
                <div class="sqa-stats__ranking-item">
                    <div class="sqa-stats__ranking-head"><span>{{ $role->label }}</span><strong>{{ $role->total }}</strong></div>
                    <div class="sqa-stats__bar"><span style="width: {{ $memberStats['filtered_total'] ? min(100, 100 * $role->total / $memberStats['filtered_total']) : 0 }}%"></span></div>
                </div>
            @empty
                <p class="sqa-stats__empty">Sin partidas registradas con estos filtros.</p>
            @endforelse
            <h3 class="sqa-stats__recent-title">Partidas recientes</h3>
            <div class="sqa-stats__events">
                @forelse($memberStats['recent_events'] as $event)
                    <a href="{{ route('events.show', ['event' => $event->id]) }}">
                        <span>{{ \Carbon\Carbon::parse($event->date)->format('d/m/Y') }}</span>
                        <strong>{{ $event->name ?: $event->activity_name }}</strong>
                        <small>{{ $event->role_name ?: 'Sin tipo de slot' }}</small>
                    </a>
                @empty
                    <p class="sqa-stats__empty">Sin eventos en este periodo.</p>
                @endforelse
            </div>
        </div>
    </div>
    <p class="sqa-stats__hint">Las cifras se basan en asignaciones de slots registradas en eventos celebrados. No acreditan asistencia física.</p>
</section>
