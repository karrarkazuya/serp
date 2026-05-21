<?php

namespace App\Models\Workflow;

use App\Models\Employees\Department;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketTemplate extends Model
{
    use HasChatter;

    protected $table = 'workflow_ticket_templates';

    public array $sortable = [
        'name'       => 'name',
        'group'      => 'default_group_id',
        'department' => 'default_department_id',
        'sla'        => 'resolve_max_duration',
        'enabled'    => 'enabled',
        'active'     => 'active',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'        => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'description' => ['label' => 'Description', 'column' => 'description', 'type' => 'text'],
        'default_group_id' => [
            'label' => 'Default Group',
            'column' => 'default_group_id',
            'type' => 'relation',
            'relation' => ['table' => 'workflow_groups', 'field' => 'name'],
        ],
        'default_department_id' => [
            'label' => 'Default Department',
            'column' => 'default_department_id',
            'type' => 'relation',
            'relation' => ['table' => 'hr_departments', 'field' => 'name'],
        ],
        'enabled'    => ['label' => 'Enabled', 'column' => 'enabled', 'type' => 'boolean'],
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'name', 'description', 'default_group_id', 'default_department_id',
        'resolve_max_duration', 'enabled', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = ['enabled' => 'boolean', 'active' => 'boolean'];

    public function defaultGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'default_group_id');
    }

    public function defaultDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'workflow_ticket_template_department', 'ticket_template_id', 'workflow_department_id');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(WorkflowTemplateInput::class, 'owner_id')
            ->where('owner_type', 'ticket_template')
            ->orderBy('sort_order');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeVisibleTo(Builder $query, WorkflowUser $wu): Builder
    {
        $groupIds = $wu->getGroupIds();
        $deptId   = $wu->default_department_id;

        $query->where('enabled', true)->where('active', true);

        // Group: must be in the required group (or no group restriction)
        $query->where(function ($q) use ($groupIds) {
            $q->whereNull('default_group_id')
              ->orWhereIn('default_group_id', $groupIds ?: [0]);
        });

        // Department: must be in the allowed list (AND logic; no departments = unrestricted)
        $query->where(function ($q) use ($deptId) {
            $q->whereDoesntHave('departments')
              ->orWhereHas('departments', fn ($d) => $d->where('hr_departments.id', $deptId));
        });

        return $query;
    }
}
