<?php

namespace App\Models\Accounting;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccountTax extends Model
{
    use HasChatter;
    public const AMOUNT_TYPES = [
        'percent' => 'Percentage (%)',
        'fixed'   => 'Fixed Amount',
    ];

    public const TYPE_TAX_USE = [
        'sale'     => 'Sales',
        'purchase' => 'Purchases',
        'none'     => 'None',
    ];

    public array $chatterTracked = [
        'name'                => 'Name',
        'amount_type'         => 'Type',
        'amount'              => 'Rate',
        'type_tax_use'        => 'Applies To',
        'include_base_amount' => 'Price Inclusive',
        'account_id'          => ['label' => 'Tax Account', 'table' => 'accounts', 'column' => 'name'],
        'active'              => 'Active',
    ];

    public array $sortable = [
        'name'         => 'name',
        'amount_type'  => 'amount_type',
        'amount'       => 'amount',
        'type_tax_use' => 'type_tax_use',
        'active'       => 'active',
        'created_at'   => 'created_at',
    ];

    public array $searchable = [
        'name'         => ['label' => 'Name',        'column' => 'name',        'type' => 'string'],
        'amount_type'  => ['label' => 'Type',         'column' => 'amount_type', 'type' => 'string'],
        'amount'       => ['label' => 'Rate',         'column' => 'amount',      'type' => 'numeric'],
        'type_tax_use' => ['label' => 'Applies To',  'column' => 'type_tax_use','type' => 'string'],
        'include_base_amount' => ['label' => 'Price Inclusive', 'column' => 'include_base_amount', 'type' => 'boolean'],
        'active'       => ['label' => 'Active',       'column' => 'active',      'type' => 'boolean'],
        'company_id'   => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'created_at'   => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'amount_type',
        'amount',
        'type_tax_use',
        'account_id',
        'description',
        'include_base_amount',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount'              => 'decimal:4',
        'include_base_amount' => 'boolean',
        'active'              => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(AccountMoveLine::class, 'account_move_line_taxes', 'account_tax_id', 'account_move_line_id');
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

    public function scopeForSale(Builder $query): Builder
    {
        return $query->whereIn('type_tax_use', ['sale', 'none']);
    }

    public function scopeForPurchase(Builder $query): Builder
    {
        return $query->whereIn('type_tax_use', ['purchase', 'none']);
    }

    /**
     * Compute the tax amount for a given base amount.
     * If include_base_amount = true the base is a gross amount and we extract tax from it.
     */
    public function computeAmount(float $base): float
    {
        if ($this->include_base_amount) {
            // price-inclusive: extract tax from gross
            return match ($this->amount_type) {
                'percent' => round($base - $base / (1 + (float) $this->amount / 100), 4),
                'fixed'   => round((float) $this->amount, 4),
                default   => 0.0,
            };
        }

        return match ($this->amount_type) {
            'percent' => round($base * (float) $this->amount / 100, 4),
            'fixed'   => round((float) $this->amount, 4),
            default   => 0.0,
        };
    }

    /**
     * Net base for a price-inclusive gross amount.
     */
    public function extractBase(float $gross): float
    {
        if (!$this->include_base_amount || $this->amount_type !== 'percent') {
            return $gross;
        }
        return round($gross / (1 + (float) $this->amount / 100), 4);
    }

    public function getDisplayNameAttribute(): string
    {
        $amount = $this->amount_type === 'percent'
            ? number_format((float) $this->amount, 2) . '%'
            : number_format((float) $this->amount, 2);
        return "{$this->name} ({$amount})";
    }
}
