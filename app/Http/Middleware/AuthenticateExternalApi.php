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
        $expectedToken = (string) config(
            'external_api.token',
            ''
        );

        /*
        |--------------------------------------------------------------------------
        | API mal configurada
        |--------------------------------------------------------------------------
        */

        if ($expectedToken === '') {
            app(AuditLogger::class)->system(
                event: 'api_configuration_error',
                properties: [
                    'reason' => 'EXTERNAL_API_TOKEN no configurado.',
                ],
            );

            return response()->json([
                'message' => 'API no disponible.',
            ], 503);
        }

        /*
        |--------------------------------------------------------------------------
        | Token recibido
        |--------------------------------------------------------------------------
        */

        $providedToken = $request->bearerToken();

        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        |
        | Nunca registramos el token en logs.
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
                    'reason' => 'invalid_bearer_token',
                ],
            );

            return response()->json([
                'message' => 'No autorizado.',
            ], 401);
        }

        return $next($request);
    }
}