@extends('layouts.metopas')

@section('title', $metopa->description ?: $metopa->name)

@section('meta-description', $metopa->description ?: 'Detalle de la metopa ' . $metopa->name . ' de Squad ALPHA.')

@section('body-class', 'metopa-detail-body')

@section('page-background')
    @if($metopa->imgback)
        <div class="metopa-page-background" aria-hidden="true">
            <img src="{{ asset('storage/' . $metopa->imgback) }}" alt="">
        </div>
    @endif
@endsection

@section('content')
    <article class="metopa-detail">
        <div class="container metopa-detail__container">
            <nav class="metopa-breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('metopas.index') }}">Metopas</a>
                <span aria-hidden="true">/</span>
                <span>{{ $metopa->name }}</span>
            </nav>

            <header class="metopa-detail__header">
                {{-- <span class="metopas-kicker">{{ $metopa->name }}</span> --}}

                <h1>{{ $metopa->description ?: $metopa->name }}</h1>
            </header>

            @if(filled($metopa->despag1))
                <section class="metopa-rich-content metopa-rich-content--intro">
                    {{ $descriptionOne }}
                </section>
            @endif

            @if($metopa->image_large || $metopa->image)
                <figure class="metopa-detail__main-image">
                    <img
                        src="{{ asset('storage/' . ($metopa->image_large ?: $metopa->image)) }}"
                        alt="{{ $metopa->description ?: $metopa->name }}"
                    >
                </figure>
            @endif

            @if(filled($metopa->despag2))
                <section class="metopa-rich-content metopa-rich-content--secondary">
                    {{ $descriptionTwo }}
                </section>
            @endif

            <section class="metopa-awardees">
                <details class="metopa-awardees__disclosure">
                    <summary>
                        <span class="metopa-awardees__heading">
                            {{-- <small>Reconocimientos</small> --}}
                            <strong>
                                <span class="metopa-awardees__show-label">Mostrar miembros galardonados</span>
                                <span class="metopa-awardees__hide-label">Ocultar miembros galardonados</span>
                            </strong>
                        </span>

                        <span class="metopa-awardees__count">
                            {{ $metopa->users->count() }}
                        </span>

                        <span class="metopa-awardees__chevron" aria-hidden="true"></span>
                    </summary>

                    <div class="metopa-awardees__content">
                        @if($metopa->users->isEmpty())
                            <p class="metopa-awardees__empty">
                                Actualmente no hay miembros galardonados con esta metopa.
                            </p>
                        @else
                            <ol class="metopa-awardees__list">
                                @foreach($metopa->users as $user)
                                    @php
                                        $assignedAt = \Illuminate\Support\Carbon::parse(
                                            $user->pivot->assigned_at
                                        );

                                        $statusName = $user->status?->name ?? 'SIN ESTADO';

                                        $statusClass = match (strtoupper($statusName)) {
                                            'ACTIVO' => 'is-active',
                                            'RESERVA' => 'is-reserve',
                                            'CESADO' => 'is-dismissed',
                                            'BAJA' => 'is-leave',
                                            default => 'is-default',
                                        };
                                    @endphp

                                    <li>
                                        <span class="metopa-awardees__position" aria-hidden="true">
                                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                        </span>

                                        <span class="metopa-awardees__member">
                                            <x-user-link
                                                :user="$user"
                                                class="metopa-user-link"
                                                @style([
                                                    '--member-group-color: '
                                                    . ($user->mainSqaGroup?->color ?? '')
                                                    => filled(
                                                        $user->mainSqaGroup?->color
                                                    ),
                                                ])
                                            />

                                            <span
                                                class="metopa-member-status {{ $statusClass }}"
                                            >
                                                {{ $statusName }}
                                            </span>
                                        </span>

                                        <time datetime="{{ $assignedAt->toDateString() }}">
                                            Concedida el {{ $assignedAt->format('d/m/Y') }}
                                        </time>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                </details>
            </section>

            @if($metopa->sqaGroup?->image)
                <section class="metopa-group">
                    <img
                        src="{{ asset('storage/' . $metopa->sqaGroup->image) }}"
                        alt="Grupo SQA {{ $metopa->sqaGroup->large_name ?: $metopa->sqaGroup->name }}"
                        loading="lazy"
                    >
                </section>
            @endif

            <div class="metopa-detail__back">
                <a href="{{ route('metopas.index') }}" class="btn btn-outline">
                    <span aria-hidden="true">←</span>
                    Volver a metopas
                </a>
            </div>
        </div>
    </article>
@endsection
