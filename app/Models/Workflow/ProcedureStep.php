<?php

namespace App\Models\Workflow;

use App\Models\Employees\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureStep extends Model
{
    protected $table = 'workflow_procedure_steps';

    protected $fillable = [
        'uuid', 'procedure_template_id', 'name', 'description',
        'default_department_id', 'resolve_max_duration', 'is_approve_only', 'has_procedures',
        'ignore_state', 'has_path_choice', 'path_choice_question', 'path_choice_required',
        'has_procedures', 'procedures_required',
        'flowchart_x', 'flowchart_y', 'flowchart_position_saved',
        'enabled', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_approve_only'          => 'boolean',
        'has_procedures'           => 'boolean',
        'procedures_required'      => 'boolean',
        'ignore_state'             => 'boolean',
        'has_path_choice'          => 'boolean',
        'path_choice_required'     => 'boolean',
        'flowchart_position_saved' => 'boolean',
        'enabled'                  => 'boolean',
        'active'                   => 'boolean',
    ];

    public function procedureTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcedureTemplate::class, 'procedure_template_id');
    }

    public function defaultDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'default_department_id');
    }

    public function nextSteps(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'workflow_procedure_step_next', 'step_id', 'next_step_id');
    }

    public function previousSteps(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'workflow_procedure_step_next', 'next_step_id', 'step_id');
    }

    public function subProcedures(): BelongsToMany
    {
        return $this->belongsToMany(ProcedureTemplate::class, 'workflow_procedure_step_sub_proc', 'step_id', 'procedure_template_id');
    }

    public function pathChoices(): HasMany
    {
        return $this->hasMany(ProcedureStepPath::class, 'step_id');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(WorkflowTemplateInput::class, 'owner_id')
            ->where('owner_type', 'procedure_step')
            ->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function isStartStep(): bool
    {
        return $this->previousSteps()->doesntExist();
    }
}
