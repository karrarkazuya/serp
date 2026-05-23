<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Picking;
use App\Models\User;

class PickingPolicy
{
    public function viewAny(User $user): bool      { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Picking $p): bool   { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool       { return $user->hasPermission('inventory.create'); }
    public function update(User $user, Picking $p): bool { return $user->hasPermission('inventory.write'); }
    public function delete(User $user, Picking $p): bool { return $user->hasPermission('inventory.unlink'); }
    public function comment(User $user, Picking $p): bool { return $user->hasPermission('inventory.write'); }
    public function validate(User $user, Picking $p): bool { return $user->hasPermission('inventory.write'); }
}
