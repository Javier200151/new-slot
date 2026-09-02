<?php

namespace App\Policies;

use App\Models\CommunityProcess;
use App\Models\User;

class CommunityProcessPolicy
{
    public function viewAny(User $user): bool { return $user->hasRole('admin'); }
    public function view(User $user, CommunityProcess $process): bool { return $user->hasRole('admin'); }
    public function create(User $user): bool { return $user->hasRole('admin'); }
    public function update(User $user, CommunityProcess $process): bool { return $user->hasRole('admin'); }
    public function delete(User $user, CommunityProcess $process): bool { return $user->hasRole('admin'); }
    public function deleteAny(User $user): bool { return $user->hasRole('admin'); }
    public function restore(User $user, CommunityProcess $process): bool { return $user->hasRole('admin'); }
    public function forceDelete(User $user, CommunityProcess $process): bool { return $user->hasRole('admin'); }
}
