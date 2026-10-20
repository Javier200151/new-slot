@php
    $eggG = \App\Support\SquadEasterEggs::enabled('grenade')
        && \App\Support\SquadEasterEggs::grenadeAllowedOn(request()->path());
    $eggN = \App\Support\SquadEasterEggs::enabled('nvg');
    $eggY = \App\Support\SquadEasterEggs::enabled('zeus');
    $eggKonami = \App\Support\SquadEasterEggs::enabled('konami');
    $zeusImage = $eggY ? \App\Support\SquadEasterEggs::publicMediaUrl('zeus', 'image_path') : null;
    $zeusAudio = $eggY ? \App\Support\SquadEasterEggs::publicMediaUrl('zeus', 'audio_path') : null;
@endphp
@if($eggG || $eggN || $eggY || $eggKonami)
    @once
        <link rel="stylesheet" href="{{ asset('css/sqa-easter-eggs.css') }}?v={{ filemtime(public_path('css/sqa-easter-eggs.css')) }}">
        <div data-sqa-eggs
            data-grenade="{{ $eggG ? '1' : '0' }}"
            data-grenade-cooldown="{{ max(1, min(86400, (int) (\App\Support\SquadEasterEggs::settings('grenade')['cooldown_seconds'] ?? 120))) }}"
            data-nvg="{{ $eggN ? '1' : '0' }}"
            data-zeus="{{ $eggY && $zeusImage ? '1' : '0' }}"
            data-konami="{{ $eggKonami ? '1' : '0' }}"
            data-zeus-image="{{ $zeusImage ?? '' }}"
            data-zeus-audio="{{ $zeusAudio ?? '' }}"
        >
            <div class="sqa-egg-grenade" role="status" aria-live="polite" hidden>
                <span class="sqa-egg-grenade__icon" aria-hidden="true">●</span>
                <div class="sqa-egg-grenade__content"><strong>¿QUIÉN ES TU TUTOR?</strong><small>INCIDENTE DE GRANADA · FUEGO AMIGO</small></div>
            </div>
            <div class="sqa-egg-zeus" aria-label="Ojo de Zeus" hidden>
                @if($zeusImage)
                    <img src="{{ $zeusImage }}" alt="Ojo de Zeus">
                @endif
            </div>
            <div class="sqa-egg-konami" role="dialog" aria-modal="true" aria-label="Trucos secretos ALPHA" hidden>
                <div class="sqa-egg-konami__window">
                    <button type="button" class="sqa-egg-konami__close" data-sqa-egg-close aria-label="Cerrar">×</button>
                    <small>SQA // CHEAT CODE DETECTED</small>
                    <h2>OPERACIÓN: VENTAJA IMPOSIBLE</h2>
                    <p>Has desbloqueado el menú secreto de trucos. ¿Qué ventaja deseas activar?</p>
                    <div class="sqa-egg-konami__perks">
                        <span>☑ Munición infinita</span><span>☑ Puntería perfecta</span><span>☑ Inmunidad al fuego amigo</span>
                    </div>
                    <p class="sqa-egg-konami__denied">ACCESO DENEGADO: Mando no autoriza trucos en operativos.</p>
                    <p class="sqa-egg-konami__small">Solo animación humorística; no se modifica ningún dato ni permiso.</p>
                </div>
            </div>
        </div>
        <script src="{{ asset('js/sqa-easter-eggs.js') }}?v={{ filemtime(public_path('js/sqa-easter-eggs.js')) }}" defer></script>
    @endonce
@endif
