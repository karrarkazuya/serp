<?php

namespace App\Models\Accounting;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

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

    /** @var array<string, array<string, mixed>> */
    public array $searchable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // MC-fix #13: dynamic dropdown for the `currency` filter sourced from
        // the seeded `currencies` table. Built in the constructor (not as a
        // static property) so SearchFilters::fieldsFor() picks up the live
        // list — adding a currency seed automatically expands the filter
        // dropdown without code changes. Falls back gracefully if the table
        // doesn't exist yet (during fresh-install migrations).
        $this->searchable = [
            'currency'   => [
                'label'   => 'Currency',
                'column'  => 'currency',
                'options' => self::currencyOptions(),
            ],
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
    }

    /**
     * @return array<string, string>
     */
    private static function currencyOptions(): array
    {
        try {
            return Currency::query()
                ->where('active', true)
                ->orderBy('code')
                ->pluck('name', 'code')
                ->all();
        } catch (Throwable $e) {
            // Schema not migrated yet (fresh install, test bootstrap) — fail
            // open with no options. The filter modal will still render; users
            // just won't get a dropdown until the currencies table exists.
            return [];
        }
    }

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
