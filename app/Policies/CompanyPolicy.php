<?php

namespace App\Policies;

use App\Models\Settings\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('companies.read');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.write');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.unlink');
    }
}
