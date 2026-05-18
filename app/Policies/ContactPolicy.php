<?php

namespace App\Policies;

use App\Models\Contacts\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contacts.read');
    }

    public function view(User $user, Contact $_contact): bool
    {
        return $user->hasPermission('contacts.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contacts.create');
    }

    public function update(User $user, Contact $_contact): bool
    {
        return $user->hasPermission('contacts.write');
    }

    public function delete(User $user, Contact $_contact): bool
    {
        return $user->hasPermission('contacts.unlink');
    }

    public function comment(User $user, Contact $_contact): bool
    {
        return $user->hasPermission('contacts.write');
    }
}
