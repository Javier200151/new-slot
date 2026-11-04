<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;

/**
 * Solo se generan novedades para eventos ACTIVO futuros o cuya fecha
 * no supere las 24 h. Al pasar de ACTIVO a FINALIZADO, las novedades
 * existentes permanecen visibles como máximo otras 48 h.
 *
 * El campo event_news_finalized_at se registra al producirse el cambio
 * de estado, no se recalcula con futuras ediciones del evento.
 */
final class EventNewsEligibility
{
    public static function query(Builder $query): Builder
    {
        $activeCutoff = now()->subDay();
        $finishedCutoff = now()->subHours(48);

        return $query->where(function (Builder $eligible) use ($activeCutoff, $finishedCutoff): void {
            $eligible->where(function (Builder $active) use ($activeCutoff): void {
                $active->where('date', '>=', $activeCutoff)
                    ->whereHas('eventStatus', fn (Builder $status) => $status->where('name', 'ACTIVO'));
            })->orWhere(function (Builder $finished) use ($finishedCutoff): void {
                $finished->where('event_news_finalized_at', '>', $finishedCutoff)
                    ->whereHas('eventStatus', fn (Builder $status) => $status->where('name', 'FINALIZADO'));
            });
        });
    }

    public static function allows(Event $event): bool
    {
        $status = mb_strtoupper(trim((string) $event->eventStatus?->name));

        if ($status === 'ACTIVO') {
            return $event->date !== null
                && $event->date->greaterThanOrEqualTo(now()->subDay());
        }

        return $status === 'FINALIZADO'
            && $event->event_news_finalized_at !== null
            && $event->event_news_finalized_at->greaterThan(now()->subHours(48));
    }
}
