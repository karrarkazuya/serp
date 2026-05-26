<?php

namespace App\Models\Workflow;

use App\Models\Employees\Department;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Manager extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'workflow_managers';

    public array $sortable = [
        'workflow_user' => 'workflow_user_id',
        'active'        => 'active',
        'created_at'    => 'created_at',
    ];

    public array $searchable = [
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    // Rule 4: uuid / created_by / updated_by are observer-managed, never in $fillable.
    protected $fillable = ['workflow_user_id', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function workflowUser(): BelongsTo
    {
        return $this->belongsTo(WorkflowUser::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'workflow_manager_department', 'workflow_manager_id', 'workflow_department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
