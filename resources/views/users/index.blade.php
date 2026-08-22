@extends('layouts.metopas')

@section('title', 'Usuarios')

@section(
    'meta-description',
    'Miembros y usuarios de Squad ALPHA.'
)

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/users.css') }}"
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


            <form
                method="GET"
                action="{{ route('users.index') }}"
                class="users-search"
                role="search"
            >
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

                <button type="submit">
                    Buscar
                </button>

                @if($search !== '')
                    <a
                        href="{{ route('users.index') }}"
                        class="users-search__clear"
                    >
                        Limpiar
                    </a>
                @endif
            </form>


            @if($search !== '')
                <div class="users-results-summary">
                    Resultados para
                    <strong>
                        “{{ $search }}”
                    </strong>
                </div>
            @endif


            @if($users->isEmpty())

                <div class="users-empty">
                    <strong>
                        No se encontraron usuarios
                    </strong>

                    <p>
                        Prueba con otro nick.
                    </p>
                </div>

            @else

                <div class="users-grid">

                    @foreach($users as $user)

                        @php
                            $avatar = $user->image
                                ? asset(
                                    'storage/' . $user->image
                                )
                                : asset(
                                    'images/sqa-shield-white.png'
                                );
                        @endphp

                        <a
                            href="{{ route(
                                'users.show',
                                ['user' => $user->nick]
                            ) }}"
                            class="user-card"
                        >

                            <div class="user-card__avatar">
                                <img
                                    src="{{ $avatar }}"
                                    alt="Imagen de {{ $user->nick }}"
                                    loading="lazy"
                                >
                            </div>

                            <div class="user-card__content">

                                <strong
                                    @style([
                                        '--member-group-color: '
                                        . (
                                            $user
                                                ->mainSqaGroup
                                                ?->color
                                            ?? ''
                                        )
                                        => filled(
                                            $user
                                                ->mainSqaGroup
                                                ?->color
                                        ),
                                    ])
                                >
                                    {{ $user->nick }}
                                </strong>

                                <span>
                                    {{ $user->status?->name
                                        ?? 'Sin estado'
                                    }}
                                </span>

                                @if($user->mainSqaGroup)
                                    <small>
                                        {{ $user
                                            ->mainSqaGroup
                                            ->name
                                        }}
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
                            <a
                                href="{{ $users->previousPageUrl() }}"
                            >
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
                            <a
                                href="{{ $users->nextPageUrl() }}"
                            >
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