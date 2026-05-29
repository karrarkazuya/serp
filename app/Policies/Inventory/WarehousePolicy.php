<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class WarehousePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool         { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Warehouse $w): bool   { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($w); }
    public function create(User $user): bool          { return $user->hasPermission('inventory.config'); }
    public function update(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.config') && $this->withinActiveCompany($w); }
    public function delete(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.config') && $this->withinActiveCompany($w); }
    public function comment(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.write') && $this->withinActiveCompany($w); }
}
