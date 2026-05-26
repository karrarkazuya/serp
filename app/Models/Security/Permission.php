<?php

namespace App\Models\Security;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use SoftDeletes;

    public array $sortable = [
        'name' => 'name',
        'key' => 'key',
        'description' => 'description',
    ];

    public array $searchable = [
        'name' => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'key' => ['label' => 'Key', 'column' => 'key', 'type' => 'string'],
        'module' => ['label' => 'Module', 'column' => 'module', 'type' => 'string'],
        'description' => ['label' => 'Description', 'column' => 'description', 'type' => 'text'],
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

    // Rule 4: uuid / created_by / updated_by are observer-managed, never in $fillable.
    protected $fillable = ['name', 'key', 'module', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
