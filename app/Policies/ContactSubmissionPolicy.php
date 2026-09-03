<?php

namespace App\Policies;

class ContactSubmissionPolicy extends CrudPolicy
{
    protected string $resource = 'contact-submissions';

    public function create(\App\Models\User $user): bool
    {
        return false;
    }
}
