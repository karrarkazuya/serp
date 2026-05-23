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

class AccountingAccountGroup extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'accounting_account_groups';

    public array $chatterTracked = [
        'name'              => 'Name',
        'code_prefix_start' => 'Code From',
        'code_prefix_end'   => 'Code To',
        'parent_id'         => 'Parent Group',
    ];

    public array $sortable = [
        'name'              => 'name',
        'code_prefix_start' => 'code_prefix_start',
        'created_at'        => 'created_at',
    ];

    public array $searchable = [
        'name'              => ['label' => 'Name',      'column' => 'name',              'type' => 'string'],
        'code_prefix_start' => ['label' => 'Code From', 'column' => 'code_prefix_start', 'type' => 'string'],
        'company_id'        => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'code_prefix_start',
        'code_prefix_end',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AccountingAccountGroup::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AccountingAccountGroup::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        return $query->whereIn('company_id', $companyIds);
    }
}
