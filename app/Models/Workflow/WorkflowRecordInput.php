<?php

namespace App\Models\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkflowRecordInput extends Model
{
    protected $table = 'workflow_record_inputs';

    protected $fillable = [
        'uuid', 'record_id', 'record_type', 'template_input_id', 'name', 'type',
        'value_char', 'value_int', 'value_float', 'value_date', 'value_datetime',
        'value_boolean', 'value_select_id', 'value_text',
        'value_file_path', 'value_file_name', 'value_file_mime', 'value_file_size',
        'is_required', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'value_boolean'  => 'boolean',
        'value_float'    => 'float',
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

    public function selectedOptions(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkflowTemplateInputOption::class,
            'workflow_record_input_multiselect',
            'record_input_id',
            'option_id'
        );
    }

    public function getResultValue(): string
    {
        return match ($this->type) {
            'char'        => (string) ($this->value_char ?? ''),
            'int'         => (string) ($this->value_int ?? ''),
            'float'       => $this->value_float !== null ? rtrim(rtrim((string) $this->value_float, '0'), '.') : '',
            'date'        => $this->value_date?->format('Y-m-d') ?? '',
            'datetime'    => $this->value_datetime?->format('Y-m-d H:i') ?? '',
            'boolean'     => $this->value_boolean ? 'Yes' : 'No',
            'select'      => $this->selectedOption?->name ?? '',
            'multiselect' => $this->relationLoaded('selectedOptions')
                                ? $this->selectedOptions->pluck('name')->join(', ')
                                : $this->selectedOptions()->get()->pluck('name')->join(', '),
            'textarea'    => (string) ($this->value_text ?? ''),
            'file'        => (string) ($this->value_file_name ?? ''),
            'label'       => '',
            default       => '',
        };
    }

    public function isFilled(): bool
    {
        return match ($this->type) {
            'label'       => true,
            'boolean'     => true,
            'select'      => $this->value_select_id !== null,
            'multiselect' => $this->relationLoaded('selectedOptions')
                                ? $this->selectedOptions->isNotEmpty()
                                : $this->selectedOptions()->exists(),
            'int'         => $this->value_int !== null,
            'float'       => $this->value_float !== null,
            'date'        => $this->value_date !== null,
            'datetime'    => $this->value_datetime !== null,
            'textarea'    => !empty($this->value_text),
            'file'        => $this->value_file_path !== null,
            default       => !empty($this->value_char),
        };
    }
}
