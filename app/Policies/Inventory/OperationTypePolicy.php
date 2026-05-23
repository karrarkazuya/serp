<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\OperationType;
use App\Models\User;

class OperationTypePolicy
{
    public function viewAny(User $user): bool                    { return $user->hasPermission('inventory.read'); }
    public function view(User $user, OperationType $o): bool     { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool                     { return $user->hasPermission('inventory.config'); }
    public function update(User $user, OperationType $o): bool   { return $user->hasPermission('inventory.config'); }
    public function delete(User $user, OperationType $o): bool   { return $user->hasPermission('inventory.config'); }
}
