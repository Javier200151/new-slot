<?php

namespace App\Policies;

use App\Models\EventStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EventStatusPolicy extends CrudPolicy
{
    protected string $resource = 'event-statuses';

    public function update(User $user, Model $record): bool
    {
        return parent::update($user, $record)
            && ! ($record instanceof EventStatus && $record->is_system);
    }

    public function delete(User $user, Model $record): bool
    {
        return parent::delete($user, $record)
            && $record instanceof EventStatus
            && $record->deletionBlockReason() === null;
    }

    public function deleteAny(User $user): bool
    {
        // Evita eliminación masiva de estados utilizados por la lógica de eventos.
        return false;
    }
}
