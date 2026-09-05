@extends('layouts.metopas')

@section('title', 'AAR ' . $sequence . ' · ' . $campaign->name)
@section('meta-description', 'After Action Report del operativo ' . ($event->name ?: $event->activity?->name) . '.')
@section('body-class', 'campaign-aar-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/campaign-aar.css') }}?v={{ filemtime(public_path('css/campaign-aar.css')) }}">
@endpush

@if($editing)
    @push('scripts')
        <script src="{{ asset('js/community-forum.js') }}?v={{ filemtime(public_path('js/community-forum.js')) }}" defer></script>
        <script src="{{ asset('js/campaign-aar.js') }}?v={{ filemtime(public_path('js/campaign-aar.js')) }}" defer></script>
    @endpush
@endif

@section('content')
    <main class="aar-report">
        <div class="container aar-shell">
            <nav class="aar-breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('campaigns.show', $campaign) }}">{{ $campaign->name }}</a>
                <span>/</span>
                <a href="{{ route('campaigns.aars.index', $campaign) }}">AAR</a>
                <span>/</span>
                <span>Operativo {{ $sequence }}</span>
            </nav>

            @if(session('status'))
                <div class="aar-flash">{{ session('status') }}</div>
            @endif

            <header class="aar-report__hero">
                <div class="aar-report__heading">
                    <span class="aar-kicker">After Action Report · {{ $campaign->name }}</span>
                    <p class="aar-report__number">OPERATIVO DE CAMPAÑA {{ str_pad((string) $sequence, 2, '0', STR_PAD_LEFT) }}</p>
                    <h1>{{ $event->name ?: $event->activity?->name }}</h1>

                    <div class="aar-report__statusline">
                        <span @class(['aar-status', 'is-published' => $aar->isPublished(), 'is-pending' => ! $aar->isPublished()])>
                            {{ $aar->isPublished() ? 'AAR PUBLICADO' : 'PENDIENTE AAR' }}
                        </span>

                        @if($aar->published_at)
                            <span>Publicado {{ $aar->published_at->format('d/m/Y · H:i') }}</span>
                        @endif
                    </div>
                </div>

                <div class="aar-report__stamp" aria-hidden="true">
                    <span>DOCUMENTO</span>
                    <strong>AAR</strong>
                    <small>SQUAD ALPHA</small>
                </div>
            </header>

            <section class="aar-dossier" aria-label="Datos del operativo">
                <div>
                    <small>Fecha / Hora</small>
                    <strong>{{ $event->date?->format('d/m/Y · H:i') ?? '—' }}</strong>
                </div>
                <div>
                    <small>Mando global</small>
                    <strong>{{ $aar->commander?->nick ?? 'Sin identificar' }}</strong>
                </div>
                <div>
                    <small>Editor del operativo</small>
                    <strong>{{ $event->activity?->editor_display_name ?? 'Sin asignar' }}</strong>
                </div>
                <div>
                    <small>Editor de campaña</small>
                    <strong>{{ $campaign->editor?->nick ?? 'Sin asignar' }}</strong>
                </div>
                <div>
                    <small>Mapa</small>
                    <strong>{{ $event->activity?->map?->name ?? '—' }}</strong>
                </div>
                <div>
                    <small>Plataforma</small>
                    <strong>{{ $event->activity?->platform?->name ?? '—' }}</strong>
                </div>
            </section>

            <details class="aar-orbat" aria-labelledby="aar-orbat-title">
                <summary class="aar-orbat__summary">
                    <div>
                        <span class="aar-kicker">Anexo automático</span>
                        <h2 id="aar-orbat-title">ORBAT final</h2>
                    </div>
                    <div class="aar-orbat__summary-meta">
                        <small>Capturado al cerrar el operativo</small>
                        <span class="aar-orbat__toggle-label" aria-hidden="true">
                            <span class="is-closed">Ver ORBAT</span>
                            <span class="is-open">Ocultar ORBAT</span>
                            <b>⌄</b>
                        </span>
                    </div>
                </summary>

                <div class="aar-orbat__content">
                    @php($orbatGroups = $aar->orbat_snapshot['groups'] ?? [])

                    @if(empty($orbatGroups))
                        <p class="aar-muted">No hay ORBAT disponible para este operativo.</p>
                    @else
                        <div class="aar-orbat__groups">
                            @foreach($orbatGroups as $group)
                                <article class="aar-orbat__group">
                                    <header>
                                        <strong>{{ $group['name'] ?? 'Grupo' }}</strong>
                                        @if(filled($group['faction'] ?? null))
                                            <span>{{ $group['faction'] }}</span>
                                        @endif
                                    </header>

                                    <div class="aar-orbat__slots">
                                        @foreach(($group['slots'] ?? []) as $slot)
                                            <div>
                                                <span>
                                                    <b>{{ $slot['slot_name'] ?? 'Slot' }}</b>
                                                    <small>{{ $slot['slot_type'] ?? 'Sin tipo' }}</small>
                                                </span>
                                                <strong @class(['is-vacant' => ($slot['assignee'] ?? 'VACANTE') === 'VACANTE'])>
                                                    {{ $slot['assignee'] ?? 'VACANTE' }}
                                                </strong>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </details>

            @if($editing)
                <form
                    class="aar-editor"
                    method="POST"
                    action="{{ route('campaigns.aars.update', [$campaign, $event]) }}"
                    data-aar-editor
                >
                    @csrf
                    @method('PUT')

                    <header class="aar-editor__header">
                        <div>
                            <span class="aar-kicker">Redacción del informe</span>
                            <h2>Secciones del AAR</h2>
                            <p>Puedes renombrar, eliminar o añadir apartados según lo ocurrido en este operativo.</p>
                        </div>

                        <button type="button" class="aar-button aar-button--ghost" data-aar-add-section="start">
                            + Añadir sección arriba
                        </button>
                    </header>

                    @if($errors->any())
                        <div class="aar-validation">
                            <strong>Revisa el formulario:</strong>
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="aar-editor__sections" data-aar-sections data-next-index="{{ count(old('sections', $aar->sections ?? [])) }}">
                        @foreach(old('sections', $aar->sections ?? []) as $index => $section)
                            <article class="aar-editor__section" data-aar-section>
                                <div class="aar-editor__section-head">
                                    <span>SECCIÓN {{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}</span>
                                    <div class="aar-editor__section-tools" aria-label="Orden de la sección">
                                        <button type="button" data-aar-move-section="top" title="Mover al principio" aria-label="Mover al principio">⇈</button>
                                        <button type="button" data-aar-move-section="up" title="Mover una posición arriba" aria-label="Mover una posición arriba">↑</button>
                                        <button type="button" data-aar-move-section="down" title="Mover una posición abajo" aria-label="Mover una posición abajo">↓</button>
                                        <button type="button" data-aar-move-section="bottom" title="Mover al final" aria-label="Mover al final">⇊</button>
                                        <button type="button" class="aar-editor__remove" data-aar-remove-section>Eliminar</button>
                                    </div>
                                </div>

                                <input
                                    type="hidden"
                                    name="sections[{{ $index }}][key]"
                                    value="{{ $section['key'] ?? '' }}"
                                >

                                <label>
                                    <span>Título</span>
                                    <input
                                        type="text"
                                        name="sections[{{ $index }}][title]"
                                        value="{{ $section['title'] ?? '' }}"
                                        maxlength="120"
                                        required
                                    >
                                </label>

                                @include('community.partials.editor', [
                                    'id' => 'aar-section-editor-' . $index,
                                    'name' => 'sections[' . $index . '][content]',
                                    'label' => 'Informe',
                                    'value' => $section['content'] ?? '',
                                    'rows' => 10,
                                    'required' => false,
                                    'maxlength' => 20000,
                                    'placeholder' => 'Describe hechos, resultados, incidencias, decisiones de mando, inteligencia obtenida...',
                                    'help' => 'Formato seguro: negrita, títulos, listas, colores, enlaces e imágenes por URL. No se admite HTML directo.',
                                ])
                            </article>
                        @endforeach
                    </div>

                    <template data-aar-section-template>
                        <article class="aar-editor__section" data-aar-section>
                            <div class="aar-editor__section-head">
                                <span>NUEVA SECCIÓN</span>
                                <div class="aar-editor__section-tools" aria-label="Orden de la sección">
                                    <button type="button" data-aar-move-section="top" title="Mover al principio" aria-label="Mover al principio">⇈</button>
                                    <button type="button" data-aar-move-section="up" title="Mover una posición arriba" aria-label="Mover una posición arriba">↑</button>
                                    <button type="button" data-aar-move-section="down" title="Mover una posición abajo" aria-label="Mover una posición abajo">↓</button>
                                    <button type="button" data-aar-move-section="bottom" title="Mover al final" aria-label="Mover al final">⇊</button>
                                    <button type="button" class="aar-editor__remove" data-aar-remove-section>Eliminar</button>
                                </div>
                            </div>

                            <input type="hidden" name="sections[__INDEX__][key]" value="">

                            <label>
                                <span>Título</span>
                                <input type="text" name="sections[__INDEX__][title]" maxlength="120" required>
                            </label>

                            @include('community.partials.editor', [
                                'id' => 'aar-section-editor-__INDEX__',
                                'name' => 'sections[__INDEX__][content]',
                                'label' => 'Informe',
                                'value' => '',
                                'rows' => 10,
                                'required' => false,
                                'maxlength' => 20000,
                                'placeholder' => 'Describe hechos, resultados, incidencias, decisiones de mando, inteligencia obtenida...',
                                'help' => 'Formato seguro: negrita, títulos, listas, colores, enlaces e imágenes por URL. No se admite HTML directo.',
                            ])
                        </article>
                    </template>

                    <div class="aar-editor__add-bottom">
                        <button type="button" class="aar-button aar-button--ghost" data-aar-add-section="end">
                            + Añadir sección al final
                        </button>
                    </div>

                    <footer class="aar-editor__actions">
                        <a class="aar-button aar-button--ghost" href="{{ route('campaigns.aars.show', [$campaign, $event]) }}">
                            Cancelar
                        </a>

                        <button class="aar-button aar-button--ghost" type="submit" name="intent" value="save">
                            Guardar borrador
                        </button>

                        <button class="aar-button aar-button--primary" type="submit" name="intent" value="publish">
                            {{ $aar->isPublished() ? 'Guardar y publicar cambios' : 'Publicar AAR' }}
                        </button>
                    </footer>
                </form>
            @else
                <section class="aar-narrative" aria-labelledby="aar-report-content-title">
                    <header class="aar-narrative__header">
                        <div>
                            <span class="aar-kicker">Informe de mando</span>
                            <h2 id="aar-report-content-title">After Action Report</h2>
                        </div>

                        @if($canEdit)
                            <a class="aar-button aar-button--primary" href="{{ route('campaigns.aars.show', ['campaign' => $campaign, 'event' => $event, 'editar' => 1]) }}">
                                {{ $aar->isPublished() ? 'Editar AAR' : 'Completar AAR' }}
                            </a>
                        @endif
                    </header>

                    @if(! $aar->isPublished())
                        <div class="aar-pending-callout">
                            <span>PENDIENTE DE INFORME</span>
                            <strong>El AAR todavía no ha sido publicado.</strong>
                            <p>Los datos automáticos y el ORBAT final ya están archivados. La redacción corresponde al Mando global o a los editores autorizados.</p>
                        </div>
                    @else
                        <div class="aar-narrative__sections">
                            @forelse(($aar->sections ?? []) as $section)
                                <article>
                                    <span>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    <div>
                                        <h3>{{ $section['title'] ?? 'Sección' }}</h3>
                                        @if(filled($section['content'] ?? null))
                                            <div class="aar-rich forum-rich">{!! \App\Support\ForumMarkup::render($section['content']) !!}</div>
                                        @else
                                            <p class="aar-muted">Sin información registrada.</p>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <p class="aar-muted">Este AAR no contiene secciones redactadas.</p>
                            @endforelse
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </main>
@endsection
