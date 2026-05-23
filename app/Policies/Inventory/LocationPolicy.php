<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool         { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Location $l): bool    { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool          { return $user->hasPermission('inventory.config'); }
    public function update(User $user, Location $l): bool  { return $user->hasPermission('inventory.config'); }
    public function delete(User $user, Location $l): bool  { return $user->hasPermission('inventory.config'); }
    public function comment(User $user, Location $l): bool { return $user->hasPermission('inventory.write'); }
}
