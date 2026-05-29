<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\PutawayRule;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class PutawayRulePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool                  { return $user->hasPermission('inventory.read'); }
    public function view(User $user, PutawayRule $p): bool     { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($p); }
    public function create(User $user): bool                   { return $user->hasPermission('inventory.config'); }
    public function update(User $user, PutawayRule $p): bool   { return $user->hasPermission('inventory.config') && $this->withinActiveCompany($p); }
    public function delete(User $user, PutawayRule $p): bool   { return $user->hasPermission('inventory.config') && $this->withinActiveCompany($p); }
}
