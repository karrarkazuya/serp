<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\ReorderRule;
use App\Models\User;

class ReorderRulePolicy
{
    public function viewAny(User $user): bool            { return $user->hasPermission('inventory.read'); }
    public function view(User $user, ReorderRule $r): bool    { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool             { return $user->hasPermission('inventory.create'); }
    public function update(User $user, ReorderRule $r): bool  { return $user->hasPermission('inventory.write'); }
    public function delete(User $user, ReorderRule $r): bool  { return $user->hasPermission('inventory.unlink'); }
}
