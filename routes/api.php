<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AuthenticateExternalApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API externa de New Slot
|--------------------------------------------------------------------------
|
| API de solo lectura destinada a integraciones externas.
|
*/

Route::middleware([
    AuthenticateExternalApi::class,
    'throttle:60,1',
])
    ->name('api.')
    ->group(function (): void {

        /*
         * GET /api/users
         *
         * Devuelve todos los usuarios.
         */
        Route::get(
            '/users',
            [
                UserController::class,
                'index',
            ]
        )->name('users.index');


        /*
         * GET /api/users/Rylod
         *
         * Devuelve un usuario mediante su nick.
         */
        Route::get(
            '/users/{nick}',
            [
                UserController::class,
                'show',
            ]
        )->name('users.show');
    });