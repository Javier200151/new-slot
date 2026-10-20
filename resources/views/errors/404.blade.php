<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>404 · Squad ALPHA</title>
    <style>
    :root{color-scheme:dark}*{box-sizing:border-box}
    body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;color:#edf0eb;background:radial-gradient(ellipse at 40% 25%,#1b221c,#090b0d 75%);font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif}
    .main{width:min(680px,100%);text-align:center;border:1px solid #383e36;background:#111512;padding:clamp(26px,7vw,62px);border-radius:16px;box-shadow:0 30px 85px #0008}
    .top{letter-spacing:.28em;color:#e5a428;font-size:11px;font-weight:800}.code{font-size:clamp(100px,23vw,190px);line-height:1;font-weight:950;letter-spacing:-.08em;margin:10px 0;color:#f5f4ef;text-shadow:4px 5px 0 #8b651f}
    h1{font-size:18px;text-transform:uppercase;letter-spacing:.2em;color:#eeb044;margin:0 0 27px}
    .separator{height:1px;background:linear-gradient(90deg,transparent,#a17d34,transparent);margin:28px auto}
    blockquote{margin:0;font-size:clamp(19px,4vw,28px);line-height:1.5;font-weight:650;color:#f0f0e9}
    a{display:inline-block;margin-top:35px;padding:13px 24px;border-radius:7px;background:#e5a428;color:#14120c;font-weight:800;text-decoration:none;letter-spacing:.04em}
    a:hover,a:focus-visible{background:#f7c34e}.footer{margin-top:25px;color:#748174;font-size:11px;letter-spacing:.17em}
    </style>
</head>
<body>
    <main class="main">
        <div class="top">SQUAD ALPHA · CENTRO DE OPERACIONES</div>
        <div class="code">404</div>
        <h1>ERROR · PÁGINA NO ENCONTRADA</h1>
        @if(\App\Support\SquadEasterEggs::enabled('error404'))
            @php($phrase = \App\Support\SquadEasterEggs::random404Phrase())
            @if($phrase)
                <div class="separator"></div>
                <blockquote>«{{ $phrase }}»</blockquote>
            @endif
        @endif
        <a href="{{ route('home') }}">REGRESAR AL LA1</a>
        <div class="footer">SQA // POSICIÓN DESCONOCIDA</div>
    </main>
</body>
</html>
