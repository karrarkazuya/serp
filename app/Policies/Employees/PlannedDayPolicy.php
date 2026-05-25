<?php

namespace App\Policies\Employees;

use App\Models\Employees\PlannedDay;
use App\Models\User;

class PlannedDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('planned_schedules.read');
    }

    public function view(User $user, ?PlannedDay $_day = null): bool
    {
        return $user->hasPermission('planned_schedules.read');
    }

    public function update(User $user, ?PlannedDay $_day = null): bool
    {
        return $user->hasPermission('planned_schedules.write');
    }

    // No delete ability on purpose — only the midnight cron removes planned
    // days (after they pass and have been recorded to attendance).
}
