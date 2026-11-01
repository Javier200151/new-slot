@php
    $result = $this->results();
    $periods = \App\Services\ActiveMemberInactivityService::periods();
@endphp
<div class="ns-pd-custom-card ns-pd-inactivity-card">
    <div class="ns-pd-custom-header ns-pd-inactivity-header">
        <div>
            <span class="ns-pd-kicker">Seguimiento de miembros</span>
            <h3>Tiempo sin jugar</h3>
        </div>
        <label class="ns-pd-inactivity-filter">
            <span>Mostrar</span>
            <select wire:model.live="period" aria-label="Filtrar miembros por tiempo sin jugar">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="ns-pd-inactivity-summary">
        @if ($period === 'top5')
            Los cinco miembros ACTIVO con más tiempo desde su última partida.
        @else
            <strong>{{ $result['total'] }}</strong> {{ $result['total'] === 1 ? 'miembro ACTIVO' : 'miembros ACTIVO' }} sin jugar durante al menos {{ $periods[$period] ?? 'el período elegido' }}.
        @endif
    </div>

    <div class="ns-pd-inactivity-list">
        @forelse ($result['members'] as $item)
            @php
                $member = $item['user'];
                $canOpen = \App\Filament\Resources\Users\UserResource::canEdit($member);
                $url = $canOpen ? \App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $member]) : null;
            @endphp
            <div class="ns-pd-inactivity-row" wire:key="inactivity-{{ $member->id }}">
                <div class="ns-pd-inactivity-person">
                    @if ($url)
                        <a class="ns-pd-inactivity-name" href="{{ $url }}" title="Abrir ficha del miembro">{{ $member->nick }}</a>
                    @else
                        <strong class="ns-pd-inactivity-name">{{ $member->nick }}</strong>
                    @endif
                    <small>
                        {{ $item['last_event_at']
                            ? 'Última partida: ' . $item['last_event_at']->format('d/m/Y')
                            : 'Sin partidas registradas' }}
                    </small>
                </div>
                <span class="ns-pd-inactivity-days">{{ number_format($item['days'], 0, ',', '.') }} {{ $item['days'] === 1 ? 'día' : 'días' }}</span>
            </div>
        @empty
            <div class="ns-pd-card-empty">No hay miembros ACTIVO que cumplan este período.</div>
        @endforelse
    </div>
</div>
