<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool                      { return $user->hasPermission('inventory.read'); }
    public function view(User $user, ProductCategory $c): bool     { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool                       { return $user->hasPermission('inventory.config'); }
    public function update(User $user, ProductCategory $c): bool   { return $user->hasPermission('inventory.config'); }
    public function delete(User $user, ProductCategory $c): bool   { return $user->hasPermission('inventory.config'); }
}
