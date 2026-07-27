<?php

namespace App\Policies;

class EventCommentPolicy extends CrudPolicy
{
    protected string $resource = 'event-comments';
}
