@php
    $statusName = $user->status?->name;

    $bannerSrc = $statusName === 'RECLUTA'
        ? asset('storage/firmas/recluta.png')
        : asset('storage/firmas/' . $user->nick . '.png');

    $promoSrc = $statusName === 'RECLUTA'
        ? asset('storage/promos/recluta.png')
        : ($user->promo ? asset('storage/' . $user->promo->image) : null);
            
    $metopas = $statusName === 'RECLUTA'
        ? collect()
        : $user->metopas;

    $porFila = 7;
    $totalMetopas = $metopas->count();
    $resto = $totalMetopas % $porFila;
    $relleno = $resto === 0 ? 0 : ($porFila - $resto);
    $totalMostrar = $totalMetopas + $relleno;
    $anchoFirma = 620;
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Firma {{ $user->nick }}</title>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: transparent;
        }

        .firma {
            display: inline-block;
            width: {{ $anchoFirma }}px;
            font-size: 0;
            line-height: 0;
            margin: 0;
            padding: 0;
            transform-origin: top left;
        }

        .fila-superior {
            display: flex;
            align-items: flex-start;
            gap: 0;
        }

        .promo,
        .banner,
        .metopas img {
            display: block;
            margin: 0;
            padding: 0;
            border: 0;
        }

        .metopas {
            display: grid;
            grid-template-columns: repeat(7, auto);
            gap: 0;
        }
        .metopas a {
            display: block;
            margin: 0;
            padding: 0;
            border: 0;
        }
    </style>
</head>
<body>

@if($statusName !== 'USUARIO')
    <div class="firma">

        <div class="fila-superior">
            @if($promoSrc)
                <img class="promo" src="{{ $promoSrc }}" alt="Promo">
            @endif

            <img class="banner" src="{{ $bannerSrc }}" alt="{{ $user->nick }}">
        </div>

        <div class="metopas">
            @for($i = 0; $i < $totalMostrar; $i++)
                @if($i < $totalMetopas)
                    <a href="{{ route('metopas.show',$metopas[$i]) }}" target="_blank">
                        <img src="{{ asset('storage/'.$metopas[$i]->image) }}">
                    </a>
                @else
                    <img src="{{ asset('images/metopa_vacia.jpg') }}" alt="Vacía">
                @endif
            @endfor
        </div>

    </div>
@endif

<script>
    (() => {
        const signature = document.querySelector('.firma');

        if (! signature) {
            return;
        }

        const signatureWidth = {{ $anchoFirma }};

        const fitSignature = () => {
            const availableWidth = Math.max(document.documentElement.clientWidth, 1);
            const scale = Math.min(1, availableWidth / signatureWidth);

            signature.style.transform = `scale(${scale})`;

            const height = Math.ceil(signature.getBoundingClientRect().height);
            document.documentElement.style.height = `${height}px`;
            document.body.style.height = `${height}px`;

            try {
                if (window.frameElement) {
                    window.frameElement.style.height = `${height}px`;
                }
            } catch {
                // La firma también puede abrirse como una página independiente.
            }
        };

        document.querySelectorAll('img').forEach((image) => {
            if (! image.complete) {
                image.addEventListener('load', fitSignature, { once: true });
            }
        });

        new ResizeObserver(fitSignature).observe(signature);
        window.addEventListener('resize', fitSignature);
        window.requestAnimationFrame(fitSignature);
    })();
</script>

</body>
</html>
