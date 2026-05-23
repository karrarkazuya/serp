<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool  { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Product $p): bool { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool   { return $user->hasPermission('inventory.create'); }
    public function update(User $user, Product $p): bool { return $user->hasPermission('inventory.write'); }
    public function delete(User $user, Product $p): bool { return $user->hasPermission('inventory.unlink'); }
    public function comment(User $user, Product $p): bool { return $user->hasPermission('inventory.write'); }
}
