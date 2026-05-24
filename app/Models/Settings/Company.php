<?php

namespace App\Models\Settings;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'name'     => 'Name',
        'email'    => 'Email',
        'phone'    => 'Phone',
        'mobile'   => 'Mobile',
        'website'  => 'Website',
        'tax_id'   => 'Tax ID',
        'currency' => 'Currency',
        'city'     => 'City',
        'country'  => 'Country',
    ];

    public array $sortable = [
        'name' => 'name',
        'email' => 'email',
        'phone' => 'phone',
        'city' => 'city',
        'users' => 'users_count',
        'status' => 'active',
    ];

    public array $searchable = [
        'name' => ['label' => 'Company', 'column' => 'name', 'type' => 'string'],
        'email' => ['label' => 'Email', 'column' => 'email', 'type' => 'email'],
        'phone' => ['label' => 'Phone', 'column' => 'phone', 'type' => 'string'],
        'mobile' => ['label' => 'Mobile', 'column' => 'mobile', 'type' => 'string'],
        'website' => ['label' => 'Website', 'column' => 'website', 'type' => 'string'],
        'city' => ['label' => 'City', 'column' => 'city', 'type' => 'string'],
        'state' => ['label' => 'State', 'column' => 'state', 'type' => 'string'],
        'country' => ['label' => 'Country', 'column' => 'country', 'type' => 'string'],
        'tax_id' => ['label' => 'Tax ID', 'column' => 'tax_id', 'type' => 'string'],
        'currency' => ['label' => 'Currency', 'column' => 'currency', 'type' => 'string'],
        'active' => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_by' => [
            'label' => 'Created by',
            'column' => 'created_by',
            'type' => 'relation',
            'relation' => ['table' => 'users', 'field' => 'name'],
        ],
        'updated_by' => [
            'label' => 'Updated by',
            'column' => 'updated_by',
            'type' => 'relation',
            'relation' => ['table' => 'users', 'field' => 'name'],
        ],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
        'updated_at' => ['label' => 'Updated on', 'column' => 'updated_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'mobile',
        'website',
        'street',
        'city',
        'state',
        'country',
        'zip',
        'tax_id',
        'currency',
        'logo',
        'notes',
        'active',
        'accounting_period_lock_date',
        'accounting_fiscal_year_lock_date',
        'income_currency_exchange_account_id',
        'expense_currency_exchange_account_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active'                            => 'boolean',
        'accounting_period_lock_date'       => 'date',
        'accounting_fiscal_year_lock_date'  => 'date',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_company');
    }

    /**
     * MC2 (Odoo parity): currencies this company is allowed to invoice/bill/pay
     * in. Empty pivot = "all active currencies" (so existing single-currency
     * companies keep working without setup). The company's base `currency` is
     * always permitted.
     */
    public function allowedCurrencies(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Accounting\Currency::class, 'company_currencies');
    }

    /**
     * MC2: target accounts when cross-currency reconciliation leaves a base
     * residual after foreign residuals close. Both must be configured before
     * reconcileLines will post adjustments — otherwise the reconcile errors
     * with a clear setup message.
     */
    public function incomeCurrencyExchangeAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\Account::class, 'income_currency_exchange_account_id');
    }

    public function expenseCurrencyExchangeAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\Account::class, 'expense_currency_exchange_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? route('files.serve', $this->logo) : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%");
        });
    }
}
