<?php

namespace App\Policies\Inventory;

use App\Models\Inventory\ScrapOrder;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class ScrapOrderPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool         { return $user->hasPermission('inventory.read'); }
    public function view(User $user, ScrapOrder $s): bool  { return $user->hasPermission('inventory.read')   && $this->withinActiveCompany($s); }
    public function create(User $user): bool          { return $user->hasPermission('inventory.create'); }
    public function update(User $user, ScrapOrder $s): bool { return $user->hasPermission('inventory.write')  && $this->withinActiveCompany($s); }
    public function delete(User $user, ScrapOrder $s): bool { return $user->hasPermission('inventory.unlink') && $this->withinActiveCompany($s); }
    public function comment(User $user, ScrapOrder $s): bool { return $user->hasPermission('inventory.write') && $this->withinActiveCompany($s); }
}
