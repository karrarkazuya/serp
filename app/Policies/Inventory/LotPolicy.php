<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Lot;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class LotPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool      { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Lot $lot): bool    { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($lot); }
    public function create(User $user): bool       { return $user->hasPermission('inventory.create'); }
    public function update(User $user, Lot $lot): bool  { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($lot); }
    public function delete(User $user, Lot $lot): bool  { return $user->hasPermission('inventory.unlink') && $this->withinActiveCompany($lot); }
    public function comment(User $user, Lot $lot): bool { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($lot); }
}
