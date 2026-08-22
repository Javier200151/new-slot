<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;
}