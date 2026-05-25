<?php

namespace App\Policies\Employees;

use App\Models\Employees\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.read');
    }

    public function view(User $user, ?Attendance $_attendance = null): bool
    {
        return $user->hasPermission('attendance.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.create');
    }

    public function update(User $user, ?Attendance $_attendance = null): bool
    {
        return $user->hasPermission('attendance.write');
    }

    // No delete ability on purpose — attendance records are immutable history.

    public function comment(User $user, Attendance $_attendance): bool
    {
        return $user->hasPermission('attendance.write');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('attendance.export');
    }
}
