<?php

namespace App\Models\Contacts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    public array $sortable = [
        'name' => 'name',
        'color' => 'color',
        'contacts' => 'contacts_count',
    ];

    public array $searchable = [
        'name' => ['label' => 'Tag Name', 'column' => 'name', 'type' => 'string'],
        'color' => ['label' => 'Color', 'column' => 'color', 'type' => 'string'],
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

    protected $fillable = ['uuid', 'name', 'color', 'created_by', 'updated_by'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag');
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
