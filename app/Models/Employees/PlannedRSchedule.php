<?php

namespace App\Models\Employees;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlannedRSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'hr_planned_rschedules';

    protected $fillable = [
        'uuid', 'employee_id', 'resource_calendar_id', 'sequence',
        'created_by', 'updated_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function resourceCalendar(): BelongsTo
    {
        return $this->belongsTo(ResourceCalendar::class, 'resource_calendar_id');
    }
}
