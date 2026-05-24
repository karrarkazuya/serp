<?php

namespace App\Models\Employees;

use App\Models\File;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeReward extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_employee_rewards';

    protected $fillable = [
        'uuid',
        'name', 'document_type', 'issued_by', 'document_number',
        'file_path', 'issue_date', 'expiry_date', 'notify_before_days', 'notes',
        'organizational_structure', 'assignment_type', 'data_status',
        'financial_specialization', 'specialization_type', 'employee_seniority', 'affective_date',
        'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date'               => 'date',
        'expiry_date'              => 'date',
        'affective_date'           => 'date',
        'financial_specialization' => 'decimal:2',
        'employee_seniority'       => 'integer',
        'active'                   => 'boolean',
    ];

    public array $chatterTracked = [
        'name'                     => 'Name',
        'document_type'            => 'Document Type',
        'issued_by'                => 'Issued By',
        'document_number'          => 'Document Number',
        'organizational_structure' => 'Organizational Structure',
        'assignment_type'          => 'Assignment Type',
        'data_status'              => 'Data Status',
        'specialization_type'      => 'Specialization Type',
        'financial_specialization' => 'Financial Specialization',
        'employee_seniority'       => 'Employee Seniority',
        'affective_date'           => 'Affective Date',
        'issue_date'               => 'Issue Date',
        'expiry_date'              => 'Expiry Date',
        'active'                   => 'Active',
    ];

    public array $searchable = [
        'name'            => ['label' => 'Name',            'column' => 'name',            'type' => 'string'],
        'document_type'   => ['label' => 'Document Type',   'column' => 'document_type',   'type' => 'string'],
        'document_number' => ['label' => 'Document Number', 'column' => 'document_number', 'type' => 'string'],
        'data_status'          => ['label' => 'Data Status',          'column' => 'data_status',          'type' => 'string', 'options' => [['value' => 'current', 'label' => 'Current'], ['value' => 'previous', 'label' => 'Previous']]],
        'assignment_type'      => ['label' => 'Assignment Type',      'column' => 'assignment_type',      'type' => 'string'],
        'specialization_type'  => ['label' => 'Specialization Type',  'column' => 'specialization_type',  'type' => 'string', 'options' => [['value' => 'amount', 'label' => 'Amount'], ['value' => 'percentage', 'label' => 'Percentage'], ['value' => 'seniority', 'label' => 'Seniority']]],
        'issue_date'      => ['label' => 'Issue Date',      'column' => 'issue_date',      'type' => 'date'],
        'expiry_date'     => ['label' => 'Expiry Date',     'column' => 'expiry_date',     'type' => 'date'],
        'affective_date'  => ['label' => 'Affective Date',  'column' => 'affective_date',  'type' => 'date'],
    ];

    public array $sortable = [
        'name'           => 'name',
        'affective_date' => 'affective_date',
        'issue_date'     => 'issue_date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'hr_reward_employees', 'reward_id', 'employee_id');
    }

    public function attachedFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_path', 'uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
