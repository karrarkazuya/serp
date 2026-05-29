<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\ReorderRule;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class ReorderRulePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool            { return $user->hasPermission('inventory.read'); }
    public function view(User $user, ReorderRule $r): bool    { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($r); }
    public function create(User $user): bool             { return $user->hasPermission('inventory.create'); }
    public function update(User $user, ReorderRule $r): bool  { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($r); }
    public function delete(User $user, ReorderRule $r): bool  { return $user->hasPermission('inventory.unlink') && $this->withinActiveCompany($r); }
}
