<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MC1 (Odoo parity): currency lookup with formatting metadata. The existing
 * string `currency` columns on companies / moves / move_lines stay as-is for
 * back-compat (denormalised ISO codes); this model carries the metadata that
 * lets us format amounts correctly per-currency.
 *
 * `decimal_places` = how many digits after the decimal point (USD=2, JPY=0,
 * BHD=3). Used for rounding tax/payment amounts and for number_format calls
 * in views.
 *
 * `rounding` = the smallest representable unit (USD=0.01, JPY=1, CHF coin=0.05
 * for cash). When rounding to nearest unit, divide-round-multiply.
 *
 * `position` = where to render the symbol ('before' for USD/EUR, 'after' for
 * د.ع/€ in some locales).
 */
class Currency extends Model
{
    use SoftDeletes;

    public const POSITIONS = ['before' => 'Before amount', 'after' => 'After amount'];

    public array $sortable = [
        'code'   => 'code',
        'name'   => 'name',
        'active' => 'active',
    ];

    public array $searchable = [
        'code'   => ['label' => 'Code',           'column' => 'code',           'type' => 'string'],
        'name'   => ['label' => 'Name',           'column' => 'name',           'type' => 'string'],
        'symbol' => ['label' => 'Symbol',         'column' => 'symbol',         'type' => 'string'],
        'decimal_places' => ['label' => 'Decimals', 'column' => 'decimal_places', 'type' => 'integer'],
        'active' => ['label' => 'Active',         'column' => 'active',         'type' => 'boolean'],
    ];

    protected $fillable = [
        'uuid', 'code', 'name', 'symbol', 'position',
        'decimal_places', 'rounding', 'active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'rounding'       => 'decimal:6',
        'active'         => 'boolean',
    ];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Settings\Company::class, 'company_currencies');
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder { return $q->where('active', true); }

    /**
     * Format an amount according to this currency's metadata. Used by views
     * instead of bare `number_format(..., 2)` so non-2-decimal currencies
     * (JPY, BHD, IQD) render correctly.
     */
    public function format(float $amount): string
    {
        $rounded = $this->round($amount);
        $number  = number_format($rounded, $this->decimal_places, '.', ',');
        $symbol  = $this->symbol ?: $this->code;

        return $this->position === 'before'
            ? "{$symbol}{$number}"
            : "{$number} {$symbol}";
    }

    /**
     * Round to this currency's rounding step. For most currencies that's
     * 0.01 (= round to 2 decimals); for cash-rounded Swiss Franc it's 0.05.
     */
    public function round(float $amount): float
    {
        $rounding = (float) ($this->rounding ?: 0.01);
        if ($rounding <= 0) {
            return round($amount, $this->decimal_places);
        }
        return round($amount / $rounding) * $rounding;
    }

    /**
     * Look up a Currency by ISO code. Cached per request via the static
     * resolver below so view helpers don't query repeatedly.
     */
    public static function byCode(string $code): ?self
    {
        return self::resolveByCode($code);
    }

    /** @var array<string, ?self> */
    private static array $cacheByCode = [];

    private static function resolveByCode(string $code): ?self
    {
        $code = strtoupper(trim($code));
        if (array_key_exists($code, self::$cacheByCode)) {
            return self::$cacheByCode[$code];
        }
        return self::$cacheByCode[$code] = self::query()->where('code', $code)->first();
    }
}
