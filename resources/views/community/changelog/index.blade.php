@extends('layouts.metopas')

@section('title', 'Changelog')
@section('body-class', 'forum-body changelog-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/community.css') }}?v={{ filemtime(public_path('css/community.css')) }}">
@endpush

@section('content')
<div class="community-shell changelog-shell">
    <a class="community-kicker forum-back-link" href="{{ route('community.forum.home') }}">← Foro</a>

    <header class="changelog-page-head">
        <span class="community-kicker">NEWSLOT</span>
        <h1 class="community-title">Changelog</h1>
    </header>

    @php
        $areaLabels = [
            'frontend' => 'Frontend',
            'filament' => 'Filament',
            'system' => 'Sistema',
        ];
        $typeLabels = [
            'new' => 'Nuevas funciones',
            'improvement' => 'Mejoras',
            'fix' => 'Fixes',
        ];
    @endphp

    <div class="changelog-list">
        @forelse($entries as $entry)
            @php
                $changes = collect($entry->changes ?? []);
            @endphp

            <article class="changelog-entry">
                <header class="changelog-entry__head">
                    <time datetime="{{ $entry->release_date?->toDateString() }}">
                        {{ $entry->release_date?->format('Y-m-d') }}
                    </time>
                    @if(filled($entry->version))
                        <span>{{ $entry->version }}</span>
                    @endif
                </header>

                <div class="changelog-entry__content">
                    @foreach($areaLabels as $areaKey => $areaLabel)
                        @php
                            $areaChanges = $changes->where('area', $areaKey);
                        @endphp

                        @if($areaChanges->isNotEmpty())
                            <section class="changelog-area">
                                <h2>{{ $areaLabel }}</h2>

                                @foreach($typeLabels as $typeKey => $typeLabel)
                                    @php
                                        $typedChanges = $areaChanges->where('type', $typeKey);
                                    @endphp

                                    @if($typedChanges->isNotEmpty())
                                        <div class="changelog-group changelog-group--{{ $typeKey }}">
                                            <h3>{{ $typeLabel }}</h3>
                                            <ul>
                                                @foreach($typedChanges as $change)
                                                    <li>
                                                        <div class="changelog-change bbcode-rich forum-rich">
                                                            {!! \App\Support\BbcodeMarkup::render($change['description'] ?? '') !!}
                                                        </div>
                                                        @if(filled($change['url'] ?? null))
                                                            <a
                                                                class="changelog-change__link"
                                                                href="{{ $change['url'] }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                aria-label="Abrir enlace relacionado"
                                                            >↗</a>
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                            </section>
                        @endif
                    @endforeach
                </div>
            </article>
        @empty
            <div class="community-empty">Todavía no hay entradas publicadas en el changelog.</div>
        @endforelse
    </div>

    @if($entries->hasPages())
        <nav class="community-pagination" aria-label="Paginación del changelog">
            @if($entries->onFirstPage())<span>← Anterior</span>@else<a href="{{ $entries->previousPageUrl() }}">← Anterior</a>@endif
            <strong>Página {{ $entries->currentPage() }} de {{ $entries->lastPage() }}</strong>
            @if($entries->hasMorePages())<a href="{{ $entries->nextPageUrl() }}">Siguiente →</a>@else<span>Siguiente →</span>@endif
        </nav>
    @endif
</div>
@endsection
