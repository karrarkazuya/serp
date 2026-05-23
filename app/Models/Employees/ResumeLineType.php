<?php

namespace App\Models\Employees;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class ResumeLineType extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_resume_line_types';

    protected $fillable = ['uuid', 'name', 'active', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public $sortable = ['name', 'active', 'created_at'];

    public $searchable = ['name'];

    public array $chatterTracked = [
        'name'   => 'Name',
        'active' => 'Active',
    ];

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
