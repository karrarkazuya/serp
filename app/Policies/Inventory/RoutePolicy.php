<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Route;
use App\Models\User;

class RoutePolicy
{
    public function viewAny(User $user): bool              { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Route $r): bool       { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool               { return $user->hasPermission('inventory.config'); }
    public function update(User $user, Route $r): bool     { return $user->hasPermission('inventory.config'); }
    public function delete(User $user, Route $r): bool     { return $user->hasPermission('inventory.config'); }
}
