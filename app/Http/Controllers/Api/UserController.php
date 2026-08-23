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
        $users = User::query()
            ->with([
                'status',
                'promo',
                'metopas',
            ])
            ->orderBy('nick')
            ->get();

        /*
         * Auditoría.
         */
        activity('audit')
            ->event('api_users_read')
            ->withProperties([
                'client' => 'arma_reforger',
                'returned_users' => $users->count(),
            ])
            ->log('api_users_read');

        return UserResource::collection(
            $users
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Usuario por nick
    |--------------------------------------------------------------------------
    |
    | GET /api/users/Rylod
    |
    */

    public function show(
        string $identifier
    ): UserResource {

        /*
        |--------------------------------------------------------------------------
        | Buscar primero por nick
        |--------------------------------------------------------------------------
        */

        $user = User::query()
            ->with([
                'status',
                'promo',
                'metopas',
            ])
            ->where(
                'nick',
                $identifier
            )
            ->first();

        $lookupBy = 'nick';


        /*
        |--------------------------------------------------------------------------
        | Si no existe el nick, buscar por Steam ID
        |--------------------------------------------------------------------------
        */

        if ($user === null) {
            $user = User::query()
                ->with([
                    'status',
                    'promo',
                    'metopas',
                ])
                ->where(
                    'steam_id',
                    $identifier
                )
                ->first();

            $lookupBy = 'steam_id';
        }


        /*
        |--------------------------------------------------------------------------
        | No encontrado
        |--------------------------------------------------------------------------
        */

        if ($user === null) {
            abort(404);
        }


        /*
        |--------------------------------------------------------------------------
        | Auditoría
        |--------------------------------------------------------------------------
        */

        activity('audit')
            ->event('api_user_read')
            ->performedOn($user)
            ->withProperties([
                'client' =>
                    'arma_reforger',

                'lookup_by' =>
                    $lookupBy,

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