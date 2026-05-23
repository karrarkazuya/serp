<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDependent extends Model
{
    use SoftDeletes;

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
