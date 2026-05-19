<?php

namespace App\Models\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRecordInput extends Model
{
    protected $table = 'workflow_record_inputs';

    protected $fillable = [
        'uuid', 'record_id', 'record_type', 'template_input_id', 'name', 'type',
        'value_char', 'value_int', 'value_date', 'value_datetime', 'value_boolean',
        'value_select_id', 'is_required', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'value_boolean'  => 'boolean',
        'value_date'     => 'date',
        'value_datetime' => 'datetime',
        'is_required'    => 'boolean',
        'active'         => 'boolean',
    ];

    public function templateInput(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplateInput::class, 'template_input_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplateInputOption::class, 'value_select_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getResultValue(): string
    {
        return match ($this->type) {
            'char'     => (string) ($this->value_char ?? ''),
            'int'      => (string) ($this->value_int ?? ''),
            'date'     => $this->value_date?->format('Y-m-d') ?? '',
            'datetime' => $this->value_datetime?->format('Y-m-d H:i') ?? '',
            'boolean'  => $this->value_boolean ? 'Yes' : 'No',
            'select'   => $this->selectedOption?->name ?? '',
            'label'    => '',
            default    => '',
        };
    }

    public function isFilled(): bool
    {
        if ($this->type === 'label') return true;
        if ($this->type === 'boolean') return true;
        if ($this->type === 'select') return $this->value_select_id !== null;
        return match ($this->type) {
            'int'      => $this->value_int !== null,
            'date'     => $this->value_date !== null,
            'datetime' => $this->value_datetime !== null,
            default    => !empty($this->value_char),
        };
    }
}
