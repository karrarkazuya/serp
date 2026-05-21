<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBankAccount extends Model
{
    protected $table = 'hr_employee_bank_accounts';

    protected $fillable = [
        'employee_id', 'bank_name', 'account_holder_name', 'account_number',
        'iban', 'swift_code', 'branch_name', 'currency', 'is_primary', 'active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'active'     => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
