<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Límite de memoria de PHP
    |--------------------------------------------------------------------------
    |
    | Se aplica también desde AppServiceProvider para que Artisan, workers y
    | peticiones web usen el mismo valor aunque el contenedor parta de un
    | php.ini más restrictivo. public/.user.ini cubre además PHP-FPM/CGI.
    |
    */
    'php_memory_limit' => env('PHP_MEMORY_LIMIT', '256M'),

    /*
    |--------------------------------------------------------------------------
    | Diagnóstico del editor ORBAT
    |--------------------------------------------------------------------------
    |
    | El profiling en cada apertura está apagado por defecto. Para diagnosticar
    | un evento concreto es preferible `php artisan orbat:profile ID`.
    |
    */
    'orbat_editor' => [
        'profile' => env('ORBAT_EDITOR_PROFILE', false),
        'warn_slots' => (int) env('ORBAT_EDITOR_WARN_SLOTS', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Directos automáticos
    |--------------------------------------------------------------------------
    |
    | Twitch se consulta mediante Helix usando las credenciales globales de la
    | aplicación. YouTube se comprueba desde la página /live del Channel ID,
    | por lo que no necesita API Key. La caché evita golpear las plataformas
    | cada vez que un visitante hace polling desde /directos.
    |
    */
    'streaming' => [
        'automatic_live' => env('STREAMS_AUTOMATIC_LIVE', true),
        'live_cache_seconds' => (int) env('STREAMS_LIVE_CACHE_SECONDS', 60),
        'event_match_before_hours' => (int) env('STREAMS_EVENT_MATCH_BEFORE_HOURS', 12),
        'event_match_after_hours' => (int) env('STREAMS_EVENT_MATCH_AFTER_HOURS', 12),
    ],
];
