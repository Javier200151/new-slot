<?php

namespace App\Policies;

class ChangelogEntryPolicy extends CrudPolicy
{
    protected string $resource = 'changelog';
}
