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

class Job extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_jobs';

    public array $chatterTracked = [
        'name'          => 'Name',
        'state'         => 'State',
        'department_id' => 'Department',
        'company_id'    => 'Company',
        'active'        => 'Active',
    ];

    public array $sortable = [
        'name'       => 'name',
        'state'      => 'state',
        'department' => 'department_id',
    ];

    public array $searchable = [
        'name'        => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'state'       => ['label' => 'State', 'column' => 'state', 'type' => 'string'],
        'active'      => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'company_id'  => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'created_at'  => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'name', 'description', 'requirements',
        'expected_employees', 'no_of_recruitment', 'state',
        'active', 'company_id', 'department_id',
        'created_by', 'updated_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'job_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'job_id');
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
        if (empty($companyIds)) return $query;
        return $query->whereIn('company_id', $companyIds);
    }

    public function getNoOfEmployeeAttribute(): int
    {
        return $this->employees()->count();
    }
}
