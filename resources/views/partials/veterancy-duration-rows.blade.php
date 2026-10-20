{{-- Display-only conversion; source counters are calculated by VeterancyService. --}}
<div>
    <dt>Tiempo efectivo como ACTIVO</dt>
    <dd>{{ \App\Services\VeterancyService::formatAccumulatedDays((int) ($veterancy['effective_days'] ?? 0)) }}</dd>
</div>
<div>
    <dt>Tiempo acumulado en RESERVA</dt>
    <dd>{{ \App\Services\VeterancyService::formatAccumulatedDays((int) ($veterancy['reserve_days'] ?? 0)) }}</dd>
</div>
