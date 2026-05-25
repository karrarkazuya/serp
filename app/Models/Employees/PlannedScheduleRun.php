<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;

class PlannedScheduleRun extends Model
{
    protected $table = 'hr_planned_schedule_runs';

    protected $fillable = ['run_date', 'ran_at', 'success', 'notes'];

    protected $casts = [
        'run_date' => 'date',
        'ran_at'   => 'datetime',
        'success'  => 'boolean',
    ];
}
