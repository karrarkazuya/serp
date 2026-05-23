<?php

namespace App\Models\Accounting;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class CurrencyRate extends Model
{
    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'currency' => 'Currency',
        'rate'     => 'Rate',
        'date'     => 'Effective Date',
        'active'   => 'Active',
    ];

    public array $sortable = [
        'currency'   => 'currency',
        'rate'       => 'rate',
        'date'       => 'date',
        'active'     => 'active',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'currency'   => ['label' => 'Currency',       'column' => 'currency', 'type' => 'string'],
        'rate'       => ['label' => 'Rate',            'column' => 'rate',     'type' => 'numeric'],
        'date'       => ['label' => 'Effective Date',  'column' => 'date',     'type' => 'date'],
        'active'     => ['label' => 'Active',          'column' => 'active',   'type' => 'boolean'],
        'company_id' => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id',
        'currency',
        'rate',
        'date',
        'active',
    ];

    protected $casts = [
        'rate'   => 'decimal:6',
        'date'   => 'date',
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
        return $query->whereIn('company_id', $companyIds);
    }
}
