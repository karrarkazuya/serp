<?php

namespace App\Models\Inventory;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class Uom extends Model
{
    protected $table = 'inventory_uoms';

    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'name'     => 'Name',
        'symbol'   => 'Symbol',
        'ratio'    => 'Ratio',
        'uom_type' => 'Type',
        'active'   => 'Active',
    ];

    public array $sortable = [
        'name'     => 'name',
        'category' => 'uom_category_id',
    ];

    public array $searchable = [
        'name'     => ['label' => 'Name',     'column' => 'name',     'type' => 'string'],
        'symbol'   => ['label' => 'Symbol',   'column' => 'symbol',   'type' => 'string'],
        'active'   => ['label' => 'Active',   'column' => 'active',   'type' => 'boolean'],
    ];

    protected $fillable = [
        'uom_category_id', 'name', 'symbol', 'ratio', 'rounding', 'uom_type', 'active',
    ];

    protected $casts = [
        'ratio'    => 'decimal:6',
        'rounding' => 'decimal:6',
        'active'   => 'boolean',
    ];

    public function category(): BelongsTo { return $this->belongsTo(UomCategory::class, 'uom_category_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo  { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }

    public function isReference(): bool { return $this->uom_type === 'reference'; }

    // R5 / Rule 12: views must not render raw enum DB values. uom_type
    // stored as 'reference' / 'bigger' / 'smaller' maps to a proper label.
    public const TYPE_LABELS = [
        'reference' => 'Reference',
        'bigger'    => 'Bigger than reference',
        'smaller'   => 'Smaller than reference',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->uom_type] ?? $this->uom_type;
    }

    public function isSameCategoryAs(Uom $other): bool
    {
        return (int) $this->uom_category_id === (int) $other->uom_category_id;
    }

    /**
     * Convert `$qty` (expressed in $this UoM) into $to UoM.
     *
     * `ratio` here means **"reference units per this UoM"** — e.g. g.ratio =
     * 0.001 (one gram is 0.001 kg), Dozen.ratio = 12 (one dozen is 12 units),
     * lb.ratio = 0.4536 (one pound is 0.4536 kg). See the UoM seeder.
     *
     *   base_qty = qty_in_this * this.ratio   (reference units)
     *   qty_in_to = base_qty / to.ratio
     *
     * Watch out: the previous formula was reversed (qty / ratio * to.ratio),
     * which happened to be unreachable because nothing called convertQty in
     * the inventory flows. Now that PickingService / ScrapService rely on
     * this, the formula matters. Cross-category conversions throw —
     * Rule::uomMatchingProductCategoryRule rejects mismatched UoMs at the
     * form layer, so this is defense-in-depth, not a user-facing error path.
     */
    public function convertQty(float $qty, Uom $to): float
    {
        if ($this->id === $to->id) return $qty;
        if (!$this->isSameCategoryAs($to)) {
            throw new \RuntimeException(__('inventory.err_uom_category_mismatch', [
                'from' => $this->name,
                'to'   => $to->name,
            ]));
        }
        $fromRatio = (float) $this->ratio;
        $toRatio   = (float) $to->ratio;
        if ($fromRatio <= 0 || $toRatio <= 0) {
            throw new \RuntimeException(__('inventory.err_uom_zero_ratio', [
                'name' => $fromRatio <= 0 ? $this->name : $to->name,
            ]));
        }
        $base = $qty * $fromRatio;
        return round($base / $toRatio, 4);
    }
}
