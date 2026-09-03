@extends('layouts.metopas')

@section('title', 'Actividades')

@section(
    'meta-description',
    'Listado de actividades de Squad ALPHA.'
)

@section('body-class', 'activities-body')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/activities.css') }}?v={{
            filemtime(public_path('css/activities.css'))
        }}"
    >
@endpush


@section('content')

    <section class="activities-hero">
        <div class="container">

            <span class="activities-kicker">
                Archivo de actividades
            </span>

            <div class="activities-hero__row">

                <div>
                    <h1>Actividades</h1>

                    <p>
                        Consulta las actividades creadas por y para miembros de Squad ALPHA
                    </p>
                </div>

                <div
                    class="activities-view-switcher"
                    role="group"
                    aria-label="Vista del listado"
                >
                    <button
                        type="button"
                        class="activities-view-button is-active"
                        data-activity-view="grid"
                        aria-pressed="true"
                        title="Vista en cuadrícula"
                    >
                        <span aria-hidden="true">
                            ▦
                        </span>

                        Cuadrícula
                    </button>

                    <button
                        type="button"
                        class="activities-view-button"
                        data-activity-view="list"
                        aria-pressed="false"
                        title="Vista en lista"
                    >
                        <span aria-hidden="true">
                            ☰
                        </span>

                        Lista
                    </button>
                </div>

            </div>

        </div>
    </section>


    {{-- =====================================================
        FILTROS
    ====================================================== --}}

    <section
        class="activities-filters-section"
        aria-label="Filtros de actividades"
    >
        <div class="container">
            @php
                $hasAdvancedFilters =
                    $selectedPlatformId
                    || $selectedEditorId
                    || $selectedTypeId
                    || $selectedStatusId
                    || $selectedMapId
                    || $selectedPeriodId
                    || $selectedCampaignId
                    || $selectedFactionId
                    || $selectedDayId
                    || filled($selectedDayOrNight)
                    || $selectedOcap !== null
                    || $selectedRespawn !== null
                    || $selectedJip !== null
                    || $selectedMulticlans !== null
                    || filled($selectedDateFrom)
                    || filled($selectedDateTo);
            @endphp
            <form
                method="GET"
                action="{{ route('activities.index') }}"
                class="activities-filters {{ $hasAdvancedFilters ? 'is-expanded' : '' }}"
                data-activities-filters
                data-has-advanced-filters="{{ $hasAdvancedFilters ? '1' : '0' }}"
            >
                {{-- Vista actual, para no perder cuadrícula/lista --}}
                @if(request('view'))
                    <input type="hidden" name="view" value="{{ request('view') }}">
                @endif

                <div class="activities-filters-basic">

                    <div class="activities-filter-field activities-filter-field--search">

                        <label for="activities-search">
                            Buscar
                        </label>

                        <input
                            id="activities-search"
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Nombre, mapa, campaña, facción, editor..."
                            autocomplete="off"
                        >

                    </div>

                    <div class="activities-filter-field activities-filter-field--sort">
                        <label for="activities-sort">Ordenar</label>
                        <select id="activities-sort" name="sort">
                            <option value="published_desc" @selected($selectedSort === 'published_desc')>Últimos publicados</option>
                            <option value="published_asc" @selected($selectedSort === 'published_asc')>Primeros publicados</option>
                            <option value="name_asc" @selected($selectedSort === 'name_asc')>Nombre A–Z</option>
                            <option value="name_desc" @selected($selectedSort === 'name_desc')>Nombre Z–A</option>
                        </select>
                    </div>

                    <button
                        type="button"
                        class="activities-filters-toggle"
                        data-activities-filters-toggle
                        aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}"
                        aria-controls="activities-advanced-filters"
                    >
                        <span
                            class="activities-filters-toggle__icon"
                            aria-hidden="true"
                        >
                            ⚙
                        </span>

                        <span data-activities-filters-toggle-label>
                            {{
                                $hasAdvancedFilters
                                    ? 'Ocultar filtros'
                                    : 'Más filtros'
                            }}
                        </span>
                    </button>

                    <button
                        type="submit"
                        class="activities-filter-submit activities-filter-submit--basic"
                    >
                        Filtrar
                    </button>

                </div>

                <div
                    id="activities-advanced-filters"
                    class="activities-filters-advanced"
                    data-activities-filters-advanced
                    @if(! $hasAdvancedFilters)
                        hidden
                    @endif
                >
                    {{-- PLATAFORMA --}}
                    <div class="activities-filter-field">
                        <label for="platform">Plataforma</label>
                        <select id="platform" name="platform">
                            <option value="">Todas las plataformas</option>
                            @foreach($platforms as $platform)
                                <option
                                    value="{{ $platform->id }}"
                                    @selected((string) request('platform') === (string) $platform->id)
                                >
                                    {{ $platform->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- EDITOR --}}
                    <div class="activities-filter-field">
                        <label for="editor">Editor</label>
                        <select id="editor" name="editor">
                            <option value="">Todos los editores</option>
                            @foreach($editors as $editor)
                                <option
                                    value="{{ $editor->id }}"
                                    @selected((string) request('editor') === (string) $editor->id)
                                >
                                    {{ $editor->nick }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- TIPO --}}
                    <div class="activities-filter-field">
                        <label for="type">Tipo</label>
                        <select id="type" name="type">
                            <option value="">Todos los tipos</option>
                            @foreach($activityTypes as $type)
                                <option
                                    value="{{ $type->id }}"
                                    @selected((string) request('type') === (string) $type->id)
                                >
                                    {{ $type->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- ESTADO --}}
                    <div class="activities-filter-field">
                        <label for="status">Estado</label>
                        <select id="status" name="status">
                            <option value="">Todos los estados</option>
                            @foreach($activityStatuses as $status)
                                <option
                                    value="{{ $status->id }}"
                                    @selected((string) request('status') === (string) $status->id)
                                >
                                    {{ $status->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- MAPA --}}
                    <div class="activities-filter-field">
                        <label for="map">Mapa</label>
                        <select id="map" name="map">
                            <option value="">Todos los mapas</option>
                            @foreach($maps as $map)
                                <option
                                    value="{{ $map->id }}"
                                    @selected(
                                        $selectedMapId === $map->id
                                    )
                                >
                                    {{ $map->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- PERIODO --}}
                    <div class="activities-filter-field">
                        <label for="period">Periodo</label>
                        <select id="period" name="period">
                            <option value="">Todos los periodos</option>
                            @foreach($periods as $period)
                                <option
                                    value="{{ $period->id }}"
                                    @selected(
                                        $selectedPeriodId === $period->id
                                    )
                                >
                                    {{ $period->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- CAMPAÑA --}}
                    <div class="activities-filter-field">
                        <label for="campaign">Campaña</label>
                        <select id="campaign" name="campaign">
                            <option value="">Todas las campañas</option>
                            @foreach($campaigns as $campaign)
                                <option
                                    value="{{ $campaign->id }}"
                                    @selected(
                                        $selectedCampaignId === $campaign->id
                                    )
                                >
                                    {{ $campaign->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- FACCIÓN ENEMIGA --}}
                    <div class="activities-filter-field">
                        <label for="faction">Facción enemiga</label>
                        <select id="faction" name="faction">
                            <option value="">Todas las facciones</option>
                            @foreach($factions as $faction)
                                <option
                                    value="{{ $faction->id }}"
                                    @selected(
                                        $selectedFactionId === $faction->id
                                    )
                                >
                                    {{ $faction->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="activities-filter-field">

                        <label for="activities-day">
                            Día de la semana
                        </label>

                        <select
                            id="activities-day"
                            name="day"
                        >
                            <option value="">
                                Todos los días
                            </option>

                            @foreach($activityDays as $day)
                                <option
                                    value="{{ $day->id }}"
                                    @selected(
                                        $selectedDayId === $day->id
                                    )
                                >
                                    {{ $day->name }}
                                </option>
                            @endforeach
                        </select>

                    </div>

                    {{-- AMBIENTACIÓN --}}
                    <div class="activities-filter-field">

                        <label for="activities-day-night">
                            Ambientación
                        </label>

                        <select
                            id="activities-day-night"
                            name="day_or_night"
                        >
                            <option value="">
                                Cualquiera
                            </option>

                            <option
                                value="day"
                                @selected(
                                    $selectedDayOrNight === 'day'
                                )
                            >
                                Día
                            </option>

                            <option
                                value="night"
                                @selected(
                                    $selectedDayOrNight === 'night'
                                )
                            >
                                Noche
                            </option>

                            <option
                                value="both"
                                @selected(
                                    $selectedDayOrNight === 'both'
                                )
                            >
                                Día y noche
                            </option>
                        </select>

                    </div>

                    {{-- OCAP --}}
                    <div class="activities-filter-field">
                        <label for="ocap">OCAP</label>
                        <select
                            id="activities-ocap"
                            name="ocap"
                        >
                            <option value="">Cualquiera</option>

                            <option
                                value="1"
                                @selected($selectedOcap === '1')
                            >
                                Sí
                            </option>

                            <option
                                value="0"
                                @selected($selectedOcap === '0')
                            >
                                No
                            </option>
                        </select>
                    </div>

                    {{-- RESPAWN --}}
                    <div class="activities-filter-field">
                        <label for="respawn">Respawn</label>
                        <select
                            id="activities-respawn"
                            name="respawn"
                        >
                            <option value="">Cualquiera</option>

                            <option
                                value="1"
                                @selected($selectedRespawn === '1')
                            >
                                Sí
                            </option>

                            <option
                                value="0"
                                @selected($selectedRespawn === '0')
                            >
                                No
                            </option>
                        </select>
                    </div>

                    {{-- JIP --}}
                    <div class="activities-filter-field">
                        <label for="jip">JIP</label>
                        <select
                            id="activities-jip"
                            name="jip"
                        >
                            <option value="">Cualquiera</option>

                            <option
                                value="1"
                                @selected($selectedJip === '1')
                            >
                                Sí
                            </option>

                            <option
                                value="0"
                                @selected($selectedJip === '0')
                            >
                                No
                            </option>
                        </select>
                    </div>

                    {{-- MULTICLANES --}}
                    <div class="activities-filter-field">
                        <label for="activities-multiclans">Multiclanes</label>
                        <select
                            id="activities-multiclans"
                            name="multiclans"
                        >
                            <option value="">Cualquiera</option>

                            <option
                                value="1"
                                @selected($selectedMulticlans === '1')
                            >
                                Sí
                            </option>

                            <option
                                value="0"
                                @selected($selectedMulticlans === '0')
                            >
                                No
                            </option>
                        </select>
                    </div>

                    {{-- DESDE --}}
                    <div class="activities-filter-field">

                        <label for="activities-date-from">
                            Desde
                        </label>

                        <input
                            id="activities-date-from"
                            type="date"
                            name="date_from"
                            value="{{ $selectedDateFrom }}"
                        >

                    </div>

                    {{-- HASTA --}}
                    <div class="activities-filter-field">

                        <label for="activities-date-to">
                            Hasta
                        </label>

                        <input
                            id="activities-date-to"
                            type="date"
                            name="date_to"
                            value="{{ $selectedDateTo }}"
                        >

                    </div>

                    <div class="activities-filter-actions activities-filter-actions--advanced">
                        <button
                            type="submit"
                            class="activities-filter-submit"
                        >
                            Filtrar
                        </button>

                        <a
                            href="{{ route('activities.index', array_filter(['view' => request('view')])) }}"
                            class="activities-filter-clear"
                        >
                            Limpiar
                        </a>
                    </div>
                </div>
            </form>

        </div>
    </section>


    {{-- =====================================================
        LISTADO
    ====================================================== --}}

    <section
        class="activities-list-section"
        aria-labelledby="activities-list-title"
    >
        <div class="container">

            @php
                $campaignCount = $activityItems
                    ->where('type', 'campaign')
                    ->count();
            @endphp


            <header class="activities-list-header">

                <div>

                    <span>
                        {{
                            $hasFilters
                                ? 'Resultados de búsqueda'
                                : 'Biblioteca de actividades'
                        }}
                    </span>


                    <h2 id="activities-list-title">

                        {{ $activities->count() }}

                        {{
                            $activities->count() === 1
                                ? 'actividad'
                                : 'actividades'
                        }}


                        @if($campaignCount > 0)

                            <small class="activities-list-header__campaigns">

                                · {{ $campaignCount }}

                                {{
                                    $campaignCount === 1
                                        ? 'campaña'
                                        : 'campañas'
                                }}

                            </small>

                        @endif

                    </h2>

                </div>

            </header>


            @if($activities->isEmpty())

                <div class="activities-empty">

                    @if($hasFilters)

                        <strong>
                            No hay resultados
                        </strong>

                        <p>
                            Ninguna actividad coincide con los filtros seleccionados.
                        </p>

                        <a
                            href="{{ route('activities.index') }}"
                            class="activities-empty__reset"
                        >
                            Quitar filtros
                        </a>

                    @else

                        <strong>
                            No hay actividades
                        </strong>

                        <p>
                            Todavía no se ha creado ninguna actividad.
                        </p>

                    @endif

                </div>

            @else

                <div
                    class="activities-catalog is-grid"
                    data-activities-catalog
                >

                    @foreach($activityItems as $item)

                        @php
                            $isCampaign =
                                $item['type'] === 'campaign';

                            $campaign =
                                $isCampaign
                                    ? $item['campaign']
                                    : null;

                            $activitiesToRender =
                                $isCampaign
                                    ? $activities
                                        ->where(
                                            'campaign_id',
                                            $campaign->id
                                        )
                                        ->values()
                                    : collect([
                                        $item['activity'],
                                    ]);

                            $campaignActivitiesCount =
                                $isCampaign
                                    ? $activitiesToRender->count()
                                    : 0;
                        @endphp


                        {{-- ==========================================
                            CAMPAÑA
                        =========================================== --}}

                        @if($isCampaign)
                            @php
                            /*
                            * La imagen de la campaña se obtiene
                            * automáticamente del primera actividad.
                            */

                            $campaignFirstActivity =
                                $campaign
                                    ->activities
                                    ->first();


                            $campaignThumbnail = null;


                            if (
                                $campaignFirstActivity
                                && filled(
                                    $campaignFirstActivity->image
                                )
                            ) {
                                $campaignThumbnail = asset(
                                    'storage/'
                                    . $campaignFirstActivity->image
                                );
                            } elseif (
                                $campaignFirstActivity
                                && filled(
                                    $campaignFirstActivity
                                        ->map
                                        ?->image
                                )
                            ) {
                                /*
                                * Igual que en las tarjetas normales:
                                * si el actividad no tiene imagen propia,
                                * usamos la imagen del mapa.
                                */

                                $campaignThumbnail = asset(
                                    'storage/'
                                    . $campaignFirstActivity
                                        ->map
                                        ->image
                                );
                            }
                        @endphp
                            <div
                                class="activity-campaign-card"
                                role="button"
                                tabindex="0"

                                data-campaign-toggle
                                data-campaign-id="{{ $campaign->id }}"

                                aria-expanded="false"
                                
                            >
                                <div class="activity-campaign-card__media">

                                    @if($campaignThumbnail)

                                        <img
                                            src="{{ $campaignThumbnail }}"
                                            alt="{{ $campaign->name }}"
                                            loading="lazy"
                                        >

                                    @else

                                        <div
                                            class="activity-campaign-card__placeholder"
                                        >

                                            <img
                                                src="{{ asset(
                                                    'images/sqa-shield-white.png'
                                                ) }}"
                                                alt=""
                                            >

                                        </div>

                                    @endif


                                    <div
                                        class="activity-campaign-card__media-overlay"
                                        aria-hidden="true"
                                    ></div>


                                    <span
                                        class="activity-campaign-card__media-badge"
                                    >
                                        Campaña
                                    </span>

                                </div>
                                    <div class="activity-campaign-card__main">


                                        <h3
                                            class="activity-campaign-card__title"
                                        >
                                            {{ $campaign->name }}
                                        </h3>


                                        @if(filled($campaign->description))

                                            <div
                                                class="activity-campaign-card__description"
                                            >
                                                {!! nl2br(
                                                    e(
                                                        strip_tags(
                                                            $campaign->description
                                                        )
                                                    )
                                                ) !!}
                                            </div>

                                        @else

                                            <p
                                                class="activity-campaign-card__description activity-campaign-card__description--empty"
                                            >
                                                Sin descripción.
                                            </p>

                                        @endif

                                    </div>


                                    <div
                                        class="activity-campaign-card__aside"
                                    >

                                        <div
                                            class="activity-campaign-card__meta"
                                        >

                                            <span
                                                @class([
                                                    'activity-campaign-card__persistent',
                                                    'is-persistent' =>
                                                        $campaign->persistent,
                                                ])
                                            >

                                                <span
                                                    class="activity-campaign-card__persistent-dot"
                                                    aria-hidden="true"
                                                ></span>

                                                {{
                                                    $campaign->persistent
                                                        ? 'Persistente'
                                                        : 'No persistente'
                                                }}

                                            </span>


                                            <span
                                                class="activity-campaign-card__count"
                                            >
                                                {{ $campaignActivitiesCount }}

                                                {{
                                                    $campaignActivitiesCount === 1
                                                        ? 'actividad'
                                                        : 'actividades'
                                                }}
                                            </span>

                                        </div>


                                        <span
                                            class="activity-campaign-card__toggle"
                                            aria-hidden="true"
                                        >
                                            <span
                                                class="activity-campaign-card__toggle-icon"
                                            >
                                                ↓
                                            </span>
                                        </span>

                                    </div>


                                    </div>

                            @endif


                        {{-- ==========================================
                            ACTIVIDADES
                        =========================================== --}}

                        @foreach($activitiesToRender as $activity)

                            @php
                                $thumbnail = null;

                                if (filled($activity->image)) {
                                    $thumbnail = asset(
                                        'storage/' . $activity->image
                                    );
                                } elseif (
                                    filled(
                                        $activity->map?->image
                                    )
                                ) {
                                    $thumbnail = asset(
                                        'storage/' . $activity->map->image
                                    );
                                }

                                $activityColor =
                                    $activity
                                        ->activityType
                                        ?->color
                                    ?: '#f59e0b';
                            @endphp


                            <div
                                @class([
                                    'activity-card-wrapper',

                                    'activity-card-wrapper--campaign' =>
                                        $isCampaign,
                                ])

                                @if($isCampaign)
                                    data-campaign-child="{{ $campaign->id }}"
                                    hidden
                                @endif
                            >

                                @if($isCampaign)

                                    <span
                                        class="activity-card-connector"
                                        aria-hidden="true"
                                    ></span>

                                @endif


                                <article
                                    @class([
                                        'activity-card',
                                        'activity-card--campaign-child' =>
                                            $isCampaign,
                                    ])
                                    style="
                                        --activity-color:
                                        {{ $activityColor }};
                                    "
                                >

                                    <a
                                        href="{{ route(
                                            'activities.show',
                                            $activity
                                        ) }}"
                                        class="activity-card__link"
                                        aria-label="Ver actividad {{ $activity->name }}"
                                    ></a>


                                    <div
                                        class="activity-card__media"
                                    >

                                        @if($thumbnail)

                                            <img
                                                src="{{ $thumbnail }}"
                                                alt="{{ $activity->name }}"
                                                loading="lazy"
                                            >

                                        @else

                                            <div
                                                class="activity-card__placeholder"
                                            >

                                                <img
                                                    src="{{ asset(
                                                        'images/sqa-shield-white.png'
                                                    ) }}"
                                                    alt=""
                                                >

                                            </div>

                                        @endif


                                        <div
                                            class="activity-card__media-overlay"
                                        ></div>


                                        @if($activity->activityType)

                                            <span
                                                class="activity-card__type"
                                            >
                                                {{
                                                    $activity
                                                        ->activityType
                                                        ->name
                                                }}
                                            </span>

                                        @endif


                                        @if($activity->activityStatus)

                                            <span
                                                class="activity-card__status"
                                                @style([
                                                    '--status-color: ' . $activity->activityStatus?->color
                                                        => filled($activity->activityStatus?->color),
                                                ])
                                            >
                                                {{
                                                    $activity
                                                        ->activityStatus
                                                        ->name
                                                }}
                                            </span>

                                        @endif

                                    </div>


                                    <div
                                        class="activity-card__body"
                                    >

                                        <div
                                            class="activity-card__heading"
                                        >

                                            <div>

                                                <h3>
                                                    {{ $activity->name }}
                                                </h3>


                                                {{--
                                                    Solo mostramos el badge
                                                    de campaña si el actividad
                                                    estuviera fuera de un grupo.

                                                    Dentro del desplegable sería
                                                    redundante.
                                                --}}

                                                @if(
                                                    ! $isCampaign
                                                    && $activity->campaign
                                                )

                                                    <div
                                                        class="activity-card__campaign"
                                                    >

                                                        <span
                                                            class="activity-card__campaign-badge"
                                                        >
                                                            Campaña
                                                        </span>

                                                        <span
                                                            class="activity-card__campaign-name"
                                                        >
                                                            {{
                                                                $activity
                                                                    ->campaign
                                                                    ->name
                                                            }}
                                                        </span>

                                                    </div>

                                                @endif

                                            </div>


                                            <span
                                                class="activity-card__arrow"
                                                aria-hidden="true"
                                            >
                                                →
                                            </span>

                                        </div>


                                        <dl
                                            class="activity-card__facts"
                                        >

                                            {{-- Plataforma --}}

                                            @if($activity->platform)

                                                <div>

                                                    <dt>
                                                        Plataforma
                                                    </dt>

                                                    <dd>

                                                        @if(
                                                            $activity
                                                                ->platform
                                                                ->image
                                                        )

                                                            <img
                                                                src="{{ asset(
                                                                    'storage/'
                                                                    . $activity
                                                                        ->platform
                                                                        ->image
                                                                ) }}"
                                                                alt=""
                                                                aria-hidden="true"
                                                                loading="lazy"
                                                            >

                                                        @endif

                                                        <span>
                                                            {{
                                                                $activity
                                                                    ->platform
                                                                    ->name
                                                            }}
                                                        </span>

                                                    </dd>

                                                </div>

                                            @endif


                                            {{-- Mapa --}}

                                            @if($activity->map)

                                                <div>

                                                    <dt>
                                                        Mapa
                                                    </dt>

                                                    <dd>

                                                        <a
                                                            href="{{ route(
                                                                'maps.show',
                                                                $activity->map
                                                            ) }}"
                                                            class="activity-card__fact-link"
                                                            title="Ver mapa {{ $activity->map->name }}"
                                                        >
                                                            {{
                                                                $activity
                                                                    ->map
                                                                    ->name
                                                            }}
                                                        </a>

                                                    </dd>

                                                </div>

                                            @endif


                                            {{-- Periodo --}}

                                            @if($activity->period)

                                                <div>

                                                    <dt>
                                                        Periodo
                                                    </dt>

                                                    <dd>

                                                        @if(
                                                            $activity
                                                                ->period
                                                                ->ico
                                                        )

                                                            <img
                                                                src="{{ asset(
                                                                    'storage/'
                                                                    . $activity
                                                                        ->period
                                                                        ->ico
                                                                ) }}"
                                                                alt=""
                                                                aria-hidden="true"
                                                                loading="lazy"
                                                            >

                                                        @endif

                                                        <span>
                                                            {{
                                                                $activity
                                                                    ->period
                                                                    ->name
                                                            }}
                                                        </span>

                                                    </dd>

                                                </div>

                                            @endif


                                            {{-- Editor --}}

                                            @if($activity->editor || $activity->editorAlly)

                                                <div>

                                                    <dt>
                                                        Editor
                                                    </dt>

                                                    <dd>

                                                        @if($activity->editor)
                                                            <a
                                                                href="{{ route(
                                                                    'users.show',
                                                                    $activity->editor
                                                                ) }}"
                                                                class="activity-card__editor"
                                                                style="
                                                                    --member-group-color:
                                                                    {{
                                                                        $activity
                                                                            ->editor
                                                                            ->getFrontendColor()
                                                                    }};
                                                                "
                                                            >
                                                                {{ $activity->editor->nick }}
                                                            </a>
                                                        @else
                                                            <span class="activity-card__editor">
                                                                {{ $activity->editorAlly->name }}
                                                            </span>
                                                        @endif

                                                    </dd>

                                                </div>

                                            @endif

                                        </dl>


                                        @if(
                                            ($activity->activityType?->supportsOcap() ?? false)
                                            || ($activity->activityType?->supportsRespawn() ?? false)
                                            || ($activity->activityType?->supportsJip() ?? false)
                                        )
                                            <div class="activity-card__options">
                                                @if($activity->activityType?->supportsOcap())
                                                    <span @class(['is-enabled' => $activity->ocap])>OCAP</span>
                                                @endif

                                                @if($activity->activityType?->supportsRespawn())
                                                    <span @class(['is-enabled' => $activity->respawn])>Respawn</span>
                                                @endif

                                                @if($activity->activityType?->supportsJip())
                                                    <span @class(['is-enabled' => $activity->jip])>JIP</span>
                                                @endif
                                            </div>
                                        @endif

                                    </div>

                                </article>

                            </div>

                        @endforeach

                    @endforeach

                </div>

            @endif

        </div>
    </section>

@endsection


@push('scripts')
    <script
        src="{{ asset('js/activities.js') }}?v={{
            filemtime(public_path('js/activities.js'))
        }}"
        defer
    ></script>
@endpush
