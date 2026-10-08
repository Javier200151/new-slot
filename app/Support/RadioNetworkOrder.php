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
