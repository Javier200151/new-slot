@extends('layouts.metopas')

@section('title', 'Operativos')

@section(
    'meta-description',
    'Listado de operativos de Squad ALPHA.'
)

@section('body-class', 'operations-body')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/operations.css') }}?v={{
            filemtime(public_path('css/operations.css'))
        }}"
    >
@endpush


@section('content')

    <section class="operations-hero">
        <div class="container">

            <span class="operations-kicker">
                Archivo operativo
            </span>

            <div class="operations-hero__row">

                <div>
                    <h1>Operativos</h1>

                    <p>
                        Consulta los operativos creados por y para miembros de Squad ALPHA
                    </p>
                </div>

                <div
                    class="operations-view-switcher"
                    role="group"
                    aria-label="Vista del listado"
                >
                    <button
                        type="button"
                        class="operations-view-button is-active"
                        data-operation-view="grid"
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
                        class="operations-view-button"
                        data-operation-view="list"
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
        class="operations-filters-section"
        aria-label="Filtros de operativos"
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
                    || filled($selectedDateFrom)
                    || filled($selectedDateTo);
            @endphp
            <form
                method="GET"
                action="{{ route('operations.index') }}"
                class="operations-filters {{ $hasAdvancedFilters ? 'is-expanded' : '' }}"
                data-operations-filters
                data-has-advanced-filters="{{ $hasAdvancedFilters ? '1' : '0' }}"
            >
                {{-- Vista actual, para no perder cuadrícula/lista --}}
                @if(request('view'))
                    <input type="hidden" name="view" value="{{ request('view') }}">
                @endif

                <div class="operations-filters-basic">

                    <div class="operations-filter-field operations-filter-field--search">

                        <label for="operations-search">
                            Buscar
                        </label>

                        <input
                            id="operations-search"
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Nombre, mapa, campaña, facción, editor..."
                            autocomplete="off"
                        >

                    </div>

                    <button
                        type="button"
                        class="operations-filters-toggle"
                        data-operations-filters-toggle
                        aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}"
                        aria-controls="operations-advanced-filters"
                    >
                        <span
                            class="operations-filters-toggle__icon"
                            aria-hidden="true"
                        >
                            ⚙
                        </span>

                        <span data-operations-filters-toggle-label>
                            {{
                                $hasAdvancedFilters
                                    ? 'Ocultar filtros'
                                    : 'Más filtros'
                            }}
                        </span>
                    </button>

                    <button
                        type="submit"
                        class="operations-filter-submit operations-filter-submit--basic"
                    >
                        Filtrar
                    </button>

                </div>

                <div
                    id="operations-advanced-filters"
                    class="operations-filters-advanced"
                    data-operations-filters-advanced
                    @if(! $hasAdvancedFilters)
                        hidden
                    @endif
                >
                    {{-- PLATAFORMA --}}
                    <div class="operations-filter-field">
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
                    <div class="operations-filter-field">
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
                    <div class="operations-filter-field">
                        <label for="type">Tipo</label>
                        <select id="type" name="type">
                            <option value="">Todos los tipos</option>
                            @foreach($operationTypes as $type)
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
                    <div class="operations-filter-field">
                        <label for="status">Estado</label>
                        <select id="status" name="status">
                            <option value="">Todos los estados</option>
                            @foreach($operationStatuses as $status)
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
                    <div class="operations-filter-field">
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
                    <div class="operations-filter-field">
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
                    <div class="operations-filter-field">
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
                    <div class="operations-filter-field">
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

                    <div class="operations-filter-field">

                        <label for="operations-day">
                            Día de la semana
                        </label>

                        <select
                            id="operations-day"
                            name="day"
                        >
                            <option value="">
                                Todos los días
                            </option>

                            @foreach($operationDays as $day)
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
                    <div class="operations-filter-field">

                        <label for="operations-day-night">
                            Ambientación
                        </label>

                        <select
                            id="operations-day-night"
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
                    <div class="operations-filter-field">
                        <label for="ocap">OCAP</label>
                        <select
                            id="operations-ocap"
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
                    <div class="operations-filter-field">
                        <label for="respawn">Respawn</label>
                        <select
                            id="operations-respawn"
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
                    <div class="operations-filter-field">
                        <label for="jip">JIP</label>
                        <select
                            id="operations-jip"
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

                    {{-- DESDE --}}
                    <div class="operations-filter-field">

                        <label for="operations-date-from">
                            Desde
                        </label>

                        <input
                            id="operations-date-from"
                            type="date"
                            name="date_from"
                            value="{{ $selectedDateFrom }}"
                        >

                    </div>

                    {{-- HASTA --}}
                    <div class="operations-filter-field">

                        <label for="operations-date-to">
                            Hasta
                        </label>

                        <input
                            id="operations-date-to"
                            type="date"
                            name="date_to"
                            value="{{ $selectedDateTo }}"
                        >

                    </div>

                    <div class="operations-filter-actions operations-filter-actions--advanced">
                        <button
                            type="submit"
                            class="operations-filter-submit"
                        >
                            Filtrar
                        </button>

                        <a
                            href="{{ route('operations.index', array_filter(['view' => request('view')])) }}"
                            class="operations-filter-clear"
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
        class="operations-list-section"
        aria-labelledby="operations-list-title"
    >
        <div class="container">

            @php
                $campaignCount = $operationItems
                    ->where('type', 'campaign')
                    ->count();
            @endphp


            <header class="operations-list-header">

                <div>

                    <span>
                        {{
                            $hasFilters
                                ? 'Resultados de búsqueda'
                                : 'Biblioteca de operativos'
                        }}
                    </span>


                    <h2 id="operations-list-title">

                        {{ $operations->count() }}

                        {{
                            $operations->count() === 1
                                ? 'operativo'
                                : 'operativos'
                        }}


                        @if($campaignCount > 0)

                            <small class="operations-list-header__campaigns">

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


            @if($operations->isEmpty())

                <div class="operations-empty">

                    @if($hasFilters)

                        <strong>
                            No hay resultados
                        </strong>

                        <p>
                            Ningún operativo coincide con los filtros seleccionados.
                        </p>

                        <a
                            href="{{ route('operations.index') }}"
                            class="operations-empty__reset"
                        >
                            Quitar filtros
                        </a>

                    @else

                        <strong>
                            No hay operativos
                        </strong>

                        <p>
                            Todavía no se ha creado ningún operativo.
                        </p>

                    @endif

                </div>

            @else

                <div
                    class="operations-catalog is-grid"
                    data-operations-catalog
                >

                    @foreach($operationItems as $item)

                        @php
                            $isCampaign =
                                $item['type'] === 'campaign';

                            $campaign =
                                $isCampaign
                                    ? $item['campaign']
                                    : null;

                            $operationsToRender =
                                $isCampaign
                                    ? $operations
                                        ->where(
                                            'campaign_id',
                                            $campaign->id
                                        )
                                        ->values()
                                    : collect([
                                        $item['operation'],
                                    ]);

                            $campaignOperationsCount =
                                $isCampaign
                                    ? $operationsToRender->count()
                                    : 0;
                        @endphp


                        {{-- ==========================================
                            CAMPAÑA
                        =========================================== --}}

                        @if($isCampaign)
                            @php
                            /*
                            * La imagen de la campaña se obtiene
                            * automáticamente del primer operativo.
                            */

                            $campaignFirstOperation =
                                $campaign
                                    ->operations
                                    ->first();


                            $campaignThumbnail = null;


                            if (
                                $campaignFirstOperation
                                && filled(
                                    $campaignFirstOperation->image
                                )
                            ) {
                                $campaignThumbnail = asset(
                                    'storage/'
                                    . $campaignFirstOperation->image
                                );
                            } elseif (
                                $campaignFirstOperation
                                && filled(
                                    $campaignFirstOperation
                                        ->map
                                        ?->image
                                )
                            ) {
                                /*
                                * Igual que en las tarjetas normales:
                                * si el operativo no tiene imagen propia,
                                * usamos la imagen del mapa.
                                */

                                $campaignThumbnail = asset(
                                    'storage/'
                                    . $campaignFirstOperation
                                        ->map
                                        ->image
                                );
                            }
                        @endphp
                            <div
                                class="operation-campaign-card"
                                role="button"
                                tabindex="0"

                                data-campaign-toggle
                                data-campaign-id="{{ $campaign->id }}"

                                aria-expanded="false"
                                
                            >
                                <div class="operation-campaign-card__media">

                                    @if($campaignThumbnail)

                                        <img
                                            src="{{ $campaignThumbnail }}"
                                            alt="{{ $campaign->name }}"
                                            loading="lazy"
                                        >

                                    @else

                                        <div
                                            class="operation-campaign-card__placeholder"
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
                                        class="operation-campaign-card__media-overlay"
                                        aria-hidden="true"
                                    ></div>


                                    <span
                                        class="operation-campaign-card__media-badge"
                                    >
                                        Campaña
                                    </span>

                                </div>
                                    <div class="operation-campaign-card__main">


                                        <h3
                                            class="operation-campaign-card__title"
                                        >
                                            {{ $campaign->name }}
                                        </h3>


                                        @if(filled($campaign->description))

                                            <div
                                                class="operation-campaign-card__description"
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
                                                class="operation-campaign-card__description operation-campaign-card__description--empty"
                                            >
                                                Sin descripción.
                                            </p>

                                        @endif

                                    </div>


                                    <div
                                        class="operation-campaign-card__aside"
                                    >

                                        <div
                                            class="operation-campaign-card__meta"
                                        >

                                            <span
                                                @class([
                                                    'operation-campaign-card__persistent',
                                                    'is-persistent' =>
                                                        $campaign->persistent,
                                                ])
                                            >

                                                <span
                                                    class="operation-campaign-card__persistent-dot"
                                                    aria-hidden="true"
                                                ></span>

                                                {{
                                                    $campaign->persistent
                                                        ? 'Persistente'
                                                        : 'No persistente'
                                                }}

                                            </span>


                                            <span
                                                class="operation-campaign-card__count"
                                            >
                                                {{ $campaignOperationsCount }}

                                                {{
                                                    $campaignOperationsCount === 1
                                                        ? 'operativo'
                                                        : 'operativos'
                                                }}
                                            </span>

                                        </div>


                                        <span
                                            class="operation-campaign-card__toggle"
                                            aria-hidden="true"
                                        >
                                            <span
                                                class="operation-campaign-card__toggle-icon"
                                            >
                                                ↓
                                            </span>
                                        </span>

                                    </div>


                                    </div>

                            @endif


                        {{-- ==========================================
                            OPERATIVOS
                        =========================================== --}}

                        @foreach($operationsToRender as $operation)

                            @php
                                $thumbnail = null;

                                if (filled($operation->image)) {
                                    $thumbnail = asset(
                                        'storage/' . $operation->image
                                    );
                                } elseif (
                                    filled(
                                        $operation->map?->image
                                    )
                                ) {
                                    $thumbnail = asset(
                                        'storage/' . $operation->map->image
                                    );
                                }

                                $operationColor =
                                    $operation
                                        ->operationType
                                        ?->color
                                    ?: '#f59e0b';
                            @endphp


                            <div
                                @class([
                                    'operation-card-wrapper',

                                    'operation-card-wrapper--campaign' =>
                                        $isCampaign,
                                ])

                                @if($isCampaign)
                                    data-campaign-child="{{ $campaign->id }}"
                                    hidden
                                @endif
                            >

                                @if($isCampaign)

                                    <span
                                        class="operation-card-connector"
                                        aria-hidden="true"
                                    ></span>

                                @endif


                                <article
                                    @class([
                                        'operation-card',
                                        'operation-card--campaign-child' =>
                                            $isCampaign,
                                    ])
                                    style="
                                        --operation-color:
                                        {{ $operationColor }};
                                    "
                                >

                                    <a
                                        href="{{ route(
                                            'operations.show',
                                            $operation
                                        ) }}"
                                        class="operation-card__link"
                                        aria-label="Ver operativo {{ $operation->name }}"
                                    ></a>


                                    <div
                                        class="operation-card__media"
                                    >

                                        @if($thumbnail)

                                            <img
                                                src="{{ $thumbnail }}"
                                                alt="{{ $operation->name }}"
                                                loading="lazy"
                                            >

                                        @else

                                            <div
                                                class="operation-card__placeholder"
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
                                            class="operation-card__media-overlay"
                                        ></div>


                                        @if($operation->operationType)

                                            <span
                                                class="operation-card__type"
                                            >
                                                {{
                                                    $operation
                                                        ->operationType
                                                        ->name
                                                }}
                                            </span>

                                        @endif


                                        @if($operation->operationStatus)

                                            <span
                                                class="operation-card__status"
                                            >
                                                {{
                                                    $operation
                                                        ->operationStatus
                                                        ->name
                                                }}
                                            </span>

                                        @endif

                                    </div>


                                    <div
                                        class="operation-card__body"
                                    >

                                        <div
                                            class="operation-card__heading"
                                        >

                                            <div>

                                                <h3>
                                                    {{ $operation->name }}
                                                </h3>


                                                {{--
                                                    Solo mostramos el badge
                                                    de campaña si el operativo
                                                    estuviera fuera de un grupo.

                                                    Dentro del desplegable sería
                                                    redundante.
                                                --}}

                                                @if(
                                                    ! $isCampaign
                                                    && $operation->campaign
                                                )

                                                    <div
                                                        class="operation-card__campaign"
                                                    >

                                                        <span
                                                            class="operation-card__campaign-badge"
                                                        >
                                                            Campaña
                                                        </span>

                                                        <span
                                                            class="operation-card__campaign-name"
                                                        >
                                                            {{
                                                                $operation
                                                                    ->campaign
                                                                    ->name
                                                            }}
                                                        </span>

                                                    </div>

                                                @endif

                                            </div>


                                            <span
                                                class="operation-card__arrow"
                                                aria-hidden="true"
                                            >
                                                →
                                            </span>

                                        </div>


                                        <dl
                                            class="operation-card__facts"
                                        >

                                            {{-- Plataforma --}}

                                            @if($operation->platform)

                                                <div>

                                                    <dt>
                                                        Plataforma
                                                    </dt>

                                                    <dd>

                                                        @if(
                                                            $operation
                                                                ->platform
                                                                ->image
                                                        )

                                                            <img
                                                                src="{{ asset(
                                                                    'storage/'
                                                                    . $operation
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
                                                                $operation
                                                                    ->platform
                                                                    ->name
                                                            }}
                                                        </span>

                                                    </dd>

                                                </div>

                                            @endif


                                            {{-- Mapa --}}

                                            @if($operation->map)

                                                <div>

                                                    <dt>
                                                        Mapa
                                                    </dt>

                                                    <dd>

                                                        <a
                                                            href="{{ route(
                                                                'maps.show',
                                                                $operation->map
                                                            ) }}"
                                                            class="operation-card__fact-link"
                                                            title="Ver mapa {{ $operation->map->name }}"
                                                        >
                                                            {{
                                                                $operation
                                                                    ->map
                                                                    ->name
                                                            }}
                                                        </a>

                                                    </dd>

                                                </div>

                                            @endif


                                            {{-- Periodo --}}

                                            @if($operation->period)

                                                <div>

                                                    <dt>
                                                        Periodo
                                                    </dt>

                                                    <dd>

                                                        @if(
                                                            $operation
                                                                ->period
                                                                ->ico
                                                        )

                                                            <img
                                                                src="{{ asset(
                                                                    'storage/'
                                                                    . $operation
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
                                                                $operation
                                                                    ->period
                                                                    ->name
                                                            }}
                                                        </span>

                                                    </dd>

                                                </div>

                                            @endif


                                            {{-- Editor --}}

                                            @if($operation->editor)

                                                <div>

                                                    <dt>
                                                        Editor
                                                    </dt>

                                                    <dd>

                                                        <a
                                                            href="{{ route(
                                                                'users.show',
                                                                $operation->editor
                                                            ) }}"
                                                            class="operation-card__editor"
                                                            style="
                                                                --member-group-color:
                                                                {{
                                                                    $operation
                                                                        ->editor
                                                                        ->getFrontendColor()
                                                                }};
                                                            "
                                                        >
                                                            {{
                                                                $operation
                                                                    ->editor
                                                                    ->nick
                                                            }}
                                                        </a>

                                                    </dd>

                                                </div>

                                            @endif

                                        </dl>


                                        <div
                                            class="operation-card__options"
                                        >

                                            <span
                                                @class([
                                                    'is-enabled' =>
                                                        $operation->ocap,
                                                ])
                                            >
                                                OCAP
                                            </span>

                                            <span
                                                @class([
                                                    'is-enabled' =>
                                                        $operation->respawn,
                                                ])
                                            >
                                                Respawn
                                            </span>

                                            <span
                                                @class([
                                                    'is-enabled' =>
                                                        $operation->jip,
                                                ])
                                            >
                                                JIP
                                            </span>

                                        </div>

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
        src="{{ asset('js/operations.js') }}?v={{
            filemtime(public_path('js/operations.js'))
        }}"
        defer
    ></script>
@endpush