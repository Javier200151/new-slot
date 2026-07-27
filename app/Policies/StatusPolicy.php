<?php

namespace App\Policies;

class StatusPolicy extends CrudPolicy
{
    protected string $resource = 'statuses';
}