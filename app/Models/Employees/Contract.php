<?php

namespace App\Models\Employees;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    protected $table = 'hr_contracts';

    protected $fillable = [
        'uuid', 'name', 'employee_id', 'department_id', 'job_id', 'company_id',
        'resource_calendar_id', 'date_start', 'date_end', 'trial_date_start', 'trial_date_end',
        'state', 'contract_type', 'wage', 'currency', 'notes', 'image',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_start'       => 'date',
        'date_end'         => 'date',
        'trial_date_start' => 'date',
        'trial_date_end'   => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function resourceCalendar(): BelongsTo
    {
        return $this->belongsTo(ResourceCalendar::class, 'resource_calendar_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('state', 'open');
    }

    public function isOpen(): bool
    {
        return $this->state === 'open';
    }
}
