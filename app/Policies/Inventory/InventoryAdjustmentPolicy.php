<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\User;

class InventoryAdjustmentPolicy
{
    public function viewAny(User $user): bool                    { return $user->hasPermission('inventory.read'); }
    public function view(User $user, InventoryAdjustment $a): bool    { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool                     { return $user->hasPermission('inventory.write'); }
    public function update(User $user, InventoryAdjustment $a): bool  { return $user->hasPermission('inventory.write'); }
    public function delete(User $user, InventoryAdjustment $a): bool  { return $user->hasPermission('inventory.unlink'); }
    public function comment(User $user, InventoryAdjustment $a): bool { return $user->hasPermission('inventory.write'); }
}
