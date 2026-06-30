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
    $filasMetopas = $totalMostrar > 0
        ? (int) ceil($totalMostrar / $porFila)
        : 0;

    $escalaMovil = 0.55;

    $anchoFirma = 620;
    $altoFilaSuperior = 110;
    $altoFilaMetopa = 32;

    $margenExtra = 20;

    $altoFirma = $altoFilaSuperior + ($filasMetopas * $altoFilaMetopa) + $margenExtra;

    $anchoMovil = (int) ceil($anchoFirma * $escalaMovil);
    $altoMovil = (int) ceil($altoFirma * $escalaMovil);
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
            font-size: 0;
            line-height: 0;
            margin: 0;
            padding: 0;
            transform-origin: top left;
        }

        @media (max-width: 700px) {
            .firma {
                transform: scale({{ $escalaMovil }});
            }

            html,
            body {
                width: {{ $anchoMovil }}px;
                height: {{ $altoMovil }}px;
            }
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

</body>
</html>
