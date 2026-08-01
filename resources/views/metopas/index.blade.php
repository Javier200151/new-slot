@extends('layouts.metopas')

@section('title', 'Metopas')

@section('meta-description', 'Consulta las metopas y reconocimientos de Squad ALPHA.')

@section('content')
    <section class="metopas-index-hero">
        <div class="container metopas-index-hero__content">
            {{-- <span class="metopas-kicker">Reconocimientos</span> --}}

            <h1>Metopas</h1>

            <p>
                Distinciones que representan la formación, el compromiso y los
                hitos alcanzados dentro de Squad ALPHA.
            </p>
        </div>
    </section>

    <section class="metopas-catalogue" aria-labelledby="metopas-list-title">
        <div class="container">
            {{--<header class="metopas-section-heading">
                 <div>
                    <span class="metopas-section-heading__index">01</span>
                    <span class="metopas-section-heading__label">Archivo de metopas</span>
                </div>

                <h2 id="metopas-list-title">
                    Selecciona una metopa para conocer su historia.
                </h2> 
            </header> --}}

            @if($metopas->isEmpty())
                <div class="metopas-empty">
                    <span>Sin registros</span>
                    <p>Todavía no hay metopas publicadas.</p>
                </div>
            @else
                <div class="metopas-grid">
                    @foreach($metopas as $metopa)
                        <a
                            href="{{ route('metopas.show', $metopa) }}"
                            class="metopa-card"
                            aria-label="Ver metopa {{ $metopa->description ?: $metopa->name }}"
                        >
                            {{-- <span class="metopa-card__number">
                                {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                            </span> --}}

                            <figure class="metopa-card__image">
                                <img
                                    src="{{ asset('storage/' . $metopa->image) }}"
                                    alt="Metopa {{ $metopa->description ?: $metopa->name }}"
                                    loading="lazy"
                                >
                            </figure>

                            <div class="metopa-card__content">
                                <span class="metopa-card__code">{{ $metopa->name }}</span>

                                <h3>{{ $metopa->description ?: $metopa->name }}</h3>

                                {{-- <span class="metopa-card__group">
                                    @if($metopa->sqaGroup)
                                        Grupo {{ $metopa->sqaGroup->name }}
                                    @else
                                        Sin grupo
                                    @endif
                                </span> --}}

                                <span class="metopa-card__link">
                                    Ver metopa <span aria-hidden="true">→</span>
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
