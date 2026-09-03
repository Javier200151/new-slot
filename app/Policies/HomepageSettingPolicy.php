<?php
namespace App\Policies;
class HomepageSettingPolicy extends CrudPolicy { protected string $resource = 'homepage-settings'; public function delete(\App\Models\User $user, \Illuminate\Database\Eloquent\Model $record): bool { return false; } public function deleteAny(\App\Models\User $user): bool { return false; } }
