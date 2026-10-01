@php
    $editorId = $id ?? ('bbcode-editor-' . uniqid());
    $editorName = $name ?? 'body';
    $editorLabel = $label ?? 'Texto';
    $editorValue = $value ?? old($editorName, '');
    $editorRows = $rows ?? 7;
    $editorRequired = $required ?? false;
    $editorMaxlength = $maxlength ?? null;
    $editorPlaceholder = $placeholder ?? null;
    $editorHelp = $help ?? 'Puedes usar BBCode para dar formato, crear listas, citas, enlaces e insertar imágenes o GIF por URL.';
    $editorClass = trim('forum-editor__textarea ' . ($textareaClass ?? ''));
@endphp

<div class="forum-editor bbcode-editor" data-forum-editor>
    @if($editorLabel)
        <label for="{{ $editorId }}">{{ $editorLabel }}</label>
    @endif

    <div class="forum-editor__toolbar bbcode-editor__toolbar" role="toolbar" aria-label="Formato BBCode">
        <button type="button" title="Negrita" data-forum-wrap="b"><strong>B</strong></button>
        <button type="button" title="Cursiva" data-forum-wrap="i"><em>I</em></button>
        <button type="button" title="Subrayado" data-forum-wrap="u"><u>U</u></button>
        <button type="button" title="Tachado" data-forum-wrap="s"><s>S</s></button>
        <span class="forum-editor__sep"></span>
        <button type="button" title="Título" data-forum-wrap="h2">H2</button>
        <button type="button" title="Subtítulo" data-forum-wrap="h3">H3</button>
        <button type="button" title="Alinear a la izquierda" data-forum-wrap="left">↤</button>
        <button type="button" title="Centrar" data-forum-wrap="center">↔</button>
        <button type="button" title="Alinear a la derecha" data-forum-wrap="right">↦</button>
        <span class="forum-editor__sep"></span>
        <button type="button" title="Cita" data-forum-action="quote">❝</button>
        <button type="button" title="Spoiler" data-forum-action="spoiler">Spoiler</button>
        <button type="button" title="Código" data-forum-wrap="code">&lt;/&gt;</button>
        <button type="button" title="Lista" data-forum-action="list">☷</button>
        <button type="button" title="Lista numerada" data-forum-action="olist">1.</button>
        <span class="forum-editor__sep"></span>
        <button type="button" title="Enlace" data-forum-action="link">🔗</button>
        <button type="button" title="Imagen o GIF por URL" data-forum-action="image">🖼</button>
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
        class="{{ $editorClass }}"
        data-bbcode-input
        @if($editorRequired) required @endif
        @if($editorMaxlength) maxlength="{{ $editorMaxlength }}" @endif
        @if($editorPlaceholder) placeholder="{{ $editorPlaceholder }}" @endif
    >{{ $editorValue }}</textarea>

    @if($editorHelp)
        <small class="forum-editor__help">{{ $editorHelp }}</small>
    @endif
</div>
