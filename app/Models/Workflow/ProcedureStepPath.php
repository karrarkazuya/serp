<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureStepPath extends Model
{
    protected $table = 'workflow_procedure_step_paths';

    protected $fillable = ['step_id', 'target_step_id', 'name'];

    public function step(): BelongsTo
    {
        return $this->belongsTo(ProcedureStep::class, 'step_id');
    }

    public function targetStep(): BelongsTo
    {
        return $this->belongsTo(ProcedureStep::class, 'target_step_id');
    }
}
