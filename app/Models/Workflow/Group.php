<?php

namespace App\Models\Workflow;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    use HasChatter;

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

    protected $fillable = ['uuid', 'name', 'active', 'created_by', 'updated_by'];

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
