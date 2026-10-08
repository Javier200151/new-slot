<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $type === 'reactivation' ? 'Correo de reactivación' : 'Correo de alta' }} · NewSlot</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; background:#090b0f; color:#f8fafc; font-family:Inter,Arial,Helvetica,sans-serif; }
        .page { min-height:100vh; }
        .bar { position:sticky; top:0; z-index:20; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:14px 20px; border-bottom:1px solid #252a32; background:rgba(9,11,15,.96); backdrop-filter:blur(10px); }
        .bar__left { display:flex; align-items:center; gap:14px; min-width:0; }
        .back { color:#9ca3af; text-decoration:none; font-weight:700; font-size:13px; }
        .title { margin:0; font-size:16px; font-weight:850; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .btn { border:1px solid #343b46; border-radius:8px; padding:9px 13px; background:#11161e; color:#f8fafc; font-size:12px; font-weight:800; text-decoration:none; cursor:pointer; }
        .btn--primary { border-color:#f5a900; background:#f5a900; color:#090b0f; }
        .notice { max-width:1240px; margin:16px auto 0; padding:0 18px; }
        .notice > div { padding:10px 13px; border:1px solid rgba(34,197,94,.32); border-radius:8px; background:rgba(34,197,94,.08); color:#86efac; font-size:12px; }
        .workspace { width:min(1240px,100%); margin:0 auto; padding:18px; display:grid; grid-template-columns:1fr; gap:18px; }
        .workspace.is-editing { grid-template-columns:minmax(320px,420px) minmax(0,1fr); align-items:start; }
        .editor { border:1px solid #252a32; border-radius:12px; background:#11141a; padding:18px; position:sticky; top:76px; }
        .editor h2 { margin:0 0 7px; font-size:16px; }
        .editor p { margin:0 0 18px; color:#8f98a8; font-size:12px; line-height:1.5; }
        label { display:block; margin:14px 0 7px; font-size:12px; font-weight:800; }
        input, textarea { width:100%; border:1px solid #343b46; border-radius:8px; background:#090c11; color:#f8fafc; padding:11px 12px; font:inherit; font-size:13px; outline:none; }
        input:focus, textarea:focus { border-color:#f5a900; box-shadow:0 0 0 2px rgba(245,169,0,.10); }
        textarea { min-height:220px; resize:vertical; line-height:1.55; }
        .hint { margin-top:7px; color:#6f7888; font-size:11px; line-height:1.45; }
        .editor__actions { display:flex; gap:8px; margin-top:16px; }
        .preview { min-width:0; }
        .mail-meta { display:flex; justify-content:space-between; gap:12px; align-items:center; margin:0 0 10px; color:#8f98a8; font-size:11px; }
        .mail-meta strong { color:#f8fafc; overflow-wrap:anywhere; }
        iframe { width:100%; min-height:calc(100vh - 120px); border:1px solid #252a32; border-radius:12px; background:#05070a; }
        @media (max-width: 900px) {
            .workspace.is-editing { grid-template-columns:1fr; }
            .editor { position:static; }
            .bar { align-items:flex-start; }
            .bar__left { align-items:flex-start; flex-direction:column; gap:5px; }
            iframe { min-height:780px; }
        }
    </style>
</head>
<body>
<div class="page">
    <header class="bar">
        <div class="bar__left">
            <a class="back" href="{{ $backUrl }}">← Config. procedimientos</a>
            <h1 class="title">{{ $type === 'reactivation' ? 'Correo · Reactivación' : 'Correo · Alta de miembro' }}</h1>
        </div>
        <div class="actions">
            @if($editing)
                <a class="btn" href="{{ route('member-procedure-email-preview.show', ['type' => $type]) }}">Salir de edición</a>
            @elseif($canEdit)
                <a class="btn btn--primary" href="{{ route('member-procedure-email-preview.show', ['type' => $type, 'edit' => 1]) }}">Editar texto</a>
            @endif
        </div>
    </header>

    @if(session('status'))
        <div class="notice"><div>{{ session('status') }}</div></div>
    @endif

    <main class="workspace {{ $editing ? 'is-editing' : '' }}">
        @if($editing && $canEdit)
            <form class="editor" method="POST" action="{{ route('member-procedure-email-preview.update', ['type' => $type]) }}">
                @csrf
                <h2>Editar únicamente el texto</h2>
                <p>El diseño, colores, secciones, tarjetas y botones están bloqueados. Aquí solo puedes modificar el asunto y el mensaje principal.</p>

                <label for="subject">Asunto</label>
                <input id="subject" name="subject" maxlength="255" value="{{ old('subject', $template['subject']) }}">
                @error('subject')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror

                <label for="body">Mensaje principal</label>
                <textarea id="body" name="body" maxlength="6000">{{ old('body', $template['body']) }}</textarea>
                <div class="hint">Variable disponible: <strong>&#123;&#123;nick&#125;&#125;</strong>. Si dejas un campo vacío se recuperará el texto predeterminado.</div>
                @error('body')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror

                <div class="editor__actions">
                    <button class="btn btn--primary" type="submit">Guardar y previsualizar</button>
                    <a class="btn" href="{{ route('member-procedure-email-preview.show', ['type' => $type]) }}">Cancelar</a>
                </div>
            </form>
        @endif

        <section class="preview">
            <div class="mail-meta">
                <span>Vista real del correo</span>
                <strong>{{ $preview['subject'] }}</strong>
            </div>
            <iframe title="Previsualización del correo" srcdoc="{{ $preview['html'] }}"></iframe>
        </section>
    </main>
</div>
</body>
</html>
