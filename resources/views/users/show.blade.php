@extends('layouts.metopas')

@section(
    'title',
    'Perfil de ' . $user->nick
)

@section(
    'meta-description',
    'Perfil público de '
    . $user->nick
    . ' en Squad ALPHA.'
)

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/users.css') }}"
    >
@endpush

@section('body-class', 'public-user-body')

@section('content')

    @php
        $avatar = $user->image
            ? asset('storage/' . $user->image)
            : asset('images/sqa-shield-white.png');

        $quote = trim(
            strip_tags(
                (string) $user->quote
            )
        );
    @endphp


    <article class="public-user-profile">
        <div class="container public-user-profile__container">

            <nav
                class="public-user-profile__breadcrumb"
                aria-label="Migas de pan"
            >
                <a href="{{ route('users.index') }}">
                    Usuarios
                </a>

                <span>/</span>

                <span>
                    {{ $user->nick }}
                </span>
            </nav>


            <section class="public-user-hero">

                <div class="public-user-hero__avatar">
                    <img
                        src="{{ $avatar }}"
                        alt="Imagen de {{ $user->nick }}"
                    >
                </div>

                <div class="public-user-hero__content">

                    <span class="users-kicker">
                        PERFIL SQUAD ALPHA
                    </span>

                    <h1
                        @style([
                            '--member-group-color: '
                            . (
                                $user->mainSqaGroup?->color
                                ?? ''
                            )
                            => filled(
                                $user->mainSqaGroup?->color
                            ),
                        ])
                    >
                        {{ $user->nick }}
                    </h1>

                    <div class="public-user-hero__badges">

                        @if($user->status)
                            <span>
                                {{ $user->status->name }}
                            </span>
                        @endif

                        @if($user->mainSqaGroup)
                            <span>
                                {{ $user->mainSqaGroup->name }}
                            </span>
                        @endif

                    </div>


                    @if($quote !== '')
                        <blockquote>
                            “{{ $quote }}”
                        </blockquote>
                    @endif

                </div>

            </section>


            <div class="public-user-grid">

                <section class="public-user-card">
                    <header>
                        <span>INFORMACIÓN SQA</span>
                        <h2>Datos del miembro</h2>
                    </header>

                    <dl>
                        <div>
                            <dt>Estado</dt>

                            <dd>
                                {{ $user->status?->name
                                    ?? 'Sin estado'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>Promo</dt>

                            <dd>
                                {{ $user->promo_id
                                    ? '#'
                                        . $user->promo_id
                                    : 'Sin promo'
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt>Miembro desde</dt>

                            <dd>
                                {{ $user->member_at
                                    ?->format('d/m/Y')
                                    ?? 'No indicado'
                                }}
                            </dd>
                        </div>

                        @if($user->mainSqaGroup)
                            <div>
                                <dt>Grupo</dt>

                                <dd>
                                    {{ $user
                                        ->mainSqaGroup
                                        ->name
                                    }}
                                </dd>
                            </div>
                        @endif

                    </dl>
                </section>


                <section class="public-user-card">
                    <header>
                        <span>RECONOCIMIENTOS</span>

                        <h2>
                            Metopas
                            <small>
                                {{ $user->metopas->count() }}
                            </small>
                        </h2>
                    </header>

                    @if($user->metopas->isEmpty())

                        <div class="public-user-metopas__empty">
                            Este usuario todavía no tiene
                            metopas.
                        </div>

                    @else

                        <div class="public-user-metopas">

                            @foreach($user->metopas as $metopa)

                                <a
                                    href="{{ route(
                                        'metopas.show',
                                        $metopa
                                    ) }}"
                                    title="{{ $metopa->name }}"
                                >
                                    @if($metopa->image)
                                        <img
                                            src="{{ asset(
                                                'storage/'
                                                . $metopa->image
                                            ) }}"
                                            alt="{{ $metopa->name }}"
                                            loading="lazy"
                                        >
                                    @endif

                                    <span>
                                        {{ $metopa->name }}
                                    </span>
                                </a>

                            @endforeach

                        </div>

                    @endif

                </section>

            </div>


            @if(
                $user->firma
                && $user->status?->name !== 'USUARIO'
            )
                <section class="public-user-signature">

                    <header class="public-user-signature__header">
                        <div>
                            <span>FIRMA SQA</span>

                            <h2>
                                Firma
                            </h2>
                        </div>

                        <a
                            href="{{ $user->getSignatureUrl() }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Abrir
                        </a>
                    </header>

                    <div class="public-user-signature__preview">
                        <iframe
                            src="{{ $user->getSignatureUrl() }}?fit=1"
                            title="Firma de {{ $user->nick }}"
                            scrolling="no"
                            loading="lazy"
                        ></iframe>
                    </div>

                </section>
            @endif


            <div class="public-user-profile__back">
                <a
                    href="{{ route('users.index') }}"
                    class="btn btn-outline"
                >
                    ← Volver a usuarios
                </a>
            </div>

        </div>
    </article>

@endsection