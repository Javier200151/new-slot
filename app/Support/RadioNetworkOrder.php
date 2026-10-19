<?php

namespace App\Support;

class RadioNetworkOrder
{
    /**
     * Devuelve las redes en el orden persistido. En datos antiguos sin campo
     * order conserva exactamente el orden del array JSON existente.
     */
    public static function ordered(array $networks): array
    {
        $prepared = collect($networks)
            ->filter(fn ($network): bool => is_array($network))
            ->values()
            ->map(function (array $network, int $index): array {
                $network['_original_index'] = $index;
                $network['_effective_order'] = isset($network['order'])
                    ? (int) $network['order']
                    : ($index + 1);

                return $network;
            })
            ->sort(function (array $a, array $b): int {
                $orderComparison = $a['_effective_order'] <=> $b['_effective_order'];

                return $orderComparison !== 0
                    ? $orderComparison
                    : ($a['_original_index'] <=> $b['_original_index']);
            })
            ->values()
            ->map(function (array $network): array {
                unset($network['_original_index'], $network['_effective_order']);

                return $network;
            });

        return $prepared->all();
    }

    /**
     * Ordena las filas del repeater conservando sus claves y su contenido.
     *
     * La acción nativa de Filament usa array_flip() y array unpacking: cuando
     * las claves de los ítems son numéricas, el unpacking puede insertar
     * índices (int) como si fueran filas. Entonces Filament falla al tratar
     * esos enteros como estados de un esquema.
     *
     * Sólo movemos claves que ya existen; los identificadores obsoletos de
     * un DOM anterior se ignoran, y las filas restantes mantienen su orden.
     * No persiste nada: el modal sigue guardando únicamente al confirmarlo.
     *
     * @param array<int|string, mixed> $items
     * @param array<int, mixed> $requestedKeys
     * @return array<int|string, array<string, mixed>>
     */
    public static function reorderRepeaterItems(array $items, array $requestedKeys): array
    {
        $ordered = [];

        foreach ($requestedKeys as $key) {
            if (! is_int($key) && ! is_string($key)) {
                continue;
            }

            if (! array_key_exists($key, $items) || ! is_array($items[$key])) {
                continue;
            }

            $ordered[$key] = $items[$key];
        }

        foreach ($items as $key => $item) {
            if (! is_array($item) || array_key_exists($key, $ordered)) {
                continue;
            }

            $ordered[$key] = $item;
        }

        return $ordered;
    }

    /**
     * Guarda explícitamente el orden visual del repeater dentro de cada red.
     */
    public static function forStorage(array $networks): array
    {
        return collect($networks)
            ->filter(fn ($network): bool => is_array($network))
            ->values()
            ->map(function (array $network, int $index): array {
                $network['order'] = $index + 1;

                return $network;
            })
            ->all();
    }
}
