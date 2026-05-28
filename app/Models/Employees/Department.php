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

class Department extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_departments';

    public array $chatterTracked = [
        'name'       => 'Name',
        'parent_id'  => 'Parent Department',
        'manager_id' => 'Manager',
        'company_id' => 'Company',
        'active'     => 'Active',
    ];

    public array $sortable = [
        'name'    => 'name',
        'company' => 'company_id',
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
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'name', 'note', 'color_index', 'active',
        'company_id', 'parent_id', 'manager_id',
        'created_by', 'updated_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class, 'department_id');
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

    public function getCompleteName(): string
    {
        $parts = [$this->name];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($parts, $parent->name);
            $parent = $parent->parent;
        }
        return implode(' / ', $parts);
    }
}
