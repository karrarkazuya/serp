<?php

namespace App\Models\Employees;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlannedDay extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_planned_days';

    public array $chatterTracked = [
        'employee_id'          => 'Employee',
        'planned_date'         => 'Date',
        'resource_calendar_id' => 'Working Schedule',
    ];

    protected $fillable = [
        'uuid', 'employee_id', 'resource_calendar_id', 'planned_date',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'planned_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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
}
