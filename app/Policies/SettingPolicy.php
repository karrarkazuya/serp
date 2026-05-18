<?php

namespace App\Policies;

use App\Models\Settings\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.read');
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $user->hasPermission('settings.write');
    }
}
