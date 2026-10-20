<x-filament-panels::page>
    <div class="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm">
        <strong>Panel secreto de Squad ALPHA</strong>
        <p class="mt-1 text-gray-400">Configura los secretos de la web. Los miembros no verán este panel.</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit" icon="heroicon-o-check-circle">Guardar configuración</x-filament::button>
    </form>

    <x-filament::section>
        <x-slot name="heading">Archivos del Ojo de Zeus</x-slot>
        <x-slot name="description">Elige la imagen del ojo y, si quieres, un sonido para cuando pulses Y.</x-slot>

        @if(session('zeus_upload_success'))
            <p class="rounded-lg border border-green-500/40 bg-green-500/10 p-3 text-sm text-green-500" role="status">{{ session('zeus_upload_success') }}</p>
        @endif
        @if($errors->has('zeus_image') || $errors->has('zeus_audio'))
            <div class="rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-500" role="alert">
                @foreach($errors->get('zeus_image') as $error)<p>{{ $error }}</p>@endforeach
                @foreach($errors->get('zeus_audio') as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <style>
            .sqa-zeus-upload { display:grid; gap:1.1rem; }
            .sqa-zeus-upload__fields { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr)); gap:1rem; }
            .sqa-zeus-upload__field { display:flex; flex-direction:column; gap:.55rem; min-width:0; }
            .sqa-zeus-upload__label { color:inherit; font-size:.875rem; font-weight:600; }
            .sqa-zeus-upload__input { width:100%; max-width:100%; font-size:.85rem; color:inherit; padding:.65rem; border:1px solid rgba(156,163,175,.28); border-radius:.65rem; background:rgba(127,127,127,.055); }
            .sqa-zeus-upload__input::file-selector-button { margin-right:1rem; border:1px solid rgba(156,163,175,.30); border-radius:.45rem; padding:.45rem .75rem; background:rgba(127,127,127,.15); color:inherit; cursor:pointer; font:inherit; font-weight:600; }
            .sqa-zeus-upload__input::file-selector-button:hover { background:rgba(127,127,127,.25); }
            .sqa-zeus-upload__hint { font-size:.8rem; color:#9ca3af; }
            .sqa-zeus-upload__saved { display:flex; flex-wrap:wrap; gap:.65rem 1.3rem; border-top:1px solid rgba(156,163,175,.17); padding-top:1rem; font-size:.85rem; }
            .sqa-zeus-upload__saved a { color:#f59e0b; text-decoration:underline; text-underline-offset:3px; }
        </style>
        <form method="POST" action="{{ url('/admin/easter_eggs/zeus-media') }}" enctype="multipart/form-data" class="sqa-zeus-upload">
            @csrf
            <div class="sqa-zeus-upload__fields">
                <div class="sqa-zeus-upload__field">
                    <label class="sqa-zeus-upload__label" for="sqa-zeus-png">Imagen del ojo (PNG)</label>
                    <input id="sqa-zeus-png" name="zeus_image" type="file" accept=".png,image/png" class="sqa-zeus-upload__input" />
                </div>
                <div class="sqa-zeus-upload__field">
                    <label class="sqa-zeus-upload__label" for="sqa-zeus-audio">Sonido (opcional)</label>
                    <input id="sqa-zeus-audio" name="zeus_audio" type="file" accept=".mp3,.ogg,.wav,audio/mpeg,audio/ogg,audio/wav" class="sqa-zeus-upload__input" />
                </div>
            </div>
            <p class="sqa-zeus-upload__hint">Máximo 4 MB por archivo. Puedes cambiar uno sin volver a subir el otro.</p>
            <div><x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">Subir archivos</x-filament::button></div>
        </form>

        @php
            $zeusImg = \App\Support\SquadEasterEggs::publicMediaUrl('zeus', 'image_path');
            $zeusSound = \App\Support\SquadEasterEggs::publicMediaUrl('zeus', 'audio_path');
        @endphp
        <div class="sqa-zeus-upload__saved">
            <div><strong>Imagen actual:</strong>
                @if($zeusImg)<a href="{{ $zeusImg }}" target="_blank" rel="noopener noreferrer">Ver imagen ↗</a>
                @else <span class="text-gray-400">Sin imagen</span> @endif
            </div>
            <div><strong>Sonido actual:</strong>
                @if($zeusSound)<a href="{{ $zeusSound }}" target="_blank" rel="noopener noreferrer">Escuchar ↗</a>
                @else <span class="text-gray-400">Sin sonido</span> @endif
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">¿Cómo se descubren?</x-slot>
        

        <x-filament::button color="warning" wire:click="toggleSpoilers" type="button" icon="heroicon-o-eye">
            {{ $spoilersVisible ? 'Ocultar spoilers' : '¡Cuidado, spoilers!' }}
        </x-filament::button>

        @if($spoilersVisible)
            <div class="mt-4 space-y-3 text-sm">
                <p><strong>G — ¿Quién es tu tutor?</strong> Pulsa G en cualquiera de las ocho páginas permitidas, sin escribir en un campo.</p>
                <p><strong>404 — Malla errónea.</strong> Visita una URL pública inexistente para ver una de las frases activas.</p>
                <p><strong>NVG.</strong> Pulsa alguna N del texto «Comunidad de simulación militar» en la portada o la N de «Eventos» en el calendario. Vuelve a pulsar cualquiera para salir.</p>
                <p><strong>Y — Ojo de Zeus.</strong> Sube la imagen, activa el ojo y pulsa Y cuando no estés escribiendo.</p>
                <p><strong>Konami.</strong> ↑ ↑ ↓ ↓ ← → ← → B A (los trucos son una broma).</p>
                <p><strong>Fallujah 2012.</strong> Haz clic en la radio del lateral izquierdo el 27 de abril o selecciona «Mostrar ahora» en su configuración.</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
