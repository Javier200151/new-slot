<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('meta-description', 'Metopas y reconocimientos de Squad ALPHA.')">

    <title>@yield('title', 'Metopas') - Squad ALPHA</title>

    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/metopas.css') }}">
    @stack('styles')
</head>

<body class="landing-body metopas-body @yield('body-class')">
    @yield('page-background')

    @include('partials.public-header')

    <main class="metopas-main">
        @yield('content')
    </main>

    @include('partials.public-footer', [
        'footerClass' => 'metopas-footer',
        'footerLinkUrl' => request()->routeIs('metopas.*')
            ? route('metopas.index')
            : route('home'),
        'footerLinkLabel' => request()->routeIs('metopas.*')
            ? 'Ver todas las metopas'
            : 'Volver al inicio',
    ])

    <script src="{{ asset('js/landing.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
