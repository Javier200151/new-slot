<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Administrador principal protegido
    |--------------------------------------------------------------------------
    |
    | AdminSeeder ya utiliza ADMIN_EMAIL para identificar la cuenta principal.
    | El Bloque H usa ese mismo valor únicamente para marcarla por primera vez;
    | después la protección queda persistida en users.is_protected_admin.
    |
    */
    'protected_admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),

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

    /*
    |--------------------------------------------------------------------------
    | Procedimientos de miembros / ArmaSquads
    |--------------------------------------------------------------------------
    |
    | La API key nunca se guarda en base de datos ni se muestra en Filament.
    | El Squad ID sí es configuración funcional y se selecciona en Filament.
    |
    */
    'procedures' => [
        'armasquads' => [
            'enabled' => env('ARMASQUADS_ENABLED', false),
            'base_url' => env('ARMASQUADS_BASE_URL', 'https://armasquads.com/api/v1'),
            'api_key' => env('ARMASQUADS_API_KEY'),
            'timeout' => (int) env('ARMASQUADS_TIMEOUT', 10),
        ],
    ],
];
