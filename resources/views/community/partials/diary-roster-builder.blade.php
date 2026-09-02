@php
    $builderId = $id ?? ('diary-roster-' . uniqid());
    $eventSelectId = $eventSelectId ?? null;
    $eventId = $eventId ?? null;
    $initialEventId = $initialEventId ?? $eventId;
    $rosterValue = $roster ?? [];
    $squadUrlTemplate = str_replace('/0/escuadra', '/__EVENT__/escuadra', route('community.diary.event-squad', 0));
@endphp

<section
    id="{{ $builderId }}"
    class="diary-roster-builder"
    data-diary-roster-builder
    data-event-select="{{ $eventSelectId }}"
    data-event-id="{{ $eventId }}"
    data-initial-event-id="{{ $initialEventId }}"
    data-squad-url-template="{{ $squadUrlTemplate }}"
    data-initial-roster="{{ e(json_encode($rosterValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
>
    <input type="hidden" name="squad_roster" value="{{ e(json_encode($rosterValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}" data-diary-roster-json>

    <div class="diary-roster-builder__head">
        <div>
            <span class="community-kicker">NUMERACIÓN DE ESCUADRA</span>
            <h3>Cómo os numerasteis en la partida</h3>
            <p>
                Se cargan los jugadores que compartieron tu grupo ORBAT. Ordénalos, asigna un número y un color de equipo.
                Los números pueden repetirse, por ejemplo 1–4 rojo y 1–4 azul.
            </p>
        </div>
        <span class="diary-roster-builder__group" data-diary-roster-group>Selecciona primero un evento</span>
    </div>

    <div class="diary-roster-builder__loading" data-diary-roster-loading hidden>Cargando escuadra…</div>
    <div class="diary-roster-builder__empty" data-diary-roster-empty>
        Selecciona un evento para cargar automáticamente tu escuadra.
    </div>
    <div class="diary-roster-builder__list" data-diary-roster-list></div>

    <template data-diary-roster-template>
        <article class="diary-roster-edit-row" draggable="true" data-roster-row>
            <div class="diary-roster-edit-row__move">
                <button type="button" data-roster-up title="Subir">↑</button>
                <span class="diary-roster-edit-row__drag" title="Arrastrar para ordenar">⋮⋮</span>
                <button type="button" data-roster-down title="Bajar">↓</button>
            </div>
            <img data-roster-avatar alt="" loading="lazy">
            <div class="diary-roster-edit-row__identity">
                <strong data-roster-nick></strong>
                <small data-roster-slot></small>
            </div>
            <label>
                <span>Nº</span>
                <input data-roster-number type="number" min="1" max="99" inputmode="numeric" placeholder="—">
            </label>
            <label>
                <span>Equipo</span>
                <select data-roster-color>
                    <option value="">Sin color</option>
                    <option value="red">Rojo</option>
                    <option value="blue">Azul</option>
                    <option value="green">Verde</option>
                    <option value="yellow">Amarillo</option>
                    <option value="white">Blanco</option>
                    <option value="orange">Naranja</option>
                    <option value="purple">Morado</option>
                    <option value="pink">Rosa</option>
                    <option value="cyan">Cian</option>
                </select>
            </label>
        </article>
    </template>
</section>
