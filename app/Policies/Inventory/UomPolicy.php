<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Uom;
use App\Models\User;

class UomPolicy
{
    public function viewAny(User $user): bool          { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Uom $u): bool     { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool           { return $user->hasPermission('inventory.config'); }
    public function update(User $user, Uom $u): bool   { return $user->hasPermission('inventory.config'); }
    public function delete(User $user, Uom $u): bool   { return $user->hasPermission('inventory.config'); }
}
