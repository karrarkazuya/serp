<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class InventoryAdjustmentPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool                    { return $user->hasPermission('inventory.read'); }
    public function view(User $user, InventoryAdjustment $a): bool    { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($a); }
    public function create(User $user): bool                     { return $user->hasPermission('inventory.create'); }
    public function update(User $user, InventoryAdjustment $a): bool  { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($a); }
    public function delete(User $user, InventoryAdjustment $a): bool  { return $user->hasPermission('inventory.unlink') && $this->withinActiveCompany($a); }
    public function comment(User $user, InventoryAdjustment $a): bool { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($a); }
}
