<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logAll()

            /*
             * Nunca almacenar estos valores.
             */
            ->logExcept([
                'password',
                'remember_token',
            ])

            /*
             * En UPDATE solo guardar lo que
             * realmente haya cambiado.
             */
            ->logOnlyDirty()

            /*
             * No crear registros vacíos.
             */
            ->dontLogEmptyChanges();
    }

    public function beforeActivityLogged(
        Activity $activity,
        string $eventName,
    ): void {
        /*
         * Información adicional.
         */
        $properties = $activity->properties?->toArray() ?? [];

        /*
         |--------------------------------------------------------------------------
         | Acción desde navegador
         |--------------------------------------------------------------------------
         */

        if (! app()->runningInConsole()) {
            $request = request();

            $activity->ip_address =
                $request->ip();

            $activity->user_agent =
                $request->userAgent();

            $activity->url =
                $request->fullUrl();

            $properties['request'] = [
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
            ];
        }

        /*
         |--------------------------------------------------------------------------
         | Acción desde Artisan
         |--------------------------------------------------------------------------
         */

        else {
            $properties['request'] = [
                'source' => 'console',
                'command' => implode(
                    ' ',
                    $_SERVER['argv'] ?? []
                ),
            ];
        }

        $activity->properties =
            collect($properties);
    }
}