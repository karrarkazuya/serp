<?php

namespace App\Models\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplateInput extends Model
{
    protected $table = 'workflow_template_inputs';

    public const TYPES = ['char', 'int', 'float', 'date', 'datetime', 'boolean', 'select', 'multiselect', 'textarea', 'file', 'label'];

    protected $fillable = [
        'uuid', 'owner_id', 'owner_type', 'name', 'type', 'is_required', 'sort_order',
        'active', 'created_by', 'updated_by',
    ];

    protected $casts = ['is_required' => 'boolean', 'active' => 'boolean'];

    public function options(): HasMany
    {
        return $this->hasMany(WorkflowTemplateInputOption::class, 'template_input_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
