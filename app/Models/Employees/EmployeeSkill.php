<?php

namespace App\Models\Employees;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSkill extends Model
{
    protected $table = 'hr_employee_skills';

    protected $fillable = [
        'employee_id', 'skill_id', 'skill_type_id', 'skill_level_id', 'years_of_experience',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class, 'skill_id');
    }

    public function skillType(): BelongsTo
    {
        return $this->belongsTo(SkillType::class, 'skill_type_id');
    }

    public function skillLevel(): BelongsTo
    {
        return $this->belongsTo(SkillLevel::class, 'skill_level_id');
    }

    public function getLevelProgressAttribute(): int
    {
        return $this->skillLevel?->level_progress ?? 0;
    }
}
