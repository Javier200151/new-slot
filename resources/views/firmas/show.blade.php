@php
    $statusName = $user->status?->name;

    $bannerSrc = $statusName === 'RECLUTA'
        ? asset('storage/firmas/recluta.png')
        : asset('storage/firmas/' . $user->nick . '.png');

    $metopas = $statusName === 'RECLUTA'
        ? collect()
        : $user->metopas;

    $porFila = 7;
    $totalMetopas = $metopas->count();
    $resto = $totalMetopas % $porFila;
    $relleno = $resto === 0 ? 0 : ($porFila - $resto);
    $totalMostrar = $totalMetopas + $relleno;
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Firma {{ $user->nick }}</title>

    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            background: transparent;
            width: max-content;
            height: max-content;
            overflow: hidden;
        }

        .firma {
            display: inline-block;
            font-size: 0;
            line-height: 0;
            margin: 0;
            padding: 0;
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
            @if($user->promo)
                <img class="promo" src="{{ asset('storage/' . $user->promo->image) }}" alt="Promo">
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