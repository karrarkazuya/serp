<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Route;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class RoutePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool              { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Route $r): bool       { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($r); }
    public function create(User $user): bool               { return $user->hasPermission('inventory.config'); }
    public function update(User $user, Route $r): bool     { return $user->hasPermission('inventory.config') && $this->withinActiveCompany($r); }
    public function delete(User $user, Route $r): bool     { return $user->hasPermission('inventory.config') && $this->withinActiveCompany($r); }
}
