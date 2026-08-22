@php
    $statusName = $user->status?->name;
    $fitFirma = request()->boolean('fit');
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

    $relleno = $resto === 0
        ? 0
        : ($porFila - $resto);

    $totalMostrar = $totalMetopas + $relleno;

    $filasMetopas = $totalMostrar > 0
        ? (int) ceil($totalMostrar / $porFila)
        : 0;


    /*
    |--------------------------------------------------------------------------
    | Medidas reales de la firma
    |--------------------------------------------------------------------------
    */

    $anchoFirma = 600;

    $altoFilaSuperior = 100;

    $altoFilaMetopa = 25;

    $altoFirma =
        $altoFilaSuperior
        + ($filasMetopas * $altoFilaMetopa);


    /*
    |--------------------------------------------------------------------------
    | Escala móvil
    |--------------------------------------------------------------------------
    */

    $escalaMovil = 0.55;

    $anchoMovil = (int) ceil(
        $anchoFirma * $escalaMovil
    );

    $altoMovil = (int) ceil(
        $altoFirma * $escalaMovil
    );
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
            display: block;

            width: {{ $anchoFirma }}px;

            margin: 0;
            padding: 0;

            font-size: 0;
            line-height: 0;

            transform-origin: top left;
        }

        @if(! $fitFirma)

            /*
            |--------------------------------------------------------------------------
            | Firma abierta directamente
            |--------------------------------------------------------------------------
            */

            html,
            body {
                width: {{ $anchoFirma }}px;
                height: {{ $altoFirma }}px;
            }


            @media (max-width: 700px) {

                .firma {
                    transform: scale(
                        {{ $escalaMovil }}
                    );
                }

                html,
                body {
                    width: {{ $anchoMovil }}px;
                    height: {{ $altoMovil }}px;
                }

            }

        @else

            /*
            |--------------------------------------------------------------------------
            | Firma embebida en un perfil
            |--------------------------------------------------------------------------
            */

            html,
            body {
                width: 100%;
            }

        @endif
        /*
        |--------------------------------------------------------------------------
        | Cabecera
        |--------------------------------------------------------------------------
        |
        | Promo  = 100 px
        | Banner = 500 px
        | Total  = 600 px exactos
        |
        */

        .fila-superior {
            display: flex;

            width: {{ $anchoFirma }}px;
            height: {{ $altoFilaSuperior }}px;

            margin: 0;
            padding: 0;

            gap: 0;

            align-items: flex-start;

            overflow: hidden;
        }


        .promo {
            display: block;

            flex: 0 0 100px;

            width: 100px;
            height: 100px;

            margin: 0;
            padding: 0;

            border: 0;

            object-fit: fill;
        }
        
        .promo--empty {
            background: transparent;
        }

        .banner {
            display: block;

            flex: 0 0 500px;

            width: 500px;
            height: 100px;

            margin: 0;
            padding: 0;

            border: 0;

            object-fit: fill;
        }


        /*
        |--------------------------------------------------------------------------
        | Metopas
        |--------------------------------------------------------------------------
        |
        | No usamos el ancho natural de 86px.
        |
        | 600 / 7 = 85,714285... px
        |
        | CSS Grid reparte los decimales automáticamente para que
        | la séptima metopa acabe EXACTAMENTE en el pixel 600.
        |
        */

        .metopas {
            display: grid;

            grid-template-columns:
                repeat(7, minmax(0, 1fr));

            width: {{ $anchoFirma }}px;

            margin: 0;
            padding: 0;

            gap: 0;

            font-size: 0;
            line-height: 0;

            overflow: hidden;
        }


        /*
        * Tanto una metopa enlazada como una metopa vacía
        * deben ocupar exactamente una celda del grid.
        */

        .metopas > a {
            display: block;

            width: 100%;
            height: {{ $altoFilaMetopa }}px;

            margin: 0;
            padding: 0;

            border: 0;

            overflow: hidden;

            font-size: 0;
            line-height: 0;
        }


        .metopas > img {
            display: block;

            width: 100%;
            height: {{ $altoFilaMetopa }}px;

            margin: 0;
            padding: 0;

            border: 0;

            object-fit: fill;
        }


        /*
        * Imagen dentro de los enlaces.
        */

        .metopas > a > img {
            display: block;

            width: 100%;
            height: 100%;

            margin: 0;
            padding: 0;

            border: 0;

            object-fit: fill;
        }
    </style>
</head>
<body>

@if($statusName !== 'USUARIO')
    <div class="firma">

        <div class="fila-superior">
            @if($promoSrc)

        <img
                class="promo"
                src="{{ $promoSrc }}"
                alt="Promo"
            >

        @else

            <div
                class="promo promo--empty"
                aria-hidden="true"
            ></div>

        @endif

            <img class="banner" src="{{ $bannerSrc }}" alt="{{ $user->nick }}">
        </div>

        <div class="metopas">
            @for($i = 0; $i < $totalMostrar; $i++)
                @if($i < $totalMetopas)
                    <a href="{{ route('metopas.show',$metopas[$i]) }}" target="_blank">
                        <img
                            src="{{ asset(
                                'storage/' . $metopas[$i]->image
                            ) }}"
                            alt="{{ $metopas[$i]->name }}"
                        >
                    </a>
                @else
                    <img src="{{ asset('images/metopa_vacia.jpg') }}" alt="Vacía">
                @endif
            @endfor
        </div>

    </div>
@endif
@if($fitFirma)

    <script>
        (() => {
            const signature =
                document.querySelector('.firma');

            if (!signature) {
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Geometría original
            |--------------------------------------------------------------------------
            */

            const baseWidth =
                {{ $anchoFirma }};

            const baseHeight =
                {{ $altoFirma }};


            /*
            |--------------------------------------------------------------------------
            | Ajustar al ancho del iframe
            |--------------------------------------------------------------------------
            */

            const fitSignature = () => {

                const availableWidth =
                    Math.max(
                        document.documentElement
                            .clientWidth,
                        1
                    );


                /*
                 * Ejemplo:
                 *
                 * iframe = 1000 px
                 * firma  = 600 px
                 *
                 * 1000 / 600 = 1.666...
                 */
                const scale =
                    availableWidth
                    / baseWidth;


                /*
                 * Escalamos la firma ENTERA:
                 *
                 * promo
                 * banner
                 * todas las metopas
                 */
                signature.style.transform =
                    `scale(${scale})`;


                /*
                 * Calculamos la altura final.
                 */
                const scaledHeight =
                    Math.ceil(
                        baseHeight
                        * scale
                    );


                document.documentElement.style.height =
                    `${scaledHeight}px`;

                document.body.style.height =
                    `${scaledHeight}px`;


                /*
                 * Ajustamos la altura del iframe
                 * que contiene esta firma.
                 */
                try {

                    if (window.frameElement) {

                        window.frameElement.style.height =
                            `${scaledHeight}px`;

                    }

                } catch {
                    /*
                     * Si abrimos /firmas/... directamente,
                     * no hay nada que hacer.
                     */
                }
            };


            /*
            |--------------------------------------------------------------------------
            | Esperar imágenes
            |--------------------------------------------------------------------------
            */

            const images = [
                ...document.querySelectorAll('img')
            ];


            const pendingImages =
                images.filter(
                    (image) => !image.complete
                );


            if (pendingImages.length === 0) {

                fitSignature();

            } else {

                let remaining =
                    pendingImages.length;


                pendingImages.forEach(
                    (image) => {

                        image.addEventListener(
                            'load',
                            () => {

                                remaining--;


                                if (remaining === 0) {

                                    fitSignature();

                                }

                            },
                            {
                                once: true,
                            }
                        );

                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Responsive
            |--------------------------------------------------------------------------
            */

            window.addEventListener(
                'resize',
                fitSignature
            );

        })();
    </script>

@endif
</body>
</html>