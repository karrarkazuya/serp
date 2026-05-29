<?php

namespace App\Models\Employees;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use SoftDeletes;

    protected $table = 'hr_employee_documents';

    protected $fillable = [
        'uuid', 'name', 'document_type', 'issued_by', 'document_number',
        'organizational_structure', 'file_path',
        'issue_date', 'expiry_date', 'notify_before_days', 'notes',
        'active', 'employee_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'expiry_date' => 'date',
        'active'      => 'boolean',
    ];

    public array $searchable = [
        'name'                    => ['label' => 'Name',                    'column' => 'name',                    'type' => 'string'],
        'document_type'           => ['label' => 'Document Type',           'column' => 'document_type',           'options' => [
            'contract' => 'Contract', 'id_card' => 'ID Card', 'passport' => 'Passport',
            'certificate' => 'Certificate', 'resume' => 'Resume', 'medical' => 'Medical', 'other' => 'Other',
        ]],
        'issued_by'               => ['label' => 'Issued By',               'column' => 'issued_by',               'type' => 'string'],
        'document_number'         => ['label' => 'Document Number',         'column' => 'document_number',         'type' => 'string'],
        'organizational_structure'=> ['label' => 'Organizational Structure','column' => 'organizational_structure','type' => 'string'],
        'issue_date'              => ['label' => 'Issue Date',              'column' => 'issue_date',              'type' => 'date'],
        'expiry_date'             => ['label' => 'Expiry Date',             'column' => 'expiry_date',             'type' => 'date'],
        'active'                  => ['label' => 'Active',                  'column' => 'active',                  'type' => 'boolean'],
    ];

    public array $sortable = [
        'name'        => 'name',
        'type'        => 'document_type',
        'issue_date'  => 'issue_date',
        'expiry_date' => 'expiry_date',
        'employee'    => 'employee_id',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date
            && !$this->expiry_date->isPast()
            && $this->expiry_date->diffInDays(now()) <= $this->notify_before_days;
    }
}
