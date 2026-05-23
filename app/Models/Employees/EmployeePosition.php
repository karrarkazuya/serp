<?php

namespace App\Models\Employees;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeePosition extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_employee_positions';

    protected $fillable = [
        'uuid', 'organizational_structure', 'assignment_type',
        'data_status', 'financial_specialization', 'affective_date',
        'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'affective_date'           => 'date',
        'financial_specialization' => 'decimal:2',
        'active'                   => 'boolean',
    ];

    public array $chatterTracked = [
        'organizational_structure' => 'Organizational Structure',
        'assignment_type'          => 'Assignment Type',
        'data_status'              => 'Data Status',
        'financial_specialization' => 'Financial Specialization',
        'affective_date'           => 'Affective Date',
        'active'                   => 'Active',
    ];

    public array $searchable = [
        'organizational_structure' => ['label' => 'Organizational Structure', 'column' => 'organizational_structure', 'type' => 'string'],
        'assignment_type'          => ['label' => 'Assignment Type',          'column' => 'assignment_type',          'type' => 'string'],
        'data_status'              => ['label' => 'Data Status',              'column' => 'data_status',              'type' => 'string'],
        'affective_date'           => ['label' => 'Affective Date',           'column' => 'affective_date',           'type' => 'date'],
    ];

    public array $sortable = [
        'organizational_structure' => 'organizational_structure',
        'assignment_type'          => 'assignment_type',
        'affective_date'           => 'affective_date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'hr_position_employees', 'position_id', 'employee_id');
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
