<?php

namespace App\Models\Employees;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_attendances';

    public array $chatterTracked = [
        'employee_id'          => 'Employee',
        'company_id'           => 'Company',
        'resource_calendar_id' => 'Working Schedule',
        'attendance_date'      => 'Date',
        'check_in'             => 'Check In',
        'check_out'            => 'Check Out',
        'expected_check_in'    => 'Expected Check In',
        'expected_check_out'   => 'Expected Check Out',
        'expected_hours'       => 'Expected Hours',
        'worked_hours'         => 'Worked Hours',
        'overtime_hours'       => 'Overtime Hours',
        'shortage_hours'       => 'Shortage Hours',
        'is_day_off'           => 'Day Off',
        'is_absence'           => 'Absence',
        'notes'                => 'Notes',
    ];

    public array $sortable = [
        'date'      => 'attendance_date',
        'employee'  => 'employee_id',
        'check_in'  => 'check_in',
        'check_out' => 'check_out',
        'worked'    => 'worked_hours',
        'overtime'  => 'overtime_hours',
        'shortage'  => 'shortage_hours',
    ];

    public array $searchable = [
        'attendance_date'    => ['label' => 'Date',                'column' => 'attendance_date',    'type' => 'date'],
        'check_in'           => ['label' => 'Check In',            'column' => 'check_in',           'type' => 'datetime'],
        'check_out'          => ['label' => 'Check Out',           'column' => 'check_out',          'type' => 'datetime'],
        'expected_check_in'  => ['label' => 'Expected Check In',   'column' => 'expected_check_in',  'type' => 'datetime'],
        'expected_check_out' => ['label' => 'Expected Check Out',  'column' => 'expected_check_out', 'type' => 'datetime'],
        'expected_hours'     => ['label' => 'Expected Hours',      'column' => 'expected_hours',     'type' => 'number'],
        'worked_hours'       => ['label' => 'Worked Hours',        'column' => 'worked_hours',       'type' => 'number'],
        'overtime_hours'     => ['label' => 'Overtime',            'column' => 'overtime_hours',     'type' => 'number'],
        'shortage_hours'     => ['label' => 'Shortage',            'column' => 'shortage_hours',     'type' => 'number'],
        'is_absence'         => ['label' => 'Absence',             'column' => 'is_absence',         'type' => 'boolean'],
        'is_day_off'         => ['label' => 'Day Off',             'column' => 'is_day_off',         'type' => 'boolean'],
        'notes'              => ['label' => 'Notes',               'column' => 'notes',              'type' => 'string'],
        'employee_id'        => [
            'label'    => 'Employee',
            'column'   => 'employee_id',
            'type'     => 'relation',
            'relation' => ['table' => 'hr_employees', 'field' => 'name'],
        ],
        'company_id' => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'resource_calendar_id' => [
            'label'    => 'Working Schedule',
            'column'   => 'resource_calendar_id',
            'type'     => 'relation',
            'relation' => ['table' => 'hr_resource_calendars', 'field' => 'name'],
        ],
    ];

    protected $fillable = [
        'uuid',
        'employee_id', 'company_id', 'resource_calendar_id',
        'attendance_date',
        'check_in', 'check_out',
        'expected_check_in', 'expected_check_out',
        'expected_hours', 'worked_hours', 'overtime_hours', 'shortage_hours',
        'approved_overtime_hours', 'request_id',
        'is_day_off', 'is_absence',
        'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'attendance_date'    => 'date',
        'check_in'           => 'datetime',
        'check_out'          => 'datetime',
        'expected_check_in'  => 'datetime',
        'expected_check_out' => 'datetime',
        'expected_hours'         => 'decimal:2',
        'worked_hours'           => 'decimal:2',
        'overtime_hours'         => 'decimal:2',
        'shortage_hours'         => 'decimal:2',
        'approved_overtime_hours' => 'decimal:2',
        'is_day_off'         => 'boolean',
        'is_absence'         => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function resourceCalendar(): BelongsTo
    {
        return $this->belongsTo(ResourceCalendar::class, 'resource_calendar_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(EmployeeRequest::class, 'request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        if (empty($companyIds)) return $query;
        return $query->whereIn('company_id', $companyIds);
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->is_day_off) return 'Day Off';
        if ($this->is_absence) return 'Absence';
        return 'Present';
    }

    public function getStatusColorAttribute(): string
    {
        if ($this->is_day_off) return 'gray';
        if ($this->is_absence) return 'red';
        return 'green';
    }
}
