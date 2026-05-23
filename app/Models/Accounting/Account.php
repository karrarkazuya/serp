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

class Account extends Model
{
    use HasChatter, SoftDeletes;

    public const TYPES = [
        'asset_receivable'     => 'Receivable',
        'asset_cash'           => 'Bank and Cash',
        'asset_current'        => 'Current Assets',
        'asset_non_current'    => 'Non-current Assets',
        'asset_prepayments'    => 'Prepayments',
        'asset_fixed'          => 'Fixed Assets',
        'liability_payable'    => 'Payable',
        'liability_credit_card'=> 'Credit Card',
        'liability_current'    => 'Current Liabilities',
        'liability_non_current'=> 'Non-current Liabilities',
        'equity'               => 'Equity',
        'equity_unaffected'    => 'Current Year Earnings',
        'income'               => 'Income',
        'income_other'         => 'Other Income',
        'expense'              => 'Expenses',
        'expense_depreciation' => 'Depreciation',
        'expense_direct_cost'  => 'Cost of Revenue',
        'off_balance'          => 'Off-Balance Sheet',
    ];

    public const INTERNAL_TYPE_MAP = [
        'asset_receivable'  => 'receivable',
        'asset_cash'        => 'liquidity',
        'liability_payable' => 'payable',
    ];

    public array $chatterTracked = [
        'code'           => 'Code',
        'name'           => 'Name',
        'account_type'   => 'Type',
        'currency'       => 'Currency',
        'reconcile'      => 'Allow Reconciliation',
        'parent_id'      => 'Group',
        'company_id'     => 'Company',
    ];

    public array $sortable = [
        'code'         => 'code',
        'name'         => 'name',
        'account_type' => 'account_type',
        'currency'     => 'currency',
    ];

    public array $searchable = [
        'code'         => ['label' => 'Code',     'column' => 'code',         'type' => 'string'],
        'name'         => ['label' => 'Name',     'column' => 'name',         'type' => 'string'],
        'account_type' => ['label' => 'Type',     'column' => 'account_type', 'type' => 'string'],
        'currency'     => ['label' => 'Currency', 'column' => 'currency',     'type' => 'string'],
        'reconcile'    => ['label' => 'Reconcilable', 'column' => 'reconcile', 'type' => 'boolean'],
        'active'       => ['label' => 'Active',   'column' => 'active',       'type' => 'boolean'],
        'company_id'   => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'created_at'   => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id',
        'parent_id',
        'code',
        'name',
        'name_en',
        'account_type',
        'internal_type',
        'currency',
        'reconcile',
        'notes',
        'active',
    ];

    protected $casts = [
        'active'    => 'boolean',
        'reconcile' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function moveLines(): HasMany
    {
        return $this->hasMany(AccountMoveLine::class, 'account_id');
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
        if (empty($companyIds)) {
            return $query;
        }
        return $query->whereIn('company_id', $companyIds);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%");
        });
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->account_type] ?? $this->account_type;
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} {$this->name}";
    }
}
