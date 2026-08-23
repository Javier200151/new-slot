<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AuthenticateExternalApi;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API externa de New Slot
|--------------------------------------------------------------------------
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
         * Usuario por nick.
         *
         * GET /api/users/Rylod
         */
        Route::get(
            '/users/{nick}',
            [
                UserController::class,
                'show',
            ]
        )->name('users.show');
    });