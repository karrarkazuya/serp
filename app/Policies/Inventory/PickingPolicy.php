<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Picking;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class PickingPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool      { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Picking $p): bool   { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($p); }
    public function create(User $user): bool       { return $user->hasPermission('inventory.create'); }
    public function update(User $user, Picking $p): bool { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($p); }
    public function delete(User $user, Picking $p): bool { return $user->hasPermission('inventory.unlink') && $this->withinActiveCompany($p); }
    public function comment(User $user, Picking $p): bool { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($p); }
    public function validate(User $user, Picking $p): bool { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($p); }
}
