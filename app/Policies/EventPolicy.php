<?php

namespace App\Policies;

class EventPolicy extends CrudPolicy
{
    protected string $resource = 'events';
}
