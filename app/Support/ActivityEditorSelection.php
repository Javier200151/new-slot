<?php

namespace App\Support;

use App\Models\Ally;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ActivityEditorSelection
{
    public static function options(): array
    {
        $users = User::query()
            ->orderBy('nick')
            ->get(['id', 'nick'])
            ->map(
                fn (User $user): array => [
                    'value' => 'user:' . $user->id,
                    'label' => $user->nick,
                ]
            );

        $allies = Ally::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(
                fn (Ally $ally): array => [
                    'value' => 'ally:' . $ally->id,
                    'label' => $ally->name,
                ]
            );

        return $users
            ->concat($allies)
            ->sortBy(
                fn (array $option): string =>
                    mb_strtolower($option['label'])
            )
            ->mapWithKeys(
                fn (array $option): array => [
                    $option['value'] => $option['label'],
                ]
            )
            ->all();
    }

    public static function addChoiceToFormData(array $data): array
    {
        $data['editor_choice'] = filled($data['editor_ally_id'] ?? null)
            ? 'ally:' . (int) $data['editor_ally_id']
            : (filled($data['editor_id'] ?? null)
                ? 'user:' . (int) $data['editor_id']
                : null);

        return $data;
    }

    public static function resolveChoice(array $data): array
    {
        $choice = $data['editor_choice'] ?? null;
        unset($data['editor_choice']);

        $data['editor_id'] = null;
        $data['editor_ally_id'] = null;

        if (blank($choice)) {
            return $data;
        }

        [$type, $rawId] = array_pad(
            explode(':', (string) $choice, 2),
            2,
            null
        );

        $id = filter_var($rawId, FILTER_VALIDATE_INT);

        if (! $id || ! in_array($type, ['user', 'ally'], true)) {
            throw ValidationException::withMessages([
                'data.editor_choice' => 'El editor seleccionado no es válido.',
            ]);
        }

        if ($type === 'user') {
            if (! User::query()->whereKey($id)->exists()) {
                throw ValidationException::withMessages([
                    'data.editor_choice' => 'El miembro seleccionado ya no existe.',
                ]);
            }

            $data['editor_id'] = (int) $id;
        } else {
            if (! Ally::query()->whereKey($id)->exists()) {
                throw ValidationException::withMessages([
                    'data.editor_choice' => 'El aliado seleccionado ya no existe.',
                ]);
            }

            $data['editor_ally_id'] = (int) $id;
        }

        return $data;
    }
}
