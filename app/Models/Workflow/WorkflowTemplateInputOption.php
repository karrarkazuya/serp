<?php

namespace App\Models\Workflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowTemplateInputOption extends Model
{
    use SoftDeletes;

    protected $table = 'workflow_template_input_options';

    protected $fillable = ['template_input_id', 'name'];

    public function templateInput(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplateInput::class, 'template_input_id');
    }
}
