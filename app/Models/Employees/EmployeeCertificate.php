<?php

namespace App\Models\Employees;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCertificate extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_employee_certificates';

    protected $fillable = [
        'uuid', 'employee_id', 'certificate_type', 'study_type',
        'issuing_institution', 'country', 'data_status', 'graduate_date',
        'affective_date', 'financial_specialization', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'graduate_date'            => 'date',
        'affective_date'           => 'date',
        'financial_specialization' => 'decimal:2',
        'active'                   => 'boolean',
    ];

    public array $chatterTracked = [
        'certificate_type'         => 'Certificate Type',
        'study_type'               => 'Study Type',
        'issuing_institution'      => 'Issuing Institution',
        'country'                  => 'Country',
        'data_status'              => 'Data Status',
        'graduate_date'            => 'Graduate Date',
        'affective_date'           => 'Affective Date',
        'financial_specialization' => 'Financial Specialization',
        'active'                   => 'Active',
    ];

    public array $searchable = [
        'certificate_type'    => ['label' => 'Certificate Type',    'column' => 'certificate_type',    'type' => 'string'],
        'study_type'          => ['label' => 'Study Type',          'column' => 'study_type',          'type' => 'string'],
        'issuing_institution' => ['label' => 'Issuing Institution', 'column' => 'issuing_institution', 'type' => 'string'],
        'country'             => ['label' => 'Country',             'column' => 'country',             'type' => 'string'],
        'data_status'         => ['label' => 'Data Status',         'column' => 'data_status',         'type' => 'string'],
        'graduate_date'       => ['label' => 'Graduate Date',       'column' => 'graduate_date',       'type' => 'date'],
        'affective_date'      => ['label' => 'Affective Date',      'column' => 'affective_date',      'type' => 'date'],
    ];

    public array $sortable = [
        'certificate_type' => 'certificate_type',
        'employee'         => 'employee_id',
        'graduate_date'    => 'graduate_date',
        'affective_date'   => 'affective_date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
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
