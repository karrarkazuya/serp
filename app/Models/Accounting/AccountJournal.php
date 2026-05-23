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

class AccountJournal extends Model
{
    use HasChatter, SoftDeletes;

    public const TYPES = [
        'sales'    => 'Sales',
        'purchase' => 'Purchase',
        'cash'     => 'Cash',
        'bank'     => 'Bank',
        'general'  => 'Miscellaneous',
    ];

    public array $chatterTracked = [
        'code'                  => 'Code',
        'name'                  => 'Name',
        'type'                  => 'Type',
        'currency'              => 'Currency',
        'default_account_id'    => 'Default Account',
        'suspense_account_id'   => 'Suspense Account',
        'sequence_prefix'       => 'Sequence Prefix',
        'sequence_padding'      => 'Sequence Padding',
        'company_id'            => 'Company',
    ];

    public array $sortable = [
        'code' => 'code',
        'name' => 'name',
        'type' => 'type',
    ];

    public array $searchable = [
        'code'     => ['label' => 'Code', 'column' => 'code', 'type' => 'string'],
        'name'     => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'type'     => ['label' => 'Type', 'column' => 'type', 'type' => 'string'],
        'currency' => ['label' => 'Currency', 'column' => 'currency', 'type' => 'string'],
        'active'   => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'company_id' => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
    ];

    protected $fillable = [
        'uuid',
        'company_id',
        'default_account_id',
        'suspense_account_id',
        'code',
        'name',
        'type',
        'currency',
        'sequence_prefix',
        'sequence_next_number',
        'sequence_padding',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active'               => 'boolean',
        'sequence_next_number' => 'integer',
        'sequence_padding'     => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    public function suspenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'suspense_account_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(AccountMove::class, 'journal_id');
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
        return self::TYPES[$this->type] ?? $this->type;
    }
}
