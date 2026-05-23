<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\PutawayRule;
use App\Models\User;

class PutawayRulePolicy
{
    public function viewAny(User $user): bool                  { return $user->hasPermission('inventory.read'); }
    public function view(User $user, PutawayRule $p): bool     { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool                   { return $user->hasPermission('inventory.config'); }
    public function update(User $user, PutawayRule $p): bool   { return $user->hasPermission('inventory.config'); }
    public function delete(User $user, PutawayRule $p): bool   { return $user->hasPermission('inventory.config'); }
}
