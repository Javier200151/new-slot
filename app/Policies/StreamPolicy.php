<?php

namespace App\Policies;

use App\Models\Stream;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class StreamPolicy extends CrudPolicy
{
    protected string $resource = 'streams';

    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $user->hasRole('admin') || $user->can('streamer-vods.manage');
    }

    public function view(User $user, Model $record): bool
    {
        return parent::view($user, $record) || ($record instanceof Stream && $record->isVodForHome() && $user->can('streamer-vods.manage'));
    }

    public function create(User $user): bool
    {
        return parent::create($user) || $user->hasRole('admin') || $user->can('streamer-vods.manage');
    }

    public function update(User $user, Model $record): bool
    {
        if ($record instanceof Stream && $record->isVodForHome()) {
            return $user->hasRole('admin') || $user->can('streamer-vods.manage');
        }
        return parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        if ($record instanceof Stream && $record->isVodForHome()) {
            return $user->hasRole('admin') || $user->can('streamer-vods.manage');
        }
        return parent::delete($user, $record);
    }
}
