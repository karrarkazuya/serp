<?php

namespace App\Models\Workflow;

use App\Models\Employees\Department;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowUser extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'workflow_users';

    public array $sortable = [
        'user'               => 'user_id',
        'default_department' => 'default_department_id',
        'active'             => 'active',
        'created_at'         => 'created_at',
    ];

    public array $searchable = [
        'user_id' => [
            'label' => 'User',
            'column' => 'user_id',
            'type' => 'relation',
            'relation' => ['table' => 'users', 'field' => 'name'],
        ],
        'default_department_id' => [
            'label' => 'Default Department',
            'column' => 'default_department_id',
            'type' => 'relation',
            'relation' => ['table' => 'hr_departments', 'field' => 'name'],
        ],
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'user_id', 'default_department_id', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'workflow_user_group', 'workflow_user_id', 'workflow_group_id');
    }

    public function assignableDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'workflow_user_dept_assign', 'workflow_user_id', 'workflow_department_id');
    }

    public function manager(): HasOne
    {
        return $this->hasOne(Manager::class, 'workflow_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function getGroupIds(): array
    {
        return $this->groups()->pluck('workflow_groups.id')->toArray();
    }

    public function getAssignableDepartmentIds(): array
    {
        return $this->assignableDepartments()->pluck('hr_departments.id')->toArray();
    }
}
