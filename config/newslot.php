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
];
