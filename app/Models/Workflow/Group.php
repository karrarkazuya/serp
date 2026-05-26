<?php

namespace App\Models\Workflow;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'workflow_groups';

    public array $sortable = [
        'name'       => 'name',
        'active'     => 'active',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    // Rule 4: uuid / created_by / updated_by are observer-managed, never in $fillable.
    protected $fillable = ['name', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workflowUsers(): BelongsToMany
    {
        return $this->belongsToMany(WorkflowUser::class, 'workflow_user_group', 'workflow_group_id', 'workflow_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
