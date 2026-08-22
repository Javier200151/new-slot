<?php

namespace App\Http\Middleware;

use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditRequestContext
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $context =
            app(AuditContext::class);

        $correlationId =
            $context->start();

        $request->attributes->set(
            'audit_correlation_id',
            $correlationId
        );

        /*
         * También lo añadimos a laravel.log.
         */
        Log::withContext([
            'correlation_id' => $correlationId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        $response =
            $next($request);

        /*
         * Muy útil para localizar errores desde
         * las herramientas del navegador.
         */
        $response->headers->set(
            'X-Correlation-ID',
            $correlationId
        );

        return $response;
    }
}