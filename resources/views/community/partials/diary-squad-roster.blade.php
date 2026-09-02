@php
    $colors = [
        'red' => ['label' => 'Rojo', 'hex' => '#ef4444'],
        'blue' => ['label' => 'Azul', 'hex' => '#3b82f6'],
        'green' => ['label' => 'Verde', 'hex' => '#22c55e'],
        'yellow' => ['label' => 'Amarillo', 'hex' => '#eab308'],
        'white' => ['label' => 'Blanco', 'hex' => '#f8fafc'],
        'orange' => ['label' => 'Naranja', 'hex' => '#f59e0b'],
        'purple' => ['label' => 'Morado', 'hex' => '#a855f7'],
        'pink' => ['label' => 'Rosa', 'hex' => '#ec4899'],
        'cyan' => ['label' => 'Cian', 'hex' => '#06b6d4'],
    ];
@endphp

@if(!empty($roster))
    <section class="diary-roster-snapshot">
        <div class="diary-roster-snapshot__head">
            <span class="community-kicker">NUMERACIÓN DE ESCUADRA</span>
            @if(filled($group ?? null))
                <strong>{{ $group }}</strong>
            @endif
        </div>
        <div class="diary-roster-snapshot__grid">
            @foreach($roster as $member)
                @php
                    $color = $colors[$member['color'] ?? ''] ?? null;
                @endphp
                <div class="diary-roster-member">
                    <span
                        class="diary-roster-member__number"
                        @if($color) style="--team-color: {{ $color['hex'] }}" @endif
                    >
                        {{ $member['number'] ?? '—' }}
                    </span>
                    <div>
                        <strong>{{ $member['nick'] ?? 'Jugador' }}</strong>
                        <small>
                            {{ $member['slot_name'] ?? 'Slot' }}
                            @if(filled($member['slot_type'] ?? null))
                                · {{ $member['slot_type'] }}
                            @endif
                            @if($color)
                                · Equipo {{ $color['label'] }}
                            @endif
                        </small>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
