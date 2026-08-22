<?php

use App\Http\Middleware\AuditRequestContext;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )


    ->withMiddleware(
        function (
            Middleware $middleware
        ): void {

            $middleware->trustProxies(
                at: '*',
                headers:
                    Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PROTO
            );

            $middleware->append(
                AuditRequestContext::class
            );
        }
    )

    ->withExceptions(
        function (
            Exceptions $exceptions
        ): void {

            $exceptions->shouldRenderJsonWhen(
                fn (Request $request): bool =>
                    $request->is('api/*')
            );

            $exceptions->report(
                function (
                    \Throwable $exception
                ): void {
                    try {

                        $status = match (true) {

                            $exception instanceof AuthorizationException
                                => 403,

                            $exception instanceof HttpExceptionInterface
                                => $exception->getStatusCode(),

                            default => 500,
                        };

                        if ($status === 403) {
                            app(AuditLogger::class)
                                ->security(
                                    event:
                                        'authorization_denied',

                                    properties: [
                                        'exception' =>
                                            $exception::class,

                                        'status' =>
                                            403,
                                    ],
                                );

                            return;
                        }

                        if ($status >= 500) {
                            app(AuditLogger::class)
                                ->system(
                                    event:
                                        'application_error',

                                    properties: [
                                        'exception' =>
                                            $exception::class,

                                        'status' =>
                                            $status,

                                        'message' =>
                                            Str::limit(
                                                $exception
                                                    ->getMessage(),
                                                1000
                                            ),

                                        'file' =>
                                            basename(
                                                $exception
                                                    ->getFile()
                                            ),

                                        'line' =>
                                            $exception
                                                ->getLine(),
                                    ],
                                );
                        }

                    } catch (
                        \Throwable $auditException
                    ) {


                        Log::error(
                            'No se pudo registrar la excepción en la auditoría.',
                            [
                                'audit_exception' =>
                                    $auditException
                                        ->getMessage(),

                                'original_exception' =>
                                    $exception::class,
                            ],
                        );
                    }
                }
            );
        }
    )

    ->create();