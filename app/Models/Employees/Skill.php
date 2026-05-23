<?php

namespace App\Models\Employees;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Skill extends Model
{
    use SoftDeletes;

    protected $table = 'hr_skills';

    protected $fillable = ['uuid', 'name', 'skill_type_id', 'active', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public function skillType(): BelongsTo
    {
        return $this->belongsTo(SkillType::class, 'skill_type_id');
    }

    public function employeeSkills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'skill_id');
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
