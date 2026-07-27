<?php

namespace App\Policies;

class OperationStatusPolicy extends CrudPolicy
{
    protected string $resource = 'operation-statuses';
}
