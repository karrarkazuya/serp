<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Product;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class ProductPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool  { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Product $p): bool { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($p); }
    public function create(User $user): bool   { return $user->hasPermission('inventory.create'); }
    public function update(User $user, Product $p): bool { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($p); }
    public function delete(User $user, Product $p): bool { return $user->hasPermission('inventory.unlink') && $this->withinActiveCompany($p); }
    public function comment(User $user, Product $p): bool { return $user->hasPermission('inventory.write') && $this->withinActiveCompany($p); }
}
