<?php

namespace App\Models\Accounting;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class AccountTax extends Model
{
    use HasChatter, SoftDeletes;
    public const AMOUNT_TYPES = [
        'percent'  => 'Percentage (%)',
        'fixed'    => 'Fixed Amount',
        'division' => 'Division',
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
        'company_id',
        'name',
        'amount_type',
        'amount',
        'type_tax_use',
        'account_id',
        'description',
        'price_include',
        'include_base_amount',
        'active',
    ];

    protected $casts = [
        'amount'              => 'decimal:4',
        'price_include'       => 'boolean',
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
     * Compute the tax amount for a given base.
     *
     * Odoo parity (O10) — `price_include` and `include_base_amount` are TWO
     * different flags:
     *   - price_include = true        → the price already contains this tax;
     *                                   extract the embedded amount from the
     *                                   gross. The caller is expected to use
     *                                   extractBase() to also unwrap the net.
     *   - include_base_amount = true  → after this tax is computed, ADD it to
     *                                   the base for the next sequential tax
     *                                   (cascading taxes — e.g. Quebec QST on
     *                                   top of GST + base).
     *
     * Both flags can be set independently. computeAmount() only handles the
     * per-tax math; AccountingService::buildDocumentLines() owns the cascading
     * order and the price-include unwrap.
     */
    public function computeAmount(float $base): float
    {
        $rate = (float) $this->amount;

        if ($this->price_include) {
            // The `base` here is a GROSS amount (the listed line price). Extract
            // just the embedded tax portion. Net base is unwrapped separately
            // by extractBase().
            return match ($this->amount_type) {
                'percent'  => round($base - $base / (1 + $rate / 100), 4),
                'division' => round($base * $rate / 100, 4),
                'fixed'    => round($rate, 4),
                default    => 0.0,
            };
        }

        return match ($this->amount_type) {
            'percent'  => round($base * $rate / 100, 4),
            'division' => round($base * $rate / (100 - $rate), 4),
            'fixed'    => round($rate, 4),
            default    => 0.0,
        };
    }

    /**
     * Net base for a price-inclusive gross amount. Used when the line price
     * is gross (price_include = true) and we need the net to record on the
     * income/expense line.
     */
    public function extractBase(float $gross): float
    {
        if (!$this->price_include) {
            return $gross;
        }

        $rate = (float) $this->amount;

        return match ($this->amount_type) {
            'percent'  => round($gross / (1 + $rate / 100), 4),
            'division' => round($gross * (100 - $rate) / 100, 4),
            default    => $gross,
        };
    }

    public function getDisplayNameAttribute(): string
    {
        $rate = (float) $this->amount;
        $formatted = in_array($this->amount_type, ['percent', 'division'], true)
            ? number_format($rate, 2) . '%'
            : number_format($rate, 2);
        return "{$this->name} ({$formatted})";
    }
}
