<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDependent extends Model
{
    protected $table = 'hr_employee_dependents';

    protected $fillable = [
        'employee_id', 'name', 'relationship', 'birthdate', 'gender', 'identification_number', 'notes',
    ];

    protected $casts = ['birthdate' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
