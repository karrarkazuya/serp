<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Warehouse;
use App\Models\User;

class WarehousePolicy
{
    public function viewAny(User $user): bool         { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Warehouse $w): bool   { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool          { return $user->hasPermission('inventory.config'); }
    public function update(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.config'); }
    public function delete(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.config'); }
    public function comment(User $user, Warehouse $w): bool { return $user->hasPermission('inventory.write'); }
}
