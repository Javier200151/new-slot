@extends('layouts.metopas')

@section('title', 'Novedades en eventos')
@section('body-class', 'events-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/events.css') }}?v={{ filemtime(public_path('css/events.css')) }}">
@endpush

@section('content')
<section class="events-news-page container">
    <div class="events-news-heading">
        <a href="{{ route('events.index') }}">← Volver a eventos</a>
        <h1>Novedades en eventos</h1>
        <p>Últimas novedades de los eventos de Squad ALPHA.</p>
    </div>

    <div class="events-news-list">
        @forelse($updates as $update)
            @php($event = $update['event'])
            <article @class(['events-news-card', 'events-news-card--seen' => ! $update['unread']])>
                <a class="events-news-card__media" href="{{ route('events.show', $event) }}">
                    @if(filled($event->activity?->image))
                        <img src="{{ asset('storage/' . $event->activity->image) }}" alt="" loading="lazy">
                    @else
                        <span aria-hidden="true">✱</span>
                    @endif
                </a>
                <div class="events-news-card__content">
                    <div class="events-news-card__top">
                        <time datetime="{{ $event->date?->toIso8601String() }}">{{ $event->date?->format('d/m/Y · H:i') }}</time>
                        @if($update['unread'])
                            <span>✱ Novedades</span>
                        @else
                            <span class="events-news-card__read-indicator">✓ Visto</span>
                        @endif
                    </div>
                    <h2><a href="{{ route('events.show', $event) }}">{{ $event->name ?: $event->activity?->name }}</a></h2>

                    @if(! empty($update['messages']))
                        <ul class="events-news-card__messages">
                            @foreach($update['messages'] as $message)
                                <li @class(['events-news-card__message--seen' => $message['read']])>{{ $message['text'] }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if($update['join_count'] > 0)
                        <details class="events-news-joins">
                            <summary>
                                <span class="events-news-joins__summary">
                                    <strong>{{ $update['join_count'] === 1 ? 'Se ha apuntado 1 persona' : 'Se han apuntado ' . $update['join_count'] . ' personas' }}</strong>
                                    @if($update['unread_join_count'] > 0)
                                        <span class="events-news-joins__new-count">{{ $update['unread_join_count'] }} sin ver</span>
                                    @endif
                                    @if($update['own_squad_join_count'] > 0)
                                        <span class="events-news-joins__own">{{ $update['own_squad_join_count'] === 1 ? '1 de ellas a tu escuadra' : $update['own_squad_join_count'] . ' de ellas a tu escuadra' }}</span>
                                    @endif
                                </span>
                                <span class="events-news-joins__toggle" aria-hidden="true">Ver inscripciones</span>
                            </summary>
                            <ul class="events-news-joins__list">
                                @foreach($update['joins'] as $join)
                                    <li @class([
                                        'events-news-joins__item',
                                        'events-news-joins__item--own' => $join['in_own_squad'],
                                        'events-news-joins__item--seen' => $join['read'],
                                    ])>
                                        <span class="events-news-joins__slot">{{ $join['slot'] }}@if($join['group'] !== '') <span class="events-news-joins__group">· {{ $join['group'] }}</span> @endif</span>
                                        <span class="events-news-joins__member">{{ $join['member'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                    <a class="events-news-card__open" href="{{ route('events.show', $event) }}">Ver evento</a>
                </div>
            </article>
        @empty
            <div class="events-news-empty">No hay novedades de los últimos 14 días.</div>
        @endforelse
    </div>
</section>
@endsection
