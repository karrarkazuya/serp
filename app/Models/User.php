<?php

namespace App\Models;

use App\Models\Security\Role;
use App\Models\Security\Permission;
use App\Models\Settings\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public array $sortable = [
        'name' => 'name',
        'email' => 'email',
        'status' => 'active',
    ];

    public array $searchable = [
        'name' => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'email' => ['label' => 'Email', 'column' => 'email', 'type' => 'email'],
        'job_position' => ['label' => 'Job Position', 'column' => 'job_position', 'type' => 'string'],
        'phone' => ['label' => 'Phone', 'column' => 'phone', 'type' => 'string'],
        'active' => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'company_id' => [
            'label' => 'Default Company',
            'column' => 'company_id',
            'type' => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
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
        'name',
        'uuid',
        'email',
        'password',
        'active',
        'job_position',
        'phone',
        'avatar',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'active'            => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /** The user's default/primary company */
    public function defaultCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** All companies this user is allowed to access */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company');
    }

    // -------------------------------------------------------------------------
    // Permissions
    // -------------------------------------------------------------------------

    public function hasRole(string $roleKey): bool
    {
        return $this->roles()->where('key', $roleKey)->exists();
    }

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->roles()
            ->where('roles.active', true)
            ->whereHas('permissions', fn ($q) => $q->where('key', $permissionKey))
            ->exists();
    }

    public function isAdmin(): bool
    {
        return $this->roles()->where('key', 'admin')->exists();
    }

    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        return Permission::whereHas('roles', function ($q) {
            $q->whereIn('roles.id', $this->roles()->pluck('roles.id'));
        })->get();
    }

    // -------------------------------------------------------------------------
    // Company context helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the IDs of companies the user is allowed to access.
     * Falls back to all active companies for admin users.
     */
    public function getAllowedCompanyIds(): array
    {
        return $this->companies()->pluck('companies.id')->toArray();
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials;
    }
}
