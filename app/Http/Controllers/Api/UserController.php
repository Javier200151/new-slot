<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Todos los usuarios
    |--------------------------------------------------------------------------
    |
    | GET /api/users
    |
    */

    public function index(): AnonymousResourceCollection
    {
        /*
         * Cargamos también las relaciones necesarias
         * para evitar consultas repetidas.
         */
        $users = User::query()
            ->with([
                'status',
                'promo',
                'metopas',
            ])
            ->orderBy('nick')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Auditoría
        |--------------------------------------------------------------------------
        */

        activity('audit')
            ->event('api_users_read')
            ->withProperties([
                'api_client' =>
                    'external_api',

                'returned_users' =>
                    $users->count(),
            ])
            ->log('api_users_read');


        return UserResource::collection(
            $users
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Usuario individual por nick
    |--------------------------------------------------------------------------
    |
    | Ejemplo:
    |
    | GET /api/users/Rylod
    |
    */

    public function show(
        string $nick
    ): UserResource {
        $user = User::query()
            ->with([
                'status',
                'promo',
                'metopas',
            ])
            ->where(
                'nick',
                $nick
            )
            ->firstOrFail();


        /*
        |--------------------------------------------------------------------------
        | Auditoría
        |--------------------------------------------------------------------------
        */

        activity('audit')
            ->event('api_user_read')
            ->performedOn($user)
            ->withProperties([
                'api_client' =>
                    'external_api',

                'requested_user' => [
                    'id' =>
                        $user->id,

                    'nick' =>
                        $user->nick,
                ],
            ])
            ->log('api_user_read');


        return new UserResource(
            $user
        );
    }
}