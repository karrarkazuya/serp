<?php

namespace App\Policies\Employees;

use App\Models\Employees\Attendance;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class AttendancePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.read');
    }

    public function view(User $user, ?Attendance $attendance = null): bool
    {
        // List-level call (null model) just checks the permission. With a
        // bound model, also gate by company so an HR user can't view a
        // cross-tenant attendance record they shouldn't see.
        if (!$user->hasPermission('attendance.read')) return false;
        return $attendance === null || $this->withinActiveCompany($attendance);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.create');
    }

    public function update(User $user, ?Attendance $attendance = null): bool
    {
        if (!$user->hasPermission('attendance.write')) return false;
        return $attendance === null || $this->withinActiveCompany($attendance);
    }

    // No delete ability on purpose — attendance records are immutable history.

    public function comment(User $user, Attendance $attendance): bool
    {
        return $user->hasPermission('attendance.write') && $this->withinActiveCompany($attendance);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('attendance.export');
    }
}
