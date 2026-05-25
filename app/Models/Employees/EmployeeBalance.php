<?php

namespace App\Models\Employees;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeBalance extends Model
{
    use SoftDeletes;

    protected $table = 'hr_employee_balances';

    protected $fillable = [
        'uuid', 'employee_id',
        'leave_days_balance', 'time_off_hours_balance', 'last_credited_month',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'leave_days_balance'     => 'decimal:2',
        'time_off_hours_balance' => 'decimal:2',
        'last_credited_month'    => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
