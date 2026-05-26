<?php

namespace App\Models\Employees;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeRequest extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_employee_requests';

    public const STATE_PENDING  = 'pending';
    public const STATE_APPROVED = 'approved';
    public const STATE_REJECTED = 'rejected';

    public const STATE_LABELS = [
        self::STATE_PENDING  => 'Pending',
        self::STATE_APPROVED => 'Approved',
        self::STATE_REJECTED => 'Rejected',
    ];

    public const STATE_COLORS = [
        self::STATE_PENDING  => 'orange',
        self::STATE_APPROVED => 'green',
        self::STATE_REJECTED => 'red',
    ];

    public array $chatterTracked = [
        'employee_id'             => 'Employee',
        'company_id'              => 'Company',
        'type'                    => 'Type',
        'subtype_id'              => 'Subtype',
        'start_at'                => 'From',
        'end_at'                  => 'To',
        'title'                   => 'Title',
        'description'             => 'Description',
        'manager_status'          => 'Manager Status',
        'manager_decision_reason' => 'Manager Reason',
        'hr_status'               => 'HR Status',
        'hr_decision_reason'      => 'HR Reason',
        'state'                   => 'State',
    ];

    public array $sortable = [
        'created_at' => 'created_at',
        'start_at'   => 'start_at',
        'employee'   => 'employee_id',
        'type'       => 'type',
        'state'      => 'state',
        'company'    => 'company_id',
    ];

    public array $searchable = [
        'title'       => ['label' => 'Title',     'column' => 'title',      'type' => 'string'],
        'description' => ['label' => 'Description','column' => 'description','type' => 'string'],
        'type'        => ['label' => 'Type',      'column' => 'type',       'type' => 'string'],
        'state'       => ['label' => 'State',     'column' => 'state',      'type' => 'string'],
        'start_at'    => ['label' => 'From',      'column' => 'start_at',   'type' => 'datetime'],
        'end_at'      => ['label' => 'To',        'column' => 'end_at',     'type' => 'datetime'],
        'employee_id' => [
            'label' => 'Employee', 'column' => 'employee_id', 'type' => 'relation',
            'relation' => ['table' => 'hr_employees', 'field' => 'name'],
        ],
        'subtype_id' => [
            'label' => 'Subtype', 'column' => 'subtype_id', 'type' => 'relation',
            'relation' => ['table' => 'hr_request_subtypes', 'field' => 'name'],
        ],
        'company_id' => [
            'label' => 'Company', 'column' => 'company_id', 'type' => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
    ];

    // Rule 4: uuid / created_by / updated_by are observer-managed, never in $fillable.
    // State-machine fields (manager_*/hr_*/state) stay in $fillable because the
    // service uses $locked->fill([...]) inside decide(); the FormRequest
    // (StoreEmployeeRequestRequest) does NOT validate them so over-posting via
    // $request->validated() is impossible.
    protected $fillable = [
        'employee_id', 'company_id', 'type', 'subtype_id',
        'start_at', 'end_at', 'duration_days', 'duration_hours',
        'title', 'description', 'attachment',
        'manager_status', 'manager_decision_at', 'manager_decision_by', 'manager_decision_reason',
        'hr_status', 'hr_decision_at', 'hr_decision_by', 'hr_decision_reason',
        'state',
    ];

    protected $casts = [
        'start_at'             => 'datetime',
        'end_at'               => 'datetime',
        'manager_decision_at'  => 'datetime',
        'hr_decision_at'       => 'datetime',
        'duration_days'        => 'decimal:2',
        'duration_hours'       => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        // withTrashed: archiving an employee with historical requests must not
        // null out the relation — approval / notification / show pages still
        // need to resolve the person's name + manager link.
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withTrashed();
    }

    public function subtype(): BelongsTo
    {
        // withTrashed: an archived/soft-deleted subtype must still resolve on
        // historical requests so approval/recompute can read its factor + flags
        // (the FK is restrictOnDelete, so hard delete is already blocked).
        return $this->belongsTo(RequestSubtype::class, 'subtype_id')->withTrashed();
    }

    public function managerDecisionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_decision_by');
    }

    public function hrDecisionUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_decision_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'request_id');
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        if (empty($companyIds)) return $query;
        return $query->whereIn('company_id', $companyIds);
    }

    public function getStateLabelAttribute(): string
    {
        return self::STATE_LABELS[$this->state] ?? ucfirst($this->state);
    }

    public function getStateColorAttribute(): string
    {
        return self::STATE_COLORS[$this->state] ?? 'gray';
    }

    public function isLocked(): bool
    {
        return in_array($this->state, [self::STATE_APPROVED, self::STATE_REJECTED], true);
    }

    /**
     * Recompute the cached `state` from the two approval columns.
     * Either rejection = rejected; HR approval = approved (HR override);
     * otherwise pending. Caller is responsible for saving.
     */
    public function recomputeState(): void
    {
        if ($this->manager_status === self::STATE_REJECTED || $this->hr_status === self::STATE_REJECTED) {
            $this->state = self::STATE_REJECTED;
        } elseif ($this->hr_status === self::STATE_APPROVED) {
            $this->state = self::STATE_APPROVED;
        } else {
            $this->state = self::STATE_PENDING;
        }
    }
}
