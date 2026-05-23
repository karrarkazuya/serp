<?php

namespace App\Models\Employees;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeCategory extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_employee_categories';

    public array $chatterTracked = [
        'name'   => 'Name',
        'color'  => 'Color',
        'active' => 'Active',
    ];

    public array $sortable = [
        'name' => 'name',
    ];

    public array $searchable = [
        'name' => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
    ];

    protected $fillable = ['uuid', 'name', 'color', 'active', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'hr_employee_category_rel', 'category_id', 'employee_id');
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
}
