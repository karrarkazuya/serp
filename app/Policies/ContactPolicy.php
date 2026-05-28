<?php

namespace App\Policies;

use App\Models\Contacts\Contact;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class ContactPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('contacts.read');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.read')
            && $this->withinActiveCompany($contact);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('contacts.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.write')
            && $this->withinActiveCompany($contact);
    }

    public function delete(User $user, ?Contact $contact = null): bool
    {
        if ($contact === null) {
            return $user->hasPermission('contacts.unlink');
        }
        return $user->hasPermission('contacts.unlink')
            && $this->withinActiveCompany($contact);
    }

    public function comment(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.write')
            && $this->withinActiveCompany($contact);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('contacts.export');
    }
}
