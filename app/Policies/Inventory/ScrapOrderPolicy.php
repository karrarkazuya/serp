<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\ScrapOrder;
use App\Models\User;

class ScrapOrderPolicy
{
    public function viewAny(User $user): bool         { return $user->hasPermission('inventory.read'); }
    public function view(User $user, ScrapOrder $s): bool  { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool          { return $user->hasPermission('inventory.create'); }
    public function update(User $user, ScrapOrder $s): bool { return $user->hasPermission('inventory.write'); }
    public function delete(User $user, ScrapOrder $s): bool { return $user->hasPermission('inventory.unlink'); }
    public function comment(User $user, ScrapOrder $s): bool { return $user->hasPermission('inventory.write'); }
}
