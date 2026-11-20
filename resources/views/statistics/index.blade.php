@extends('layouts.metopas')

@section('title', 'Estadísticas')
@section('meta-description', 'Centro de estadísticas de Squad ALPHA: participaciones, roles y eventos.')
@section('body-class', 'sqa-stats-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sqa-statistics.css') }}?v={{ filemtime(public_path('css/sqa-statistics.css')) }}">
@endpush

@section('content')
<div class="sqa-stats container">
    <div class="sqa-stats__crumb"><a href="{{ route('home') }}">Inicio</a> <span>/</span> Estadísticas</div>

    <header class="sqa-stats__heading">
        <div>
            <span class="sqa-stats__kicker">SQUAD ALPHA · DATOS DE LA COMUNIDAD</span>
            <h1>Centro de estadísticas</h1>
            <p>Explora las participaciones, los roles y las actividades de la comunidad.</p>
        </div>
    </header>

    <div class="sqa-stats__workspace">
        <aside class="sqa-stats__panel">
            <form method="GET" action="{{ route('statistics.index') }}" class="sqa-stats__form">
                <h2>Generar estadística</h2>
                <div class="sqa-stats__focus">
                    <span class="sqa-stats__step">PASO 1</span>
                    <label for="stat-focus">¿Qué quieres analizar?</label>
                    <select id="stat-focus" name="focus" aria-describedby="stat-focus-description">
                        @foreach(\App\Services\PublicStatisticsService::FOCUSES as $key => $label)
                            <option value="{{ $key }}" @selected($filters['focus'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p id="stat-focus-description" class="sqa-stats__hint">Elige una categoría y después aplica los filtros.</p>
                    <div id="stat-main-filter" class="sqa-stats__primary-filter">
                        {{-- JavaScript coloca aquí el selector de la categoría elegida. --}}
                    </div>
                </div>
                <div class="sqa-stats__configuration-heading"><span class="sqa-stats__step">PASO 2</span><h3>Personalizar la estadística</h3></div>
                <label for="stat-metric">Qué medir</label>
                <select id="stat-metric" name="metric">
                    @foreach($metricOptions as $key => $label)
                        <option value="{{ $key }}" @selected($filters['metric'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="stat-group">Agrupar por</label>
                <select id="stat-group" name="group">
                    @foreach($groupOptions as $key => $label)
                        <option value="{{ $key }}" @selected($filters['group'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="sqa-stats__hint">Las agrupaciones que no correspondan a la métrica se ajustan automáticamente.</p>

                <label for="stat-period">Periodo</label>
                <select id="stat-period" name="period">
                    @foreach($periodOptions as $key => $label)
                        <option value="{{ $key }}" @selected($filters['period'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <div class="sqa-stats__field-row">
                    <div>
                        <label for="stat-from">Desde</label>
                        <input type="date" id="stat-from" name="from" value="{{ $filters['from'] }}">
                    </div>
                    <div>
                        <label for="stat-to">Hasta</label>
                        <input type="date" id="stat-to" name="to" value="{{ $filters['to'] }}">
                    </div>
                </div>
                <p class="sqa-stats__hint">Las fechas personalizadas se aplican al seleccionar ese periodo.</p>

                <details class="sqa-stats__more" @if($filters['platform'] || $filters['map'] || $filters['campaign'] || $filters['status'] || ($filters['role'] && $filters['focus'] !== 'role') || ($filters['activity_type'] && $filters['focus'] !== 'activity_type') || ($filters['user'] && $filters['focus'] !== 'user') || ($filters['editor'] && $filters['focus'] !== 'editor')) open @endif>
                    <summary>Más filtros</summary>
                    <div class="sqa-stats__extra" id="stat-additional-filters">
                        <div class="sqa-stats__category-field" data-focus-field="role">
                            <label for="stat-role">Tipo de slot</label>
                            <select id="stat-role" name="role">
                                <option value="">Todos los tipos de slot</option>
                                @foreach($roles as $id => $name)
                                    <option value="{{ $id }}" @selected($filters['role'] === (int) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sqa-stats__category-field" data-focus-field="activity_type">
                            <label for="stat-type">Tipo de actividad</label>
                            <select id="stat-type" name="activity_type">
                                <option value="">Todos los tipos de actividad</option>
                                @foreach($activityTypes as $id => $name)
                                    <option value="{{ $id }}" @selected($filters['activity_type'] === (int) $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sqa-stats__category-field" data-focus-field="editor">
                            <label for="stat-editor">Editor</label>
                            <select id="stat-editor" name="editor">
                                <option value="">Todos los editores</option>
                                @foreach($members as $id => $nick)
                                    <option value="{{ $id }}" @selected($filters['editor'] === (int) $id)>{{ $nick }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sqa-stats__category-field" data-focus-field="user">
                            <label for="stat-user">Jugador</label>
                            <select id="stat-user" name="user">
                                <option value="">Todos los jugadores</option>
                                @foreach($members as $id => $nick)
                                    <option value="{{ $id }}" @selected($filters['user'] === (int) $id)>{{ $nick }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label for="stat-platform">Juego / plataforma</label>
                        <select id="stat-platform" name="platform">
                            <option value="">Todas</option>
                            @foreach($platforms as $id => $name)
                                <option value="{{ $id }}" @selected($filters['platform'] === (int) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <label for="stat-map">Mapa</label>
                        <select id="stat-map" name="map">
                            <option value="">Todos los mapas</option>
                            @foreach($maps as $id => $name)
                                <option value="{{ $id }}" @selected($filters['map'] === (int) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <label for="stat-campaign">Campaña</label>
                        <select id="stat-campaign" name="campaign">
                            <option value="">Todas las campañas</option>
                            @foreach($campaigns as $id => $name)
                                <option value="{{ $id }}" @selected($filters['campaign'] === (int) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <label for="stat-status">Estado actual del jugador</label>
                        <select id="stat-status" name="status">
                            <option value="">Todos los estados</option>
                            @foreach($statuses as $id => $status)
                                <option value="{{ $id }}" @selected($filters['status'] === (int) $id)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                </details>

                <div class="sqa-stats__field-row">
                    <div>
                        <label for="stat-top">Resultados</label>
                        <select id="stat-top" name="top">
                            @foreach([5,10,20,50,100] as $count)
                                <option value="{{ $count }}" @selected($filters['top'] === $count)>Top {{ $count }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="stat-sort">Ordenar</label>
                        <select id="stat-sort" name="sort">
                            <option value="desc" @selected($filters['sort'] === 'desc')>Mayor a menor</option>
                            <option value="asc" @selected($filters['sort'] === 'asc')>Menor a mayor</option>
                        </select>
                    </div>
                </div>

                <div class="sqa-stats__actions">
                    <button type="submit">Generar estadística</button>
                    <a href="{{ route('statistics.index') }}">Restablecer</a>
                </div>
            </form>
            <nav class="sqa-stats__presets" aria-label="Consultas rápidas">
                <span>Consultas rápidas</span>
                <a href="{{ route('statistics.index', ['focus' => 'user', 'metric' => 'participations', 'group' => 'user', 'period' => '12m']) }}">Más activos del año</a>
                <a href="{{ route('statistics.index', ['focus' => 'role', 'metric' => 'participations', 'group' => 'role', 'period' => 'all']) }}">Roles más jugados</a>
                <a href="{{ route('statistics.index', ['focus' => 'activity_type', 'metric' => 'events', 'group' => 'month', 'period' => '12m']) }}">Eventos por mes</a>
                <a href="{{ route('statistics.index', ['focus' => 'editor', 'metric' => 'missions', 'group' => 'editor', 'period' => 'all']) }}">Eventos distintos por editor</a>
            </nav>
        </aside>

        <section class="sqa-stats__results" aria-label="Resultados de estadísticas">
            <div class="sqa-stats__results-header">
                <div>
                    <span class="sqa-stats__kicker">RESULTADOS</span>
                    @if($filters['metric'] === 'participations' && $filters['group'] === 'user' && $filters['role'] && isset($roles[$filters['role']]))
                        <h2>Jugadores que más han jugado de {{ $roles[$filters['role']] }}</h2>
                    @else
                        <h2>{{ $metricOptions[$filters['metric']] }}</h2>
                    @endif
                    <p>{{ \App\Services\PublicStatisticsService::FOCUSES[$filters['focus']] }} · Agrupado por {{ mb_strtolower($groupOptions[$filters['group']]) }} · {{ $periodOptions[$filters['period']] }}</p>
                </div>
                <span class="sqa-stats__pill">{{ count($rows) }} resultados</span>
            </div>

            @if(count($rows) > 0)
                @php($maximum = max(1, max(array_column($rows, 'total'))))
                <div class="sqa-stats__ranking">
                    @foreach($rows as $row)
                        <div class="sqa-stats__ranking-item">
                            <div class="sqa-stats__ranking-head">
                                <span class="sqa-stats__rank">{{ $loop->iteration }}</span>
                                @if($row['url'])
                                    <a href="{{ $row['url'] }}">{{ $row['label'] }}</a>
                                @else
                                    <span>{{ $row['label'] }}</span>
                                @endif
                                <strong>{{ number_format($row['total'], 0, ',', '.') }}</strong>
                            </div>
                            <div class="sqa-stats__bar"><span style="width: {{ max(0, min(100, 100 * $row['total'] / $maximum)) }}%"></span></div>
                        </div>
                    @endforeach
                </div>
                @if($filters['metric'] === 'participations' && $filters['group'] === 'role')
                    <p class="sqa-stats__hint">Pulsa un tipo de slot para ver el ranking de los jugadores que más lo han utilizado.</p>
                @endif
                <p class="sqa-stats__hint">Los resultados se calculan sobre eventos registrados, celebrados y no cancelados. Un jugador cuenta una sola vez por evento.</p>
            @else
                <div class="sqa-stats__empty">No hay resultados para los filtros seleccionados. Prueba con otro periodo o una categoría diferente.</div>
            @endif
            <div class="sqa-stats__notice">
                <strong>¿Buscas algo concreto?</strong>
                <p>Elige un tipo de slot para descubrir quién lo ha jugado más, o selecciona un editor para consultar sus eventos. Ajusta el periodo y combina filtros para afinar los resultados.</p>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/sqa-statistics-filters.js') }}?v={{ filemtime(public_path('js/sqa-statistics-filters.js')) }}" defer></script>
@endpush
