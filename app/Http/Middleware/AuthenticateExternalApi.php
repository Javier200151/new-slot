<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateExternalApi
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /*
        |--------------------------------------------------------------------------
        | Token esperado
        |--------------------------------------------------------------------------
        */

        $expectedToken = (string) config(
            'external_api.token',
            ''
        );


        /*
        |--------------------------------------------------------------------------
        | Comprobar configuración
        |--------------------------------------------------------------------------
        */

        if ($expectedToken === '') {
            app(AuditLogger::class)->system(
                event: 'api_configuration_error',
                properties: [
                    'reason' =>
                        'EXTERNAL_API_TOKEN no está configurado.',
                ],
            );

            return response()->json([
                'message' => 'API no configurada.',
            ], 503);
        }


        /*
        |--------------------------------------------------------------------------
        | Bearer Token recibido
        |--------------------------------------------------------------------------
        */

        $providedToken = $request->bearerToken();


        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        |
        | Nunca almacenamos el token recibido en logs.
        |
        */

        if (
            $providedToken === null
            || ! hash_equals(
                $expectedToken,
                $providedToken
            )
        ) {
            app(AuditLogger::class)->security(
                event: 'api_auth_failed',
                properties: [
                    'reason' =>
                        'invalid_bearer_token',
                ],
            );

            return response()->json([
                'message' => 'No autorizado.',
            ], 401);
        }


        return $next($request);
    }
}