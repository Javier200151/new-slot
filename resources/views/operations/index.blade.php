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
        href="{{ asset('css/operations.css') }}"
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
                        <span aria-hidden="true">▦</span>
                        Cuadrícula
                    </button>

                    <button
                        type="button"
                        class="operations-view-button"
                        data-operation-view="list"
                        aria-pressed="false"
                        title="Vista en lista"
                    >
                        <span aria-hidden="true">☰</span>
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

            <form
                method="GET"
                action="{{ route('operations.index') }}"
                class="operations-filters"
            >

                {{-- Plataforma --}}

                <div class="operations-filter-field">

                    <label for="operations-platform">
                        Plataforma
                    </label>

                    <select
                        id="operations-platform"
                        name="platform"
                    >
                        <option value="">
                            Todas las plataformas
                        </option>

                        @foreach($platforms as $platform)

                            <option
                                value="{{ $platform->id }}"
                                @selected(
                                    $selectedPlatformId === $platform->id
                                )
                            >
                                {{ $platform->name }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- Editor --}}

                <div class="operations-filter-field">

                    <label for="operations-editor">
                        Editor
                    </label>

                    <select
                        id="operations-editor"
                        name="editor"
                    >
                        <option value="">
                            Todos los editores
                        </option>

                        @foreach($editors as $editor)

                            <option
                                value="{{ $editor->id }}"
                                @selected(
                                    $selectedEditorId === $editor->id
                                )
                            >
                                {{ $editor->nick }}
                            </option>

                        @endforeach

                    </select>

                </div>


                {{-- Fecha desde --}}

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


                {{-- Fecha hasta --}}

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


                {{-- Acciones --}}

                <div class="operations-filter-actions">

                    <button
                        type="submit"
                        class="operations-filter-submit"
                    >
                        Filtrar
                    </button>

                    @if($hasFilters)

                        <a
                            href="{{ route('operations.index') }}"
                            class="operations-filter-clear"
                        >
                            Limpiar
                        </a>

                    @endif

                </div>

            </form>

        </div>
    </section>
    <section
        class="operations-list-section"
        aria-labelledby="operations-list-title"
    >
        <div class="container">

            <header class="operations-list-header">

                <div>

                    <span>
                        {{ $hasFilters
                            ? 'Resultados de búsqueda'
                            : 'Biblioteca de operativos'
                        }}
                    </span>

                    <h2 id="operations-list-title">

                        {{ $operations->count() }}

                        {{ $operations->count() === 1
                            ? 'operativo'
                            : 'operativos'
                        }}

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

                    @foreach($operations as $operation)

                        @php
                            $thumbnail = null;

                            if (filled($operation->image)) {
                                $thumbnail = asset(
                                    'storage/' . $operation->image
                                );
                            } elseif (filled($operation->map?->image)) {
                                $thumbnail = asset(
                                    'storage/' . $operation->map->image
                                );
                            }

                            $operationColor =
                                $operation->operationType?->color
                                ?: '#f59e0b';
                        @endphp

                        <article
                            class="operation-card"
                            style="--operation-color: {{ $operationColor }};"
                        >

                            <a
                                href="{{ route('operations.show', $operation) }}"
                                class="operation-card__link"
                                aria-label="Ver operativo {{ $operation->name }}"
                            ></a>


                            <div class="operation-card__media">

                                @if($thumbnail)

                                    <img
                                        src="{{ $thumbnail }}"
                                        alt="{{ $operation->name }}"
                                        loading="lazy"
                                    >

                                @else

                                    <div class="operation-card__placeholder">

                                        <img
                                            src="{{ asset('images/sqa-shield-white.png') }}"
                                            alt=""
                                        >

                                    </div>

                                @endif


                                <div class="operation-card__media-overlay"></div>


                                @if($operation->operationType)

                                    <span class="operation-card__type">
                                        {{ $operation->operationType->name }}
                                    </span>

                                @endif


                                @if($operation->operationStatus)

                                    <span class="operation-card__status">
                                        {{ $operation->operationStatus->name }}
                                    </span>

                                @endif

                            </div>


                            <div class="operation-card__body">

                                <div class="operation-card__heading">

                                    <div>

                                        <h3>
                                            {{ $operation->name }}
                                        </h3>

                                        @if($operation->campaign)

                                            <div class="operation-card__campaign">

                                                <span class="operation-card__campaign-badge">
                                                    Campaña
                                                </span>

                                                <span class="operation-card__campaign-name">
                                                    {{ $operation->campaign->name }}
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


                                <dl class="operation-card__facts">

                                    {{-- Plataforma --}}
                                    @if($operation->platform)

                                        <div>
                                            <dt>Plataforma</dt>

                                            <dd>

                                                @if($operation->platform->image)

                                                    <img
                                                        src="{{ asset(
                                                            'storage/' . $operation->platform->image
                                                        ) }}"
                                                        alt=""
                                                        aria-hidden="true"
                                                        loading="lazy"
                                                    >

                                                @endif

                                                <span>
                                                    {{ $operation->platform->name }}
                                                </span>

                                            </dd>
                                        </div>

                                    @endif


                                    {{-- Mapa --}}
                                    @if($operation->map)

                                        <div>
                                            <dt>Mapa</dt>

                                            <dd>

                                                <a
                                                    href="{{ route(
                                                        'maps.show',
                                                        $operation->map
                                                    ) }}"
                                                    class="operation-card__fact-link"
                                                    title="Ver mapa {{ $operation->map->name }}"
                                                >
                                                    {{ $operation->map->name }}
                                                </a>

                                            </dd>
                                        </div>

                                    @endif


                                    {{-- Periodo --}}
                                    @if($operation->period)

                                        <div>
                                            <dt>Periodo</dt>

                                            <dd>

                                                @if($operation->period->ico)

                                                    <img
                                                        src="{{ asset(
                                                            'storage/' . $operation->period->ico
                                                        ) }}"
                                                        alt=""
                                                        aria-hidden="true"
                                                        loading="lazy"
                                                    >

                                                @endif

                                                <span>
                                                    {{ $operation->period->name }}
                                                </span>

                                            </dd>
                                        </div>

                                    @endif


                                    {{-- Editor --}}
                                    @if($operation->editor)

                                        <div>
                                            <dt>Editor</dt>

                                            <dd>

                                                <a
                                                    href="{{ route(
                                                        'users.show',
                                                        $operation->editor
                                                    ) }}"
                                                    class="operation-card__editor"
                                                    style="
                                                        --member-group-color:
                                                        {{ $operation->editor->getFrontendColor() }};
                                                    "
                                                >
                                                    {{ $operation->editor->nick }}
                                                </a>

                                            </dd>
                                        </div>

                                    @endif

                                </dl>


                                <div class="operation-card__options">

                                    <span
                                        @class([
                                            'is-enabled' => $operation->ocap,
                                        ])
                                    >
                                        OCAP
                                    </span>

                                    <span
                                        @class([
                                            'is-enabled' => $operation->respawn,
                                        ])
                                    >
                                        Respawn
                                    </span>

                                    <span
                                        @class([
                                            'is-enabled' => $operation->jip,
                                        ])
                                    >
                                        JIP
                                    </span>

                                </div>

                            </div>

                        </article>

                    @endforeach

                </div>

            @endif

        </div>
    </section>

@endsection


@push('scripts')
    <script
        src="{{ asset('js/operations.js') }}"
        defer
    ></script>
@endpush