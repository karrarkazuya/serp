<?php

namespace App\Models\Employees;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceCalendar extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_resource_calendars';

    public array $chatterTracked = [
        'name'                   => 'Name',
        'timezone'               => 'Timezone',
        'hours_per_day'          => 'Hours per Day',
        'company_hours_per_week' => 'Company Full Time',
        'flexible_hours'         => 'Flexible Hours',
        'company_id'             => 'Company',
        'active'                 => 'Active',
    ];

    public array $sortable = [
        'name' => 'name',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'company_id' => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
    ];

    protected $fillable = [
        'uuid', 'name', 'timezone', 'hours_per_day', 'company_hours_per_week', 'flexible_hours',
        'active', 'company_id', 'created_by', 'updated_by',
    ];

    protected $casts = ['active' => 'boolean', 'flexible_hours' => 'boolean'];

    public static array $dayNames = [
        0 => 'Saturday',
        1 => 'Sunday',
        2 => 'Monday',
        3 => 'Tuesday',
        4 => 'Wednesday',
        5 => 'Thursday',
        6 => 'Friday',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(ResourceCalendarAttendance::class, 'calendar_id')->orderBy('sequence');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'resource_calendar_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        // Fail-closed: empty list = no access. See Account::scopeForCompanies.
        return $query->whereIn('company_id', $companyIds);
    }
}
