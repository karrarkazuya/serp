<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEmergencyContact extends Model
{
    protected $table = 'hr_employee_emergency_contacts';

    protected $fillable = [
        'employee_id', 'name', 'relationship', 'phone', 'email', 'address', 'is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
