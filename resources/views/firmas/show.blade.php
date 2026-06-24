@php
    $metopas = $user->metopas;

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
        body {
            margin: 0;
            background: white;
        }

        .firma {
            display: inline-grid;
            grid-template-columns: auto auto;
            grid-template-rows: auto auto;
            align-items: start;
        }

        .promo {
            grid-column: 1;
            grid-row: 1;
            display: block;
        }

        .banner {
            grid-column: 2;
            grid-row: 1;
            display: block;
        }

        .metopas {
            grid-column: 1 / span 2;
            grid-row: 2;

            display: grid;
            grid-template-columns: repeat(7, auto);
            gap: 0;
        }

        .metopas img {
            display: block;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>

<div class="firma">

    @if($user->promo)
        <img
            class="promo"
            src="{{ asset('storage/' . $user->promo->image) }}"
            alt="Promo"
        >
    @endif

    @if($user->firma)
        <img
            class="banner"
            src="{{ asset('storage/' . $user->firma) }}"
            alt="{{ $user->nick }}"
        >
    @endif

    <div class="metopas">

        @for($i = 0; $i < $totalMostrar; $i++)

            @if($i < $totalMetopas)

                <img
                    src="{{ asset('storage/' . $metopas[$i]->image) }}"
                    alt="{{ $metopas[$i]->name }}"
                >

            @else

                <img
                    src="{{ asset('images/metopa_vacia.jpg') }}"
                    alt="Vacía"
                >

            @endif

        @endfor

    </div>

</div>

</body>
</html>