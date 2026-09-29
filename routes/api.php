<?php

use App\Http\Controllers\Api\EventOrbatController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AuthenticateExternalApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API externa de New Slot
|--------------------------------------------------------------------------
*/

/*
 * ORBAT público y actualizado de un evento, con asignaciones y reservas.
 *
 * No requiere Bearer Token. Se mantiene rate limiting para evitar abuso.
 *
 * GET /api/eventos/404/orbat
 */
Route::get(
    '/eventos/{event}/orbat',
    [
        EventOrbatController::class,
        'show',
    ]
)
    ->whereNumber('event')
    ->middleware('throttle:60,1')
    ->name('api.events.orbat');


/*
 * El resto de la API externa continúa protegida mediante Bearer Token.
 */
Route::middleware([
    AuthenticateExternalApi::class,
    'throttle:60,1',
])
    ->name('api.')
    ->group(function (): void {

        /*
         * Todos los usuarios.
         *
         * GET /api/users
         */
        Route::get(
            '/users',
            [
                UserController::class,
                'index',
            ]
        )->name('users.index');


        /*
         * Usuario por identifier.
         *
         * GET /api/users/{NICK}
         * GET /api/users/{STEAMID}
         */
        Route::get(
            '/users/{identifier}',
            [
                UserController::class,
                'show',
            ]
        )->name('users.show');
    });
