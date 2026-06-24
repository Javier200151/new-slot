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
        }

        .promo {
            grid-row: 1 / span 2;
        }

        .banner {
            grid-column: 2;
        }

        .metopas {
            grid-column: 2;
            display: flex;
            flex-wrap: wrap;
            max-width: 500px;
        }

        img {
            display: block;
        }
    </style>
</head>
<body>

<div class="firma">

    @if($user->promo)
        <img class="promo" src="{{ asset('storage/' . $user->promo->image) }}">
    @endif

    @if($user->firma)
        <img class="banner" src="{{ asset('storage/' . $user->firma) }}">
    @endif

    <div class="metopas">
        @foreach($user->metopas as $metopa)
            <img src="{{ asset('storage/' . $metopa->image) }}" alt="{{ $metopa->name }}">
        @endforeach
    </div>

</div>

</body>
</html>