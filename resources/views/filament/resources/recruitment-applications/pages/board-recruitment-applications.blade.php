<x-filament-panels::page>
    @php
        $statusColors = [
            'success' => ['bg' => 'rgba(22, 163, 74, 0.14)', 'border' => 'rgba(22, 163, 74, 0.34)', 'text' => '#4ade80'],
            'warning' => ['bg' => 'rgba(234, 179, 8, 0.14)', 'border' => 'rgba(234, 179, 8, 0.34)', 'text' => '#fde047'],
            'danger' => ['bg' => 'rgba(225, 29, 72, 0.14)', 'border' => 'rgba(225, 29, 72, 0.34)', 'text' => '#fb7185'],
            'gray' => ['bg' => 'rgba(100, 116, 139, 0.15)', 'border' => 'rgba(148, 163, 184, 0.24)', 'text' => '#cbd5e1'],
        ];
    @endphp

    <div x-data="{ draggedId: null }" class="recruitment-board-page">
        <style>
            .recruitment-board-page { display:flex; flex-direction:column; gap:1.25rem; }
            .recruitment-toolbar {
                display:flex; align-items:flex-end; justify-content:space-between; gap:1rem;
                padding:1rem 1.1rem; border:1px solid rgba(148,163,184,.16); border-radius:1rem;
                background:linear-gradient(180deg,rgba(30,41,59,.68),rgba(15,23,42,.62));
            }
            .recruitment-toolbar-copy { min-width:0; }
            .recruitment-toolbar-title { margin:0; font-size:1rem; font-weight:800; }
            .recruitment-toolbar-help { margin:.28rem 0 0; color:#94a3b8; font-size:.84rem; line-height:1.45; }
            .recruitment-search-wrap { width:min(100%,24rem); }
            .recruitment-search-label { display:block; margin-bottom:.38rem; font-size:.75rem; font-weight:800; color:#cbd5e1; }
            .recruitment-search-box {
                display:flex; align-items:center; gap:.55rem; padding:.62rem .78rem; border-radius:.8rem;
                border:1px solid rgba(148,163,184,.2); background:rgba(2,6,23,.42);
                box-shadow:inset 0 1px 0 rgba(255,255,255,.02);
            }
            .recruitment-search-box:focus-within { border-color:rgba(245,158,11,.55); box-shadow:0 0 0 3px rgba(245,158,11,.08); }
            .recruitment-search-box svg { width:1rem; height:1rem; flex:0 0 auto; color:#94a3b8; }
            .recruitment-search-input { width:100%; border:0; outline:0; background:transparent; color:#f8fafc; font-size:.87rem; }
            .recruitment-search-input::placeholder { color:#64748b; }
            .recruitment-board { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; align-items:start; }
            .recruitment-column {
                min-height:24rem; display:flex; flex-direction:column; gap:.8rem; padding:.9rem;
                border-radius:1rem; border:1px solid rgba(148,163,184,.18);
                background:rgba(15,23,42,.76); box-shadow:0 10px 26px rgba(2,6,23,.14);
            }
            .recruitment-column-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.08rem .05rem .15rem; }
            .recruitment-column-header h3 { margin:0; font-size:.95rem; font-weight:800; }
            .recruitment-count {
                min-width:1.55rem; height:1.55rem; display:inline-flex; align-items:center; justify-content:center;
                padding:0 .42rem; border-radius:999px; font-size:.72rem; font-weight:800;
                color:#fbbf24; background:rgba(245,158,11,.16); border:1px solid rgba(245,158,11,.18);
            }
            .recruitment-card-list { display:flex; flex-direction:column; gap:.7rem; }
            .recruitment-card {
                position:relative; display:flex; flex-direction:column; gap:.72rem; padding:.85rem;
                border-radius:.9rem; border:1px solid rgba(148,163,184,.14); background:rgba(30,41,59,.9);
                cursor:pointer; transition:transform .14s ease,border-color .14s ease,box-shadow .14s ease,background .14s ease;
            }
            .recruitment-card:hover { transform:translateY(-1px); border-color:rgba(245,158,11,.35); background:rgba(30,41,59,.98); box-shadow:0 8px 22px rgba(2,6,23,.18); }
            .recruitment-card:focus-visible { outline:2px solid rgba(245,158,11,.65); outline-offset:2px; }
            .recruitment-card-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.65rem; }
            .recruitment-card-heading { min-width:0; }
            .recruitment-card-title { font-size:.96rem; font-weight:800; line-height:1.25; color:#f8fafc; }
            .recruitment-card-subtitle { margin-top:.15rem; font-size:.8rem; color:#93c5fd; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .recruitment-drag-handle {
                width:2.1rem; height:2.1rem; flex:0 0 auto; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.22rem;
                border-radius:.65rem; border:1px solid rgba(148,163,184,.22); background:rgba(15,23,42,.6); cursor:grab;
            }
            .recruitment-drag-handle:hover { border-color:rgba(245,158,11,.5); background:rgba(245,158,11,.08); }
            .recruitment-drag-handle:active { cursor:grabbing; }
            .recruitment-drag-handle span { width:.95rem; height:2px; border-radius:999px; background:#cbd5e1; }
            .recruitment-badges { display:flex; flex-wrap:wrap; gap:.35rem; }
            .recruitment-badge {
                display:inline-flex; align-items:center; gap:.3rem; border-radius:999px; padding:.22rem .52rem;
                font-size:.69rem; font-weight:800; border:1px solid transparent; line-height:1.2;
            }
            .recruitment-card-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.48rem .75rem; }
            .recruitment-meta-label { display:block; color:#94a3b8; font-size:.7rem; margin-bottom:.08rem; }
            .recruitment-meta-value { display:block; color:#f8fafc; font-size:.78rem; font-weight:700; min-width:0; overflow:hidden; text-overflow:ellipsis; }
            .recruitment-tier-section { padding-top:.1rem; }
            .recruitment-tier-title { margin-bottom:.42rem; color:#cbd5e1; font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
            .recruitment-tier-list { display:flex; flex-direction:column; gap:.38rem; }
            .recruitment-tier-row {
                display:flex; align-items:flex-start; gap:.5rem; padding:.48rem .55rem; border-radius:.65rem;
                background:rgba(15,23,42,.38); border:1px solid rgba(148,163,184,.11);
            }
            .recruitment-tier-copy { min-width:0; line-height:1.3; }
            .recruitment-tier-user { font-size:.74rem; font-weight:800; color:#e2e8f0; }
            .recruitment-tier-reason { margin-top:.08rem; font-size:.72rem; color:#94a3b8; overflow-wrap:anywhere; }
            .recruitment-message { padding-top:.55rem; border-top:1px solid rgba(148,163,184,.11); color:#cbd5e1; font-size:.76rem; line-height:1.42; }
            .recruitment-drop-hint { margin-top:auto; padding-top:.7rem; border-top:1px dashed rgba(148,163,184,.17); text-align:center; color:#64748b; font-size:.72rem; }
            .recruitment-empty { padding:2rem .8rem; text-align:center; color:#64748b; font-size:.8rem; border:1px dashed rgba(148,163,184,.16); border-radius:.8rem; background:rgba(255,255,255,.018); }
            @media (max-width:1100px) { .recruitment-board { grid-template-columns:1fr; } }
            @media (max-width:700px) { .recruitment-toolbar { align-items:stretch; flex-direction:column; } .recruitment-search-wrap { width:100%; } }
        </style>

        <div class="recruitment-toolbar">
            <div class="recruitment-toolbar-copy">
                <h2 class="recruitment-toolbar-title">Solicitudes de alistamiento</h2>
                <p class="recruitment-toolbar-help">Abre una solicitud haciendo clic en su tarjeta. Usa el control de tres líneas para cambiarla de estado.</p>
            </div>

            <div class="recruitment-search-wrap">
                <label class="recruitment-search-label" for="recruitment-search">Buscar solicitud</label>
                <div class="recruitment-search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.5-3.5"></path>
                    </svg>
                    <input
                        id="recruitment-search"
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Nick, email, nombre o texto..."
                        class="recruitment-search-input"
                    >
                </div>
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
                                    <div class="recruitment-card-heading">
                                        <div class="recruitment-card-title">{{ $record->nickname ?: 'Solicitud de alistamiento' }}</div>
                                        <div class="recruitment-card-subtitle">{{ $record->email }}</div>
                                    </div>

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
                                        <span></span><span></span><span></span>
                                    </button>
                                </div>

                                <div class="recruitment-badges">
                                    @php $workflowColors = $statusColors[$record->recruitmentWorkflowColor()] ?? $statusColors['gray']; @endphp
                                    <span class="recruitment-badge" style="background:{{ $workflowColors['bg'] }};border-color:{{ $workflowColors['border'] }};color:{{ $workflowColors['text'] }};">
                                        {{ $record->recruitmentWorkflowLabel() }}
                                    </span>
                                    @if ($record->read_at)
                                        <span class="recruitment-badge" style="background:rgba(59,130,246,.12);border-color:rgba(59,130,246,.28);color:#93c5fd;">Leída</span>
                                    @endif
                                </div>

                                <div class="recruitment-card-grid">
                                    <div><span class="recruitment-meta-label">Entrevistador</span><span class="recruitment-meta-value">{{ $record->recruitmentInterviewer?->nick ?? 'Sin asignar' }}</span></div>
                                    <div><span class="recruitment-meta-label">Usuario</span><span class="recruitment-meta-value">{{ $record->recruitmentMatchedUser?->nick ?? 'Sin coincidencia' }}</span></div>
                                    <div><span class="recruitment-meta-label">Comentarios</span><span class="recruitment-meta-value">{{ $record->recruitment_comments_count }}</span></div>
                                    <div><span class="recruitment-meta-label">Recibida</span><span class="recruitment-meta-value">{{ $record->created_at?->format('d/m/Y H:i') }}</span></div>
                                </div>

                                <div class="recruitment-tier-section">
                                    <div class="recruitment-tier-title">Valoraciones TIER</div>
                                    <div class="recruitment-tier-list">
                                        @forelse ($record->recruitmentTierRatingsSummary() as $rating)
                                            @php
                                                $tierNumber = (int) str_replace('TIER ', '', $rating['tier']);
                                                [$tierBg, $tierBorder, $tierText] = match ($tierNumber) {
                                                    1 => ['rgba(22,163,74,.15)', 'rgba(22,163,74,.36)', '#4ade80'],
                                                    2 => ['rgba(234,179,8,.16)', 'rgba(234,179,8,.38)', '#fde047'],
                                                    3 => ['rgba(249,115,22,.16)', 'rgba(249,115,22,.38)', '#fb923c'],
                                                    default => ['rgba(100,116,139,.15)', 'rgba(148,163,184,.24)', '#cbd5e1'],
                                                };
                                            @endphp
                                            <div class="recruitment-tier-row">
                                                <span class="recruitment-badge" style="background:{{ $tierBg }};border-color:{{ $tierBorder }};color:{{ $tierText }};">{{ $rating['tier'] }}</span>
                                                <div class="recruitment-tier-copy">
                                                    <div class="recruitment-tier-user">{{ $rating['nick'] }}</div>
                                                    <div class="recruitment-tier-reason">{{ $rating['reason'] ?: 'Sin comentario' }}</div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="recruitment-tier-row">
                                                <div class="recruitment-tier-reason">Todavía no hay valoraciones.</div>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                <div class="recruitment-message">{{ \Illuminate\Support\Str::limit($record->message ?: 'Sin mensaje adicional.', 120) }}</div>
                            </article>
                        @empty
                            <div class="recruitment-empty">No hay solicitudes en esta columna.</div>
                        @endforelse
                    </div>

                    <div class="recruitment-drop-hint">Suelta aquí la solicitud para cambiar su estado.</div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
