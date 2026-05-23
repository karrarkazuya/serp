<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\Lot;
use App\Models\User;

class LotPolicy
{
    public function viewAny(User $user): bool      { return $user->hasPermission('inventory.read'); }
    public function view(User $user, Lot $lot): bool    { return $user->hasPermission('inventory.read'); }
    public function create(User $user): bool       { return $user->hasPermission('inventory.create'); }
    public function update(User $user, Lot $lot): bool  { return $user->hasPermission('inventory.write'); }
    public function delete(User $user, Lot $lot): bool  { return $user->hasPermission('inventory.unlink'); }
    public function comment(User $user, Lot $lot): bool { return $user->hasPermission('inventory.write'); }
}
