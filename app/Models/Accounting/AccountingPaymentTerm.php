<?php

namespace App\Models\Accounting;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingPaymentTerm extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'accounting_payment_terms';

    public array $chatterTracked = [
        'name'   => 'Name',
        'note'   => 'Note',
        'active' => 'Active',
    ];

    public array $sortable = [
        'name'       => 'name',
        'active'     => 'active',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Name',       'column' => 'name',       'type' => 'string'],
        'active'     => ['label' => 'Active',      'column' => 'active',     'type' => 'boolean'],
        'company_id' => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'note',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingPaymentTermLine::class, 'payment_term_id')->orderBy('sequence');
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

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('active', false);
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        return $query->whereIn('company_id', $companyIds);
    }
}
