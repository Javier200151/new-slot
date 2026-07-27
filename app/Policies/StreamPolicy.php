<?php

namespace App\Policies;

class StreamPolicy extends CrudPolicy
{
    protected string $resource = 'streams';
}
