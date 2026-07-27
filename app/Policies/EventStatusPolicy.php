<?php

namespace App\Policies;

class EventStatusPolicy extends CrudPolicy
{
    protected string $resource = 'event-statuses';
}
