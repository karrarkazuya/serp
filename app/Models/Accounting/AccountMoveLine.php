<?php

namespace App\Models\Accounting;

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Uom;
use App\Models\Settings\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountMoveLine extends Model
{
    public array $sortable = [
        'date'       => 'date',
        'debit'      => 'debit',
        'credit'     => 'credit',
        'state'      => 'state',
        'currency'   => 'currency',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Label',    'column' => 'name',     'type' => 'string'],
        'date'       => ['label' => 'Date',     'column' => 'date',     'type' => 'date'],
        'debit'      => ['label' => 'Debit',    'column' => 'debit',    'type' => 'numeric'],
        'credit'     => ['label' => 'Credit',   'column' => 'credit',   'type' => 'numeric'],
        'state'      => ['label' => 'State',    'column' => 'state',    'type' => 'string'],
        'currency'   => ['label' => 'Currency', 'column' => 'currency', 'type' => 'string'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
        'account_id' => [
            'label'    => 'Account',
            'column'   => 'account_id',
            'type'     => 'relation',
            'relation' => ['table' => 'accounts', 'field' => 'name'],
        ],
        'partner_id' => [
            'label'    => 'Partner',
            'column'   => 'partner_id',
            'type'     => 'relation',
            'relation' => ['table' => 'contacts', 'field' => 'name'],
        ],
        'journal_id' => [
            'label'    => 'Journal',
            'column'   => 'journal_id',
            'type'     => 'relation',
            'relation' => ['table' => 'account_journals', 'field' => 'name'],
        ],
    ];

    protected $fillable = [
        'uuid',
        'company_id',
        'move_id',
        'account_id',
        'journal_id',
        'partner_id',
        'product_id',
        'uom_id',
        'tax_line_id',
        'tax_base_amount',
        'name',
        'date',
        'state',
        'debit',
        'credit',
        'currency',
        'amount_currency',
        'sequence',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date'            => 'date',
        'debit'           => 'decimal:4',
        'credit'          => 'decimal:4',
        'amount_currency' => 'decimal:4',
        'tax_base_amount' => 'decimal:4',
        'sequence'        => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function move(): BelongsTo
    {
        return $this->belongsTo(AccountMove::class, 'move_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(AccountJournal::class, 'journal_id');
    }

    public function taxLine(): BelongsTo
    {
        return $this->belongsTo(AccountTax::class, 'tax_line_id');
    }

    public function taxes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AccountTax::class, 'account_move_line_taxes', 'account_move_line_id', 'account_tax_id');
    }

    public function matchedDebits(): HasMany
    {
        return $this->hasMany(AccountPartialReconcile::class, 'debit_move_line_id');
    }

    public function matchedCredits(): HasMany
    {
        return $this->hasMany(AccountPartialReconcile::class, 'credit_move_line_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'partner_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('state', 'posted');
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        if (empty($companyIds)) {
            return $query;
        }
        return $query->whereIn('company_id', $companyIds);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->debit - (float) $this->credit;
    }

    public function getMatchedAmountAttribute(): float
    {
        return round((float) $this->matchedDebits()->sum('amount') + (float) $this->matchedCredits()->sum('amount'), 2);
    }

    public function getResidualAmountAttribute(): float
    {
        return round(abs($this->balance) - $this->matched_amount, 2);
    }
}
