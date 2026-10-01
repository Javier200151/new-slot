@include('partials.bbcode-editor', [
    'id' => $id ?? null,
    'name' => $name ?? 'body',
    'label' => $label ?? 'Mensaje',
    'value' => $value ?? old($name ?? 'body', ''),
    'rows' => $rows ?? 9,
    'required' => $required ?? true,
    'maxlength' => $maxlength ?? null,
    'placeholder' => $placeholder ?? null,
    'help' => $help ?? 'Puedes combinar formato, listas, citas, spoilers, enlaces e imágenes o GIF por URL. El contenido se procesa como BBCode seguro; no se admite HTML directo.',
])
