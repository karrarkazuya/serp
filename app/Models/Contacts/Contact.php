<?php

namespace App\Models\Contacts;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'name'         => 'Name',
        'contact_type' => 'Contact Type',
        'email'        => 'Email',
        'job_position' => 'Job Position',
        'company_name' => 'Company Name',
        'company_id'   => 'Company',
        'tax_id'       => 'Tax ID',
        'avatar'       => 'Avatar',
        'website'      => 'Website',
    ];

    public array $sortable = [
        'name'    => 'name',
        'email'   => 'email',
        'city'    => 'city',
        'country' => 'country',
        'company' => 'company_name',
    ];

    public array $searchable = [
        'name'         => ['label' => 'Name',         'column' => 'name',         'type' => 'string'],
        'email'        => ['label' => 'Email',        'column' => 'email',        'type' => 'email'],
        'company_name' => ['label' => 'Company Name', 'column' => 'company_name', 'type' => 'string'],
        'contact_type' => ['label' => 'Contact type', 'column' => 'contact_type', 'type' => 'string'],
        'city'         => ['label' => 'City',         'column' => 'city',         'type' => 'string'],
        'state'        => ['label' => 'State',        'column' => 'state',        'type' => 'string'],
        'country'      => ['label' => 'Country',      'column' => 'country',      'type' => 'string'],
        'tax_id'       => ['label' => 'Tax ID',       'column' => 'tax_id',       'type' => 'string'],
        'job_position' => ['label' => 'Job Position', 'column' => 'job_position', 'type' => 'string'],
        'active'       => ['label' => 'Active',       'column' => 'active',       'type' => 'boolean'],
        'created_by'   => [
            'label'    => 'Created by',
            'column'   => 'created_by',
            'type'     => 'relation',
            'relation' => ['table' => 'users', 'field' => 'name'],
        ],
        'updated_by'   => [
            'label'    => 'Updated by',
            'column'   => 'updated_by',
            'type'     => 'relation',
            'relation' => ['table' => 'users', 'field' => 'name'],
        ],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
        'updated_at' => ['label' => 'Updated on', 'column' => 'updated_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid',
        'company_id',
        'parent_id',
        'name',
        'company_name',
        'contact_type',
        'email',
        'website',
        'street',
        'city',
        'state',
        'country',
        'zip',
        'tax_id',
        'job_position',
        'notes',
        'avatar',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Contact::class, 'parent_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tag');
    }

    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class)->orderBy('id');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? route('files.serve', $this->avatar) : null;
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

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('company_name', 'like', "%{$search}%")
              ->orWhereHas('phones', fn($pq) => $pq->where('phone', 'like', "%{$search}%"));
        });
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        if (empty($companyIds)) {
            return $query;
        }
        return $query->whereIn('company_id', $companyIds);
    }

    public function isCompany(): bool
    {
        return $this->contact_type === 'company';
    }

    public function isIndividual(): bool
    {
        return $this->contact_type === 'individual';
    }
}
