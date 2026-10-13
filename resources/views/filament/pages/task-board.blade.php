<x-filament-panels::page>
    @php
        $columns = $this->columns();
    @endphp

    <div x-data="{ draggedId: null }" class="task-board-page">
        <style>
            .task-board-page{display:flex;flex-direction:column;gap:1rem}.task-board-intro{display:flex;gap:1rem;align-items:end;justify-content:space-between;flex-wrap:wrap}.task-board-intro p{margin:.2rem 0 0;color:#94a3b8}.task-board-search{width:min(100%,28rem)}.task-board-search label{display:block;margin-bottom:.35rem;font-size:.82rem;font-weight:700;color:#cbd5e1}.task-board-search input{width:100%;box-sizing:border-box;border:1px solid rgba(148,163,184,.25);border-radius:.75rem;background:#111827;color:#f8fafc;padding:.72rem .85rem;outline:none}.task-board-search input:focus{border-color:rgba(245,158,11,.7);box-shadow:0 0 0 3px rgba(245,158,11,.12)}.task-board-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;align-items:start}.task-column{min-height:30rem;border:1px solid rgba(148,163,184,.17);border-radius:1rem;background:rgba(15,23,42,.68);padding:1rem}.task-column__header{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.85rem}.task-column__header h3{font-weight:800;font-size:1rem}.task-column__count{display:inline-flex;min-width:1.65rem;height:1.65rem;align-items:center;justify-content:center;border-radius:999px;background:rgba(245,158,11,.16);color:#fbbf24;font-size:.75rem;font-weight:800}.task-card-list{display:flex;flex-direction:column;gap:.72rem}.task-card{border:1px solid rgba(148,163,184,.16);border-radius:.85rem;background:#1e293b;padding:.85rem;cursor:pointer;transition:transform .13s ease,border-color .13s ease,box-shadow .13s ease}.task-card:hover{transform:translateY(-1px);border-color:rgba(245,158,11,.42);box-shadow:0 9px 22px rgba(0,0,0,.2)}.task-card__top{display:flex;align-items:flex-start;justify-content:space-between;gap:.6rem}.task-card__title{font-weight:800;line-height:1.3;overflow-wrap:anywhere}.task-card__actions{display:flex;gap:.35rem}.task-card__button{width:2rem;height:2rem;display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(148,163,184,.2);border-radius:.55rem;background:rgba(15,23,42,.6);color:#e2e8f0;cursor:pointer}.task-card__button:hover{border-color:rgba(245,158,11,.5);background:rgba(245,158,11,.1)}.task-card__button--danger:hover{border-color:rgba(248,113,113,.55);background:rgba(127,29,29,.22)}.task-card__drag{cursor:grab;line-height:1;font-size:1.05rem}.task-card__description{margin:.65rem 0;color:#cbd5e1;font-size:.82rem;line-height:1.45;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.task-card__meta{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.65rem}.task-chip{display:inline-flex;align-items:center;gap:.3rem;border-radius:999px;padding:.24rem .5rem;font-size:.72rem;font-weight:700;background:rgba(15,23,42,.62);border:1px solid rgba(148,163,184,.16);color:#cbd5e1}.task-chip--warning{color:#fbbf24;border-color:rgba(245,158,11,.3)}.task-card__assignees{display:flex;flex-wrap:wrap;gap:.32rem;margin-top:.65rem}.task-assignee{padding:.22rem .48rem;border-radius:999px;background:rgba(59,130,246,.12);border:1px solid rgba(96,165,250,.24);color:#bfdbfe;font-size:.72rem;font-weight:700}.task-card__footer{display:flex;justify-content:space-between;gap:.6rem;align-items:center;margin-top:.7rem;color:#94a3b8;font-size:.72rem}.task-empty{padding:1.5rem .5rem;text-align:center;color:#64748b;font-size:.82rem}.task-drop-hint{margin-top:1rem;padding-top:.7rem;border-top:1px dashed rgba(148,163,184,.18);text-align:center;color:#64748b;font-size:.72rem}@media(max-width:1100px){.task-board-grid{grid-template-columns:1fr}.task-column{min-height:12rem}}
        </style>

        <div class="task-board-intro">
            <div>
                <h2 class="text-xl font-bold tracking-tight">Tablero de tareas</h2>
                <p>Crea tarjetas en Pendiente, asígnalas a uno o varios miembros y muévelas entre estados.</p>
            </div>
            <div class="task-board-search">
                <label for="task-board-search">Buscar tarea</label>
                <input id="task-board-search" type="text" wire:model.live.debounce.300ms="search" placeholder="Título, descripción o miembro...">
            </div>
        </div>

        <div class="task-board-grid">
            @foreach($columns as $column)
                <section class="task-column" wire:key="task-column-{{ $column['status'] }}" @dragover.prevent @drop.prevent="if (draggedId) { $wire.moveTask(draggedId, '{{ $column['status'] }}'); draggedId = null }">
                    <div class="task-column__header">
                        <h3>{{ $column['label'] }}</h3>
                        <span class="task-column__count">{{ $column['items']->count() }}</span>
                    </div>

                    <div class="task-card-list">
                        @forelse($column['items'] as $task)
                            @php
                                $done = $task->checklistItems->where('completed', true)->count();
                                $total = $task->checklistItems->count();
                                $assignedToMe = $task->assignees->contains('id', auth()->id());
                            @endphp
                            <article class="task-card" wire:key="task-card-{{ $task->id }}" role="button" tabindex="0" @click="$wire.mountAction('editTask', { taskId: {{ $task->id }} })" @keydown.enter.prevent="$wire.mountAction('editTask', { taskId: {{ $task->id }} })">
                                <div class="task-card__top">
                                    <div class="task-card__title">{{ $task->title }}</div>
                                    <div class="task-card__actions">
                                        @unless($assignedToMe)
                                            <button type="button" class="task-card__button" title="Asignarme" @click.stop.prevent wire:click.stop="assignMe({{ $task->id }})">+</button>
                                        @endunless
                                        <button type="button" class="task-card__button task-card__drag" draggable="true" title="Mover tarjeta" @click.stop.prevent @dragstart.stop="draggedId = {{ $task->id }}" @dragend.stop="draggedId = null">☰</button>
                                        <button type="button" class="task-card__button task-card__button--danger" title="Eliminar" @click.stop.prevent wire:click.stop="deleteTask({{ $task->id }})" wire:confirm="¿Eliminar esta tarjeta y todos sus comentarios/checks?">×</button>
                                    </div>
                                </div>

                                @if(filled($task->description))
                                    <div class="task-card__description">{{ $task->description }}</div>
                                @endif

                                <div class="task-card__assignees">
                                    @forelse($task->assignees as $assignee)
                                        <span class="task-assignee">{{ $assignee->nick }}</span>
                                    @empty
                                        <span class="task-chip">Sin asignar</span>
                                    @endforelse
                                </div>

                                <div class="task-card__meta">
                                    @if($total > 0)<span class="task-chip {{ $done === $total ? 'task-chip--warning' : '' }}">✓ {{ $done }}/{{ $total }}</span>@endif
                                    <span class="task-chip">💬 {{ $task->comments_count }}</span>
                                </div>

                                <div class="task-card__footer">
                                    <span>Creada por {{ $task->creator?->nick ?? 'Usuario' }}</span>
                                    <span>{{ $task->updated_at?->format('d/m/Y H:i') }}</span>
                                </div>
                            </article>
                        @empty
                            <div class="task-empty">No hay tarjetas en esta columna.</div>
                        @endforelse
                    </div>
                    <div class="task-drop-hint">Arrastra aquí una tarjeta para cambiarla de estado.</div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
