<?php

namespace App\Models\Employees;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use SoftDeletes;

    protected $table = 'hr_employee_documents';

    protected $fillable = [
        'uuid', 'name', 'document_type', 'file_path',
        'issue_date', 'expiry_date', 'notify_before_days', 'notes',
        'employee_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'expiry_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function attachedFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_path', 'uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        return $this->expiry_date
            && !$this->expiry_date->isPast()
            && $this->expiry_date->diffInDays(now()) <= $this->notify_before_days;
    }
}
