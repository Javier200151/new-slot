<?php

namespace App\Policies;

use App\Models\CommunityClip;
use App\Models\User;

class CommunityClipPolicy
{
    public function viewAny(User $user): bool { return $user->hasRole('admin') || $user->can('community-clips.view'); }
    public function view(User $user, CommunityClip $record): bool { return $this->viewAny($user); }
    public function create(User $user): bool { return $user->hasRole('admin') || $user->can('community-clips.create'); }
    public function update(User $user, CommunityClip $record): bool { return $user->hasRole('admin') || $user->can('community-clips.update'); }
    public function delete(User $user, CommunityClip $record): bool { return $user->hasRole('admin') || $user->can('community-clips.delete'); }
    public function updateAny(User $user): bool { return $user->hasRole('admin') || $user->can('community-clips.update'); }
    public function deleteAny(User $user): bool { return $user->hasRole('admin') || $user->can('community-clips.delete'); }
    public function restore(User $user, CommunityClip $record): bool { return $this->delete($user, $record); }
    public function restoreAny(User $user): bool { return $user->hasRole('admin') || $user->can('community-clips.delete'); }
}
