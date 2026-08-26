<?php

namespace App\Providers;

use App\Listeners\AuditPermissionRelationChange;
use App\Models\GameMap;
use App\Models\Role;
use App\Policies\ActivityPolicy;
use App\Policies\MapPolicy;
use App\Policies\RolePolicy;
use App\Services\AuditLogger;
use App\Support\AuditContext;
use Filament\Forms\Components\RichEditor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Facades\Activity as ActivityFacade;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Un contexto diferente por petición/job.
         */
        $this->app->scoped(
            AuditContext::class,
            fn (): AuditContext =>
                new AuditContext(),
        );
    }

    public function boot(): void
    {
        RichEditor::configureUsing(
            fn (
                RichEditor $editor
            ): RichEditor =>
                $editor->enableToolbarButtons([
                    'textColor',
                ]),
        );

        Gate::policy(
            Role::class,
            RolePolicy::class
        );

        /*
         * Policies que Laravel no puede descubrir por convención:
         *
         * - GameMap usa MapPolicy por compatibilidad con el modelo histórico Map.
         * - Activity pertenece al paquete spatie/laravel-activitylog.
         */
        Gate::policy(
            GameMap::class,
            MapPolicy::class
        );

        Gate::policy(
            Activity::class,
            ActivityPolicy::class
        );

        $this->registerActivityEnrichment();

        $this->registerAuthenticationAudit();

        $this->registerPermissionAudit();
    }

    /*
    |--------------------------------------------------------------------------
    | Metadatos comunes para TODOS los logs
    |--------------------------------------------------------------------------
    */

    private function registerActivityEnrichment(): void
    {
        ActivityFacade::beforeLogging(
            function (
                ActivityContract $activity
            ): void {
                $context =
                    app(AuditContext::class);

                $activity->correlation_id =
                    $context->id();

                $properties =
                    $activity
                        ->properties
                        ?->toArray()
                    ?? [];

                /*
                 |--------------------------------------------------------------------------
                 | Artisan / consola
                 |--------------------------------------------------------------------------
                 */

                if (app()->runningInConsole()) {
                    $activity->source =
                        'console';

                    $activity->request_method =
                        null;

                    $activity->route_name =
                        null;

                    $activity->url =
                        null;

                    $activity->ip_address =
                        null;

                    $activity->user_agent =
                        null;

                    /*
                     * Solo guardamos el nombre del comando.
                     *
                     * NO los argumentos, porque podría haber
                     * --password=xxxxx.
                     */
                    $properties['request'] = [
                        'source' => 'console',

                        'command' =>
                            $_SERVER['argv'][1]
                            ?? null,
                    ];
                }

                /*
                 |--------------------------------------------------------------------------
                 | Petición HTTP
                 |--------------------------------------------------------------------------
                 */

                else {
                    $request = request();

                    $route =
                        $request->route();

                    $routeUri =
                        is_object($route)
                        && method_exists(
                            $route,
                            'uri'
                        )
                            ? $route->uri()
                            : $request->path();

                    $activity->source =
                        $request->is('api/*')
                            ? 'api'
                            : 'web';

                    $activity->request_method =
                        $request->method();

                    $activity->route_name =
                        $route?->getName();

                    /*
                     * NO guardamos query strings.
                     *
                     * Evitamos que tokens u otros datos
                     * sensibles aparezcan en auditoría.
                     */
                    $activity->url =
                        Str::limit(
                            '/'
                            . ltrim(
                                (string) $routeUri,
                                '/'
                            ),
                            255,
                            ''
                        );

                    $activity->ip_address =
                        $request->ip();

                    $activity->user_agent =
                        Str::limit(
                            (string)
                                $request->userAgent(),
                            2000,
                            ''
                        );

                    $properties['request'] = [
                        'method' =>
                            $request->method(),

                        'route_name' =>
                            $route?->getName(),

                        'route_uri' =>
                            $routeUri,
                    ];
                }

                /*
                 |--------------------------------------------------------------------------
                 | Copia histórica del usuario
                 |--------------------------------------------------------------------------
                 */

                $actor =
                    $activity->causer;

                if ($actor instanceof Model) {
                    $activity->actor_nick =
                        $this->auditLabel(
                            $actor
                        );

                    $properties[
                        'actor_snapshot'
                    ] = [
                        'type' =>
                            $actor::class,

                        'id' =>
                            $actor->getKey(),

                        'label' =>
                            $this->auditLabel(
                                $actor
                            ),
                    ];
                }

                /*
                 |--------------------------------------------------------------------------
                 | Copia histórica del objeto afectado
                 |--------------------------------------------------------------------------
                 */

                $subject =
                    $activity->subject;

                if ($subject instanceof Model) {
                    $activity->subject_table =
                        $subject->getTable();

                    $activity->subject_label =
                        $this->auditLabel(
                            $subject
                        );

                    $properties[
                        'subject_snapshot'
                    ] = [
                        'type' =>
                            $subject::class,

                        'table' =>
                            $subject->getTable(),

                        'key_name' =>
                            $subject->getKeyName(),

                        'id' =>
                            $subject->getKey(),

                        'label' =>
                            $this->auditLabel(
                                $subject
                            ),
                    ];
                }

                $activity->properties =
                    collect($properties);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Seguridad / autenticación
    |--------------------------------------------------------------------------
    */

    private function registerAuthenticationAudit(): void
    {
        Event::listen(
            Login::class,
            function (Login $event): void {
                app(AuditLogger::class)
                    ->security(
                        event:
                            'login_succeeded',

                        subject:
                            $event->user,

                        properties: [
                            'guard' =>
                                $event->guard,

                            'remember' =>
                                $event->remember,
                        ],

                        causer:
                            $event->user,
                    );
            }
        );

        Event::listen(
            Failed::class,
            function (Failed $event): void {
                app(AuditLogger::class)
                    ->security(
                        event:
                            'login_failed',

                        subject:
                            $event->user,

                        properties: [
                            'guard' =>
                                $event->guard,

                            /*
                             * Nunca guardamos password.
                             */
                            'identifier' =>
                                $event->credentials[
                                    'email'
                                ]
                                ?? $event
                                    ->credentials[
                                        'nick'
                                    ]
                                ?? null,
                        ],

                        causer:
                            $event->user,
                    );
            }
        );

        Event::listen(
            Lockout::class,
            function (
                Lockout $event
            ): void {
                app(AuditLogger::class)
                    ->security(
                        event:
                            'login_locked_out',

                        properties: [
                            'identifier' =>
                                $event
                                    ->request
                                    ->input('email')
                                ?? $event
                                    ->request
                                    ->input('nick'),
                        ],
                    );
            }
        );

        Event::listen(
            Logout::class,
            function (
                Logout $event
            ): void {
                app(AuditLogger::class)
                    ->security(
                        event: 'logout',

                        subject:
                            $event->user,

                        properties: [
                            'guard' =>
                                $event->guard,
                        ],

                        causer:
                            $event->user,
                    );
            }
        );

        Event::listen(
            Registered::class,
            function (
                Registered $event
            ): void {
                app(AuditLogger::class)
                    ->security(
                        event:
                            'account_registered',

                        subject:
                            $event->user,

                        causer:
                            $event->user,
                    );
            }
        );

        Event::listen(
            Verified::class,
            function (
                Verified $event
            ): void {
                app(AuditLogger::class)
                    ->security(
                        event:
                            'email_verified',

                        subject:
                            $event->user,

                        causer:
                            $event->user,
                    );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Roles y permisos
    |--------------------------------------------------------------------------
    */

    private function registerPermissionAudit(): void
    {
        foreach (
            [
                RoleAttachedEvent::class,
                RoleDetachedEvent::class,
                PermissionAttachedEvent::class,
                PermissionDetachedEvent::class,
            ]
            as $eventClass
        ) {
            Event::listen(
                $eventClass,
                [
                    AuditPermissionRelationChange::class,
                    'handle',
                ],
            );
        }
    }

    private function auditLabel(
        Model $model
    ): string {
        foreach (
            [
                'nick',
                'name',
                'title',
                'slug',
                'email',
            ]
            as $attribute
        ) {
            $value =
                $model->getAttribute(
                    $attribute
                );

            if (filled($value)) {
                return (string) $value;
            }
        }

        return '#'
            . (string) $model->getKey();
    }
}