<x-filament-panels::page>
    @php
        $colorMap = [
            'success' => ['bg' => 'rgba(22, 163, 74, 0.16)', 'border' => 'rgba(22, 163, 74, 0.35)', 'text' => '#4ade80'],
            'warning' => ['bg' => 'rgba(234, 179, 8, 0.18)', 'border' => 'rgba(234, 179, 8, 0.35)', 'text' => '#facc15'],
            'danger' => ['bg' => 'rgba(249, 115, 22, 0.18)', 'border' => 'rgba(249, 115, 22, 0.35)', 'text' => '#fb923c'],
            'gray' => ['bg' => 'rgba(107, 114, 128, 0.18)', 'border' => 'rgba(107, 114, 128, 0.35)', 'text' => '#cbd5e1'],
        ];
    @endphp

    <div
        x-data="{ draggedId: null }"
        class="space-y-6"
    >
        <style>
            .recruitment-board {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 1rem;
                align-items: start;
            }
            .recruitment-column {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                min-height: 20rem;
                border-radius: 1rem;
                border: 1px solid rgba(148, 163, 184, 0.18);
                background: rgba(15, 23, 42, 0.72);
                padding: 1rem;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
            }
            .recruitment-column-header {
                display: flex;
                justify-content: space-between;
                gap: 1rem;
                align-items: center;
            }
            .recruitment-column-header h3 {
                margin: 0;
                font-size: 0.98rem;
                font-weight: 700;
                letter-spacing: 0.01em;
            }
            .recruitment-count {
                border-radius: 999px;
                padding: 0.18rem 0.55rem;
                font-size: 0.78rem;
                font-weight: 700;
                background: rgba(250, 204, 21, 0.15);
                color: #fbbf24;
            }
            .recruitment-card-list {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }
            .recruitment-card {
                min-width: 0;
                overflow: hidden;
                overflow-wrap: anywhere;
                word-break: break-word;
                border-radius: 0.9rem;
                border: 1px solid rgba(148, 163, 184, 0.14);
                background: rgba(30, 41, 59, 0.88);
                padding: 0.9rem;
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                cursor: pointer;
                transition: transform 140ms ease, border-color 140ms ease, box-shadow 140ms ease;
            }
            .recruitment-card:hover {
                transform: translateY(-1px);
                border-color: rgba(251, 191, 36, 0.35);
                box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18);
            }
            .recruitment-card-top {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.75rem;
            }
            .recruitment-card-heading {
                min-width: 0;
                flex: 1;
            }
            .recruitment-drag-handle {
                width: 2.15rem;
                height: 2.15rem;
                flex: 0 0 2.15rem;
                display: inline-flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 0.22rem;
                border-radius: 0.55rem;
                border: 1px solid rgba(148, 163, 184, 0.26);
                background: rgba(15, 23, 42, 0.58);
                cursor: grab;
                transition: background 140ms ease, border-color 140ms ease, transform 140ms ease;
            }
            .recruitment-drag-handle:hover {
                background: rgba(251, 191, 36, 0.10);
                border-color: rgba(251, 191, 36, 0.45);
            }
            .recruitment-drag-handle:active {
                cursor: grabbing;
                transform: scale(0.96);
            }
            .recruitment-drag-handle span {
                display: block;
                width: 1rem;
                height: 2px;
                border-radius: 999px;
                background: rgba(226, 232, 240, 0.92);
                pointer-events: none;
            }
            .recruitment-card-controls {
                display: flex;
                align-items: center;
                gap: 0.35rem;
                flex: 0 0 auto;
            }
            .recruitment-delete-button {
                width: 2.15rem;
                height: 2.15rem;
                flex: 0 0 2.15rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.55rem;
                border: 1px solid rgba(248, 113, 113, 0.28);
                background: rgba(127, 29, 29, 0.12);
                color: #fda4af;
                cursor: pointer;
                transition: background 140ms ease, border-color 140ms ease, transform 140ms ease;
            }
            .recruitment-delete-button:hover {
                background: rgba(127, 29, 29, 0.24);
                border-color: rgba(248, 113, 113, 0.5);
            }
            .recruitment-delete-button:active { transform: scale(0.96); }
            .recruitment-delete-button svg { width: 1rem; height: 1rem; pointer-events: none; }
            .recruitment-card-title {
                min-width: 0;
                overflow-wrap: anywhere;
                word-break: break-word;
                font-size: 0.98rem;
                font-weight: 700;
                line-height: 1.3;
            }
            .recruitment-card-subtitle {
                min-width: 0;
                overflow-wrap: anywhere;
                word-break: break-word;
                color: rgba(203, 213, 225, 0.78);
                font-size: 0.84rem;
            }
            .recruitment-card-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.45rem 0.75rem;
                font-size: 0.82rem;
            }
            .recruitment-card-grid span:first-child {
                color: rgba(203, 213, 225, 0.68);
                display: block;
            }
            .recruitment-card-grid span:last-child {
                min-width: 0;
                overflow-wrap: anywhere;
                word-break: break-word;
                font-weight: 600;
                display: block;
            }
            .recruitment-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 0.4rem;
            }
            .recruitment-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                border-radius: 999px;
                padding: 0.25rem 0.55rem;
                font-size: 0.74rem;
                font-weight: 700;
                border: 1px solid transparent;
                line-height: 1.1;
            }
            .recruitment-card-footer {
                min-width: 0;
                overflow-wrap: anywhere;
                word-break: break-word;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            .recruitment-link {
                font-size: 0.8rem;
                font-weight: 700;
                color: #fbbf24;
                text-decoration: none;
            }
            .recruitment-drop-hint {
                margin-top: auto;
                font-size: 0.78rem;
                color: rgba(203, 213, 225, 0.54);
                text-align: center;
                border-top: 1px dashed rgba(148, 163, 184, 0.2);
                padding-top: 0.75rem;
            }
            .recruitment-search {
                width: 100%;
                max-width: 26rem;
            }
            @media (max-width: 1024px) {
                .recruitment-board {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-1">
                <h2 class="text-xl font-bold tracking-tight">Vista tablero de reclutamiento</h2>
                <p class="text-sm text-gray-400">Abre una solicitud haciendo clic en su tarjeta. Usa el control de tres líneas para moverla entre estados.</p>
            </div>

            <div class="recruitment-search">
                <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="recruitment-search">
                    <span class="text-sm font-medium">Buscar solicitud</span>
                </label>
                <input
                    id="recruitment-search"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Nick, email, nombre real o texto..."
                    class="fi-input block w-full rounded-xl border-none bg-white/5 px-3 py-2.5 text-sm shadow-sm ring-1 ring-white/10"
                >
            </div>
        </div>

        <div class="recruitment-board">
            @foreach ($columns as $column)
                <section
                    wire:key="column-{{ $column['status'] }}"
                    class="recruitment-column"
                    @dragover.prevent
                    @drop.prevent="if (draggedId) { $wire.moveToStatus(draggedId, '{{ $column['status'] }}'); draggedId = null }"
                >
                    <div class="recruitment-column-header">
                        <h3>{{ $column['label'] }}</h3>
                        <span class="recruitment-count">{{ count($column['items']) }}</span>
                    </div>

                    <div class="recruitment-card-list">
                        @forelse ($column['items'] as $record)
                            <article
                                wire:key="card-{{ $record->id }}"
                                class="recruitment-card"
                                role="link"
                                tabindex="0"
                                @click="window.location.href = '{{ \App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource::getUrl('edit', ['record' => $record]) }}'"
                                @keydown.enter.prevent="window.location.href = '{{ \App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource::getUrl('edit', ['record' => $record]) }}'"
                            >
                                <div class="recruitment-card-top">
                                    <div class="recruitment-card-heading space-y-1">
                                        <div class="recruitment-card-title">{{ $record->nickname ?: 'Solicitud de alistamiento' }}</div>
                                        <div class="recruitment-card-subtitle">{{ $record->email }}</div>
                                    </div>

                                    <div class="recruitment-card-controls">
                                        <button
                                            type="button"
                                            class="recruitment-delete-button"
                                            title="Eliminar solicitud"
                                            aria-label="Eliminar solicitud"
                                            @click.stop.prevent
                                            @keydown.stop
                                            wire:click.stop="deleteApplication({{ $record->id }})"
                                            wire:confirm="¿Eliminar definitivamente esta solicitud de alistamiento?"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                <path d="M3 6h18"></path>
                                                <path d="M8 6V4h8v2"></path>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v5"></path>
                                                <path d="M14 11v5"></path>
                                            </svg>
                                        </button>

                                        <button
                                            type="button"
                                            class="recruitment-drag-handle"
                                            draggable="true"
                                            title="Mover solicitud"
                                            aria-label="Mover solicitud"
                                            @click.stop.prevent
                                            @keydown.stop
                                            @dragstart.stop="draggedId = {{ $record->id }}"
                                            @dragend.stop="draggedId = null"
                                        >
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </button>
                                    </div>
                                </div>

                                <div class="recruitment-badges">
                                    @php $workflowColors = $colorMap[$record->recruitmentWorkflowColor()] ?? $colorMap['gray']; @endphp
                                    <span class="recruitment-badge" style="background: {{ $workflowColors['bg'] }}; border-color: {{ $workflowColors['border'] }}; color: {{ $workflowColors['text'] }};">
                                        {{ $record->recruitmentWorkflowLabel() }}
                                    </span>

                                    @if ($record->read_at)
                                        <span class="recruitment-badge" style="background: rgba(59, 130, 246, 0.14); border-color: rgba(59, 130, 246, 0.28); color: #93c5fd;">
                                            Leída
                                        </span>
                                    @endif
                                </div>

                                <div class="recruitment-card-grid">
                                    <div>
                                        <span>Entrevistador</span>
                                        <span>{{ $record->recruitmentInterviewer?->nick ?? 'Sin asignar' }}</span>
                                    </div>
                                    <div>
                                        <span>Usuario</span>
                                        <span>{{ $record->recruitmentMatchedUser?->nick ?? 'Sin coincidencia' }}</span>
                                    </div>
                                    <div>
                                        <span>Comentarios</span>
                                        <span>{{ $record->recruitment_comments_count }}</span>
                                    </div>
                                    <div>
                                        <span>Recibida</span>
                                        <span>{{ $record->created_at?->format('d/m/Y H:i') }}</span>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">TIER por reclutador</div>
                                    <div class="recruitment-badges">
                                        @forelse ($record->recruitmentTierRatingsSummary() as $rating)
                                            @php $tierColors = $colorMap[$rating['color']] ?? $colorMap['gray']; @endphp
                                            <span
                                                class="recruitment-badge"
                                                title="{{ $rating['reason'] ?: 'Sin comentario' }}"
                                                style="background: {{ $tierColors['bg'] }}; border-color: {{ $tierColors['border'] }}; color: {{ $tierColors['text'] }};"
                                            >
                                                {{ $rating['nick'] }} · {{ $rating['tier'] }}
                                            </span>
                                        @empty
                                            <span class="recruitment-badge" style="background: rgba(107, 114, 128, 0.18); border-color: rgba(107, 114, 128, 0.35); color: #cbd5e1;">
                                                Sin valoraciones todavía
                                            </span>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="recruitment-card-footer">
                                    <div class="text-xs text-gray-400">
                                        {{ \Illuminate\Support\Str::limit($record->message ?: 'Sin mensaje adicional.', 100) }}
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="rounded-xl border border-dashed border-white/10 bg-white/5 px-4 py-8 text-center text-sm text-gray-400">
                                No hay solicitudes en esta columna.
                            </div>
                        @endforelse
                    </div>

                    <div class="recruitment-drop-hint">Suelta aquí la solicitud para cambiar su estado.</div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
