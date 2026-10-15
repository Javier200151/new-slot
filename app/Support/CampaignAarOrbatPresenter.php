<?php

namespace App\Support;

use App\Models\User;

/**
 * Decora el snapshot solo para mostrarlo; nunca reescribe un AAR publicado.
 * Los AAR antiguos guardaban nombres pero no colores ni ID de usuario.
 */
class CampaignAarOrbatPresenter
{
    public function present(?array $snapshot): array
    {
        $groups = $snapshot['groups'] ?? [];

        if (! is_array($groups) || $groups === []) {
            return [];
        }

        $ids = [];
        $legacyNames = [];

        foreach ($groups as $group) {
            foreach (($group['slots'] ?? []) as $slot) {
                if ($this->isVacant($slot) || ! empty($slot['is_ally']) || $this->safeColor($slot['status_color'] ?? null)) {
                    continue;
                }

                $id = (int) ($slot['user_id'] ?? 0);
                if ($id > 0) {
                    $ids[$id] = $id;
                } elseif (filled($slot['assignee'] ?? null)) {
                    $name = (string) $slot['assignee'];
                    $legacyNames[$name] = $name;
                }
            }
        }

        $byId = $ids === []
            ? collect()
            : User::withTrashed()->with('status')->whereIn('id', array_values($ids))->get()->keyBy('id');

        // Solo aceptamos nicks unívocos: no asignar el color de otra persona.
        $byNick = $legacyNames === []
            ? collect()
            : User::withTrashed()->with('status')->whereIn('nick', array_values($legacyNames))->get()->groupBy('nick');

        foreach ($groups as &$group) {
            // Importante: iterar sobre el array ORIGINAL. Si se usa
            // foreach (($group['slots'] ?? []) as &$slot), PHP modifica
            // una copia temporal y se pierden los colores y las vacantes.
            if (! is_array($group['slots'] ?? null)) {
                continue;
            }

            foreach ($group['slots'] as &$slot) {
                $slot['is_vacant'] = $this->isVacant($slot);

                if ($slot['is_vacant'] || ! empty($slot['is_ally'])) {
                    $slot['display_status_color'] = null;
                    continue;
                }

                $color = $this->safeColor($slot['status_color'] ?? null);
                if ($color === null) {
                    $id = (int) ($slot['user_id'] ?? 0);
                    $user = $id > 0 ? $byId->get($id) : null;

                    if ($user === null && $id === 0) {
                        $matches = $byNick->get((string) ($slot['assignee'] ?? ''), collect());
                        if ($matches->count() === 1) {
                            $user = $matches->first();
                        }
                    }

                    $color = $user ? $this->safeColor($user->getStatusColor()) : null;
                }

                $slot['display_status_color'] = $color;
            }
            unset($slot);
        }
        unset($group);

        return $groups;
    }

    private function isVacant(array $slot): bool
    {
        return ($slot['assignee'] ?? 'VACANTE') === 'VACANTE'
            && empty($slot['user_id'])
            && empty($slot['is_ally']);
    }

    /** CSS safe: los colores de estado de Filament se guardan como hexadecimal. */
    private function safeColor(mixed $color): ?string
    {
        return is_string($color) && preg_match('/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/D', $color)
            ? $color
            : null;
    }
}
