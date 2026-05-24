<?php

namespace App\Models\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use SoftDeletes;

    public array $sortable = [
        'name' => 'name',
        'key' => 'key',
        'permissions' => 'permissions_count',
        'users' => 'users_count',
        'status' => 'active',
    ];

    public array $searchable = [
        'name' => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'key' => ['label' => 'Key', 'column' => 'key', 'type' => 'string'],
        'description' => ['label' => 'Description', 'column' => 'description', 'type' => 'text'],
        'active' => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
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

    protected $fillable = ['uuid', 'name', 'key', 'description', 'active', 'created_by', 'updated_by'];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** Role keys that the seeder owns; mutation of key/permissions/active is blocked at the controller layer. */
    public const SYSTEM_KEYS = ['admin'];

    public function isSystem(): bool
    {
        return in_array($this->key, self::SYSTEM_KEYS, true);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'user_role');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasPermission(string $permissionKey): bool
    {
        return $this->permissions()->where('key', $permissionKey)->exists();
    }
}
