<?php

namespace App\Models\Employees;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestSubtype extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_request_subtypes';

    public const TYPE_LEAVE    = 'leave';
    public const TYPE_TIME_OFF = 'time_off';
    public const TYPE_OVERTIME = 'overtime';

    public const TYPE_LABELS = [
        self::TYPE_LEAVE    => 'Leave',
        self::TYPE_TIME_OFF => 'Time Off',
        self::TYPE_OVERTIME => 'Overtime',
    ];

    public array $chatterTracked = [
        'name'                  => 'Name',
        'type'                  => 'Type',
        'cuts_salary'           => 'Cuts Salary',
        'cuts_balance'          => 'Cuts Balance',
        'factor'                => 'Factor',
        'requires_title'        => 'Title Required',
        'requires_description'  => 'Description Required',
        'requires_attachment'   => 'Attachment Required',
        'active'                => 'Active',
        'company_id'            => 'Company',
    ];

    public array $sortable = [
        'name'    => 'name',
        'type'    => 'type',
        'company' => 'company_id',
        'active'  => 'active',
    ];

    public array $searchable = [
        'name'         => ['label' => 'Name',          'column' => 'name',         'type' => 'string'],
        'type'         => ['label' => 'Type',          'column' => 'type',         'type' => 'string'],
        'cuts_salary'  => ['label' => 'Cuts Salary',   'column' => 'cuts_salary',  'type' => 'boolean'],
        'cuts_balance' => ['label' => 'Cuts Balance',  'column' => 'cuts_balance', 'type' => 'boolean'],
        'active'       => ['label' => 'Active',        'column' => 'active',       'type' => 'boolean'],
        'company_id'   => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
    ];

    protected $fillable = [
        'uuid', 'name', 'type', 'cuts_salary', 'cuts_balance', 'factor',
        'requires_title', 'requires_description', 'requires_attachment',
        'active', 'company_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'cuts_salary'         => 'boolean',
        'cuts_balance'        => 'boolean',
        'requires_title'      => 'boolean',
        'requires_description'=> 'boolean',
        'requires_attachment' => 'boolean',
        'active'              => 'boolean',
        'factor'              => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(EmployeeRequest::class, 'subtype_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        // Fail-closed: an empty active-companies list returns ONLY the global
        // (company_id = null) subtypes — never per-company ones. With a
        // non-empty list, both globals and the actor's-company subtypes are
        // returned. Previously fail-open ("empty = all rows") leaked every
        // tenant's subtypes to users with no allowed companies.
        return $query->where(function ($q) use ($companyIds) {
            $q->whereNull('company_id');
            if (!empty($companyIds)) {
                $q->orWhereIn('company_id', $companyIds);
            }
        });
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? ucfirst($this->type);
    }
}
