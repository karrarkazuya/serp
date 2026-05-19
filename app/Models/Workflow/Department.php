<?php

namespace App\Models\Workflow;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasChatter;

    protected $table = 'workflow_departments';

    public array $sortable = [
        'name'       => 'name',
        'company'    => 'company_id',
        'active'     => 'active',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'company_id' => [
            'label' => 'Company',
            'column' => 'company_id',
            'type' => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = ['uuid', 'name', 'company_id', 'active', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workflowUsers(): HasMany
    {
        return $this->hasMany(WorkflowUser::class, 'default_department_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
