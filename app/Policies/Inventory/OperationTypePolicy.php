<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\OperationType;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class OperationTypePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool                    { return $user->hasPermission('inventory.read'); }
    public function view(User $user, OperationType $o): bool     { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($o); }
    public function create(User $user): bool                     { return $user->hasPermission('inventory.config'); }
    public function update(User $user, OperationType $o): bool   { return $user->hasPermission('inventory.config') && $this->withinActiveCompany($o); }
    public function delete(User $user, OperationType $o): bool   { return $user->hasPermission('inventory.config') && $this->withinActiveCompany($o); }
}
