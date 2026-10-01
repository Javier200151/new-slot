@extends('layouts.metopas')

@section('title', 'Usuarios')

@section(
    'meta-description',
    'Miembros y usuarios de Squad ALPHA.'
)

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/users.css') }}?v={{ filemtime(public_path('css/users.css')) }}"
    >
@endpush

@section('body-class', 'users-body')

@section('content')

    <section class="users-directory">
        <div class="container users-directory__container">

            <header class="users-directory__header">
                <div>
                    <span class="users-kicker">
                        COMUNIDAD
                    </span>

                    <h1>Usuarios</h1>

                    <p>
                        Busca miembros de Squad ALPHA y consulta
                        su perfil público.
                    </p>
                </div>
            </header>


            <div class="users-legends">
                @if($sqaGroups->isNotEmpty())
                    <details
                        class="users-color-legend {{ $colorBy === 'group' ? 'is-current' : '' }}"
                    >
                        <summary>
                            <span
                                class="users-color-legend__swatches"
                                aria-hidden="true"
                            >
                                @foreach($sqaGroups->take(4) as $group)
                                    <span
                                        style="--legend-color: {{ $group->color ?: '#94a3b8' }};"
                                    ></span>
                                @endforeach
                            </span>

                            <span>Grupos SQA</span>
                        </summary>

                        <div class="users-color-legend__panel">
                            <span class="users-color-legend__title">
                                Grupos SQA
                            </span>

                            <div class="users-color-legend__items">
                                @foreach($sqaGroups as $group)
                                    <span
                                        class="users-color-legend__item"
                                        style="--legend-color: {{ $group->color ?: '#94a3b8' }};"
                                    >
                                        <i aria-hidden="true"></i>
                                        {{ $group->name }}
                                    </span>
                                @endforeach
                            </div>

                            <small>
                                En modo Grupos, el nick utiliza el color del grupo principal.
                                Si no tiene grupo principal, se usa el color de su estado.
                            </small>
                        </div>
                    </details>
                @endif

                @if($statuses->isNotEmpty())
                    <details
                        class="users-color-legend {{ $colorBy === 'status' ? 'is-current' : '' }}"
                    >
                        <summary>
                            <span
                                class="users-color-legend__swatches"
                                aria-hidden="true"
                            >
                                @foreach($statuses->take(4) as $status)
                                    <span
                                        style="--legend-color: {{ $status->color ?: '#ffffff' }};"
                                    ></span>
                                @endforeach
                            </span>

                            <span>Estados</span>
                        </summary>

                        <div class="users-color-legend__panel">
                            <span class="users-color-legend__title">
                                Estados de usuario
                            </span>

                            <div class="users-color-legend__items">
                                @foreach($statuses as $status)
                                    <span
                                        class="users-color-legend__item"
                                        style="--legend-color: {{ $status->color ?: '#ffffff' }};"
                                    >
                                        <i aria-hidden="true"></i>
                                        {{ $status->name }}
                                    </span>
                                @endforeach
                            </div>

                            <small>
                                Los colores proceden de la configuración de Estados en Filament.
                            </small>
                        </div>
                    </details>
                @endif
            </div>


            <div class="users-toolbar">
                <form
                    method="GET"
                    action="{{ route('users.index') }}"
                    class="users-search"
                    role="search"
                >
                    <input type="hidden" name="color_by" value="{{ $colorBy }}">
                    <input type="hidden" name="sort" value="{{ $sortBy }}">
                    <input type="hidden" name="direction" value="{{ $sortDirection }}">

                    <label
                        for="users-search"
                        class="sr-only"
                    >
                        Buscar usuario
                    </label>

                    <input
                        id="users-search"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Buscar por nick..."
                        autocomplete="off"
                    >

                    <label
                        for="users-status"
                        class="sr-only"
                    >
                        Filtrar por estado
                    </label>

                    <select
                        id="users-status"
                        name="status"
                    >
                        <option value="">
                            Todos los estados
                        </option>

                        @foreach($statuses as $status)
                            <option
                                value="{{ $status->id }}"
                                @selected($selectedStatusId === (int) $status->id)
                            >
                                {{ $status->name }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit">
                        Aplicar
                    </button>

                    @if($search !== '' || $selectedStatusId !== null)
                        <a
                            href="{{ route('users.index', [
                                'color_by' => $colorBy,
                                'sort' => $sortBy,
                                'direction' => $sortDirection,
                            ]) }}"
                            class="users-search__clear"
                        >
                            Limpiar
                        </a>
                    @endif
                </form>
            </div>

            <div class="users-display-controls">
                <div class="users-control-group" aria-label="Ordenar usuarios por">
                    <span>Ordenar por</span>

                    <div class="users-control-switch">
                        <a
                            href="{{ route('users.index', array_filter([
                                'q' => $search !== '' ? $search : null,
                                'status' => $selectedStatusId,
                                'color_by' => $colorBy,
                                'sort' => 'promo',
                                'direction' => $sortDirection,
                            ])) }}"
                            class="{{ $sortBy === 'promo' ? 'is-active' : '' }}"
                            @if($sortBy === 'promo') aria-current="true" @endif
                        >
                            Promociones
                        </a>

                        <a
                            href="{{ route('users.index', array_filter([
                                'q' => $search !== '' ? $search : null,
                                'status' => $selectedStatusId,
                                'color_by' => $colorBy,
                                'sort' => 'alpha',
                                'direction' => $sortDirection,
                            ])) }}"
                            class="{{ $sortBy === 'alpha' ? 'is-active' : '' }}"
                            @if($sortBy === 'alpha') aria-current="true" @endif
                        >
                            Alfabético
                        </a>
                    </div>
                </div>

                <div class="users-control-group" aria-label="Sentido del orden">
                    <span>
                        {{ $sortBy === 'promo' ? 'Orden de promociones' : 'Orden alfabético' }}
                    </span>

                    <div class="users-control-switch users-control-switch--direction">
                        <a
                            href="{{ route('users.index', array_filter([
                                'q' => $search !== '' ? $search : null,
                                'status' => $selectedStatusId,
                                'color_by' => $colorBy,
                                'sort' => $sortBy,
                                'direction' => 'asc',
                            ])) }}"
                            class="{{ $sortDirection === 'asc' ? 'is-active' : '' }}"
                            @if($sortDirection === 'asc') aria-current="true" @endif
                        >
                            {{ $sortBy === 'promo' ? 'Antiguas primero' : 'A → Z' }}
                        </a>

                        <a
                            href="{{ route('users.index', array_filter([
                                'q' => $search !== '' ? $search : null,
                                'status' => $selectedStatusId,
                                'color_by' => $colorBy,
                                'sort' => $sortBy,
                                'direction' => 'desc',
                            ])) }}"
                            class="{{ $sortDirection === 'desc' ? 'is-active' : '' }}"
                            @if($sortDirection === 'desc') aria-current="true" @endif
                        >
                            {{ $sortBy === 'promo' ? 'Nuevas primero' : 'Z → A' }}
                        </a>
                    </div>
                </div>

                <div class="users-control-group" aria-label="Colorear usuarios por">
                    <span>Colorear por</span>

                    <div class="users-control-switch">
                        <a
                            href="{{ route('users.index', array_filter([
                                'q' => $search !== '' ? $search : null,
                                'status' => $selectedStatusId,
                                'color_by' => 'group',
                                'sort' => $sortBy,
                                'direction' => $sortDirection,
                            ])) }}"
                            class="{{ $colorBy === 'group' ? 'is-active' : '' }}"
                            @if($colorBy === 'group') aria-current="true" @endif
                        >
                            Grupos
                        </a>

                        <a
                            href="{{ route('users.index', array_filter([
                                'q' => $search !== '' ? $search : null,
                                'status' => $selectedStatusId,
                                'color_by' => 'status',
                                'sort' => $sortBy,
                                'direction' => $sortDirection,
                            ])) }}"
                            class="{{ $colorBy === 'status' ? 'is-active' : '' }}"
                            @if($colorBy === 'status') aria-current="true" @endif
                        >
                            Estados
                        </a>
                    </div>
                </div>
            </div>


            @if($search !== '' || $selectedStatusId !== null)
                <div class="users-results-summary">
                    @if($search !== '')
                        Resultados para
                        <strong>“{{ $search }}”</strong>
                    @endif

                    @if($selectedStatusId !== null)
                        @php
                            $selectedStatus = $statuses->firstWhere('id', $selectedStatusId);
                        @endphp

                        @if($search !== '')
                            <span aria-hidden="true">·</span>
                        @endif

                        Estado
                        <strong>{{ $selectedStatus?->name ?? 'desconocido' }}</strong>
                    @endif
                </div>
            @endif


            @if($users->isEmpty())

                <div class="users-empty">
                    <strong>
                        No se encontraron usuarios
                    </strong>

                    <p>
                        Prueba con otro nick o estado.
                    </p>
                </div>

            @else

                @php
                    $directoryGroups = $sortBy === 'promo'
                        ? $users->getCollection()->groupBy(
                            fn ($user) => $user->promo_id !== null
                                ? 'promo-' . $user->promo_id
                                : 'without-promo'
                        )
                        : collect(['alphabetical' => $users->getCollection()]);
                @endphp

                <div class="users-promo-list {{ $sortBy === 'alpha' ? 'is-alphabetical' : '' }}">
                    @foreach($directoryGroups as $promoKey => $directoryUsers)
                        @php
                            $promoId = $sortBy === 'promo' && str_starts_with((string) $promoKey, 'promo-')
                                ? (int) str_replace('promo-', '', (string) $promoKey)
                                : null;
                        @endphp

                        <section class="users-promo-group">
                            @if($sortBy === 'promo')
                                <header class="users-promo-group__header">
                                    <span>
                                        {{ $promoId !== null ? 'PROMOCIÓN' : 'SIN PROMOCIÓN' }}
                                    </span>

                                    <h2>
                                        {{ $promoId !== null ? $promoId : 'Otros usuarios' }}
                                    </h2>

                                    <small>
                                        {{ $directoryUsers->count() }}
                                        {{ $directoryUsers->count() === 1 ? 'usuario' : 'usuarios' }}
                                        en esta página
                                    </small>
                                </header>
                            @endif

                            <div class="users-grid">
                                @foreach($directoryUsers as $user)

                                    @php
                                        $avatar = $user->image
                                            ? asset('storage/' . $user->image)
                                            : asset('images/sqa-shield-white.png');

                                        $mainGroup = $user->mainSqaGroup;

                                        $userColor = $colorBy === 'status'
                                            ? $user->getStatusColor()
                                            : $user->getFrontendColor();
                                    @endphp

                                    <a
                                        href="{{ route(
                                            'users.show',
                                            ['user' => $user->nick]
                                        ) }}"
                                        class="user-card"
                                        style="--user-card-color: {{ $userColor }};"
                                    >

                                        <div class="user-card__avatar">
                                            <img
                                                src="{{ $avatar }}"
                                                alt="Imagen de {{ $user->nick }}"
                                                loading="lazy"
                                            >
                                        </div>

                                        <div class="user-card__content">

                                            <strong>
                                                {{ $user->nick }}
                                            </strong>

                                            <span>
                                                {{ $user->status?->name ?? 'Sin estado' }}
                                            </span>

                                            @if($mainGroup)
                                                <small>
                                                    {{ $mainGroup->name }}
                                                </small>
                                            @else
                                                <small>
                                                    Sin grupo principal
                                                </small>
                                            @endif

                                        </div>

                                        <span
                                            class="user-card__arrow"
                                            aria-hidden="true"
                                        >
                                            →
                                        </span>

                                    </a>

                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>


                @if($users->hasPages())
                    <nav
                        class="users-pagination"
                        aria-label="Paginación de usuarios"
                    >
                        @if($users->onFirstPage())
                            <span class="is-disabled">
                                ← Anterior
                            </span>
                        @else
                            <a href="{{ $users->previousPageUrl() }}">
                                ← Anterior
                            </a>
                        @endif

                        <span>
                            Página
                            {{ $users->currentPage() }}
                            de
                            {{ $users->lastPage() }}
                        </span>

                        @if($users->hasMorePages())
                            <a href="{{ $users->nextPageUrl() }}">
                                Siguiente →
                            </a>
                        @else
                            <span class="is-disabled">
                                Siguiente →
                            </span>
                        @endif
                    </nav>
                @endif

            @endif

        </div>
    </section>

@endsection
