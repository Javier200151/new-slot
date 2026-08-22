<?php

namespace App\Services;

use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class AuditLogger
{
    public function change(
        Model $subject,
        string $event,
        array $old = [],
        array $new = [],
        array $properties = [],
    ): void {
        $logger = activity('audit')
            ->event($event)
            ->performedOn($subject)
            ->withProperties($properties);

        $causer = Auth::user();

        if ($causer instanceof Model) {
            $logger->causedBy($causer);
        } else {
            $logger->causedByAnonymous();
        }

        $logger
            ->tap(
                function (
                    ActivityContract $activity
                ) use ($old, $new): void {
                    $changes = [];

                    if ($new !== []) {
                        $changes['attributes'] = $new;
                    }

                    if ($old !== []) {
                        $changes['old'] = $old;
                    }

                    $activity->attribute_changes =
                        collect($changes);
                }
            )
            ->log($event);
    }

    public function security(
        string $event,
        ?Model $subject = null,
        array $properties = [],
        ?Model $causer = null,
    ): void {
        $logger = activity('security')
            ->event($event)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $actor =
            $causer
            ?? (
                Auth::user() instanceof Model
                    ? Auth::user()
                    : null
            );

        if ($actor !== null) {
            $logger->causedBy($actor);
        } else {
            $logger->causedByAnonymous();
        }

        $logger->log($event);

        /*
         * Además de BD, los eventos de seguridad
         * importantes se escriben en fichero.
         */
        Log::channel('security')
            ->warning(
                $event,
                [
                    'correlation_id' =>
                        app(AuditContext::class)->id(),

                    ...$properties,
                ]
            );
    }

    public function system(
        string $event,
        array $properties = [],
        ?Model $subject = null,
    ): void {
        $logger = activity('system')
            ->event($event)
            ->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $causer = Auth::user();

        if ($causer instanceof Model) {
            $logger->causedBy($causer);
        } else {
            $logger->causedByAnonymous();
        }

        $logger->log($event);

        Log::error(
            $event,
            [
                'correlation_id' =>
                    app(AuditContext::class)->id(),

                ...$properties,
            ]
        );
    }
}