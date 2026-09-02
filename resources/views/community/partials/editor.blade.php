@php
    $editorId = $id ?? ('forum-editor-' . uniqid());
    $editorName = $name ?? 'body';
    $editorLabel = $label ?? 'Mensaje';
    $editorValue = $value ?? old($editorName, '');
    $editorRows = $rows ?? 9;
@endphp

<div class="forum-editor" data-forum-editor>
    @if($editorLabel)
        <label for="{{ $editorId }}">{{ $editorLabel }}</label>
    @endif

    <div class="forum-editor__toolbar" role="toolbar" aria-label="Formato del mensaje">
        <button type="button" title="Negrita" data-forum-wrap="b"><strong>B</strong></button>
        <button type="button" title="Cursiva" data-forum-wrap="i"><em>I</em></button>
        <button type="button" title="Subrayado" data-forum-wrap="u"><u>U</u></button>
        <button type="button" title="Tachado" data-forum-wrap="s"><s>S</s></button>
        <span class="forum-editor__sep"></span>
        <button type="button" title="Título grande" data-forum-wrap="h2">H2</button>
        <button type="button" title="Subtítulo" data-forum-wrap="h3">H3</button>
        <button type="button" title="Cita" data-forum-action="quote">❝</button>
        <button type="button" title="Spoiler" data-forum-action="spoiler">Spoiler</button>
        <button type="button" title="Código" data-forum-wrap="code">&lt;/&gt;</button>
        <button type="button" title="Lista" data-forum-action="list">☷</button>
        <span class="forum-editor__sep"></span>
        <button type="button" title="Enlace" data-forum-action="link">🔗</button>
        <button type="button" title="Imagen por URL" data-forum-action="image">🖼</button>
        <button type="button" title="Separador" data-forum-action="hr">―</button>

        <div class="forum-editor__colors" title="Color de texto">
            @foreach([
                '#f8fafc' => 'Blanco',
                '#94a3b8' => 'Gris',
                '#f87171' => 'Rojo',
                '#fb923c' => 'Naranja',
                '#facc15' => 'Amarillo',
                '#4ade80' => 'Verde',
                '#22d3ee' => 'Cian',
                '#60a5fa' => 'Azul',
                '#c084fc' => 'Morado',
                '#f472b6' => 'Rosa',
            ] as $color => $colorLabel)
                <button
                    type="button"
                    class="forum-editor__color"
                    style="--editor-color:{{ $color }}"
                    title="{{ $colorLabel }}"
                    data-forum-color="{{ $color }}"
                    aria-label="{{ $colorLabel }}"
                ></button>
            @endforeach
        </div>
    </div>

    <textarea
        id="{{ $editorId }}"
        name="{{ $editorName }}"
        rows="{{ $editorRows }}"
        class="forum-editor__textarea"
        required
    >{{ $editorValue }}</textarea>

    <small class="forum-editor__help">
        Puedes combinar formato, citas, spoilers, enlaces e imágenes por URL. El contenido se procesa como BBCode seguro; no se admite HTML directo.
    </small>
</div>
