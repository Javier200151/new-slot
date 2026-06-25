<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $metopa->name }}</title>

    <style>

        body{
            font-family: Arial, Helvetica, sans-serif;
            background:#f5f5f5;
            margin:40px;
            text-align:center;
        }

        .card{
            background:white;
            max-width:900px;
            margin:auto;
            padding:30px;
            border-radius:8px;
            box-shadow:0 0 15px rgba(0,0,0,.15);
        }

        img{
            max-width:100%;
            height:auto;
        }

        h1{
            margin-bottom:25px;
        }

        p{
            margin-top:30px;
            line-height:1.7;
            font-size:18px;
            text-align:left;
        }

        .volver{
            margin-top:40px;
            display:inline-block;
            text-decoration:none;
            padding:10px 20px;
            background:#444;
            color:white;
            border-radius:5px;
        }

    </style>

</head>
<body>

<div class="card">

    <h1>{{ $metopa->name }}</h1>

    @if($metopa->image_large)
        <img src="{{ asset('storage/'.$metopa->image_large) }}">
    @else
        <img src="{{ asset('storage/'.$metopa->image) }}">
    @endif

    @if($metopa->description)
        <p>
            {{ $metopa->description }}
        </p>
    @endif

    <a class="volver" href="javascript:history.back()">
        Volver
    </a>

</div>

</body>
</html>