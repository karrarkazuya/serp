<?php

namespace App\Models\Employees;

use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_employees';

    public array $chatterTracked = [
        // Identity
        'name'                       => 'Name',
        'name_ar'                    => 'Arabic Name',
        'name_en'                    => 'English Name',
        'family_name'                => 'Family Name',
        'employee_code'              => 'Employee Code',
        // Position
        'company_id'                 => 'Company',
        'department_id'              => 'Department',
        'job_id'                     => 'Job Position',
        'job_title'                  => 'Job Title',
        'parent_id'                  => 'Manager',
        'coach_id'                   => 'Coach',
        'expense_manager_id'         => 'Expense Approver',
        'attendance_manager_id'      => 'Attendance Approver',
        'work_location_id'           => 'Work Location',
        'resource_calendar_id'       => 'Working Schedule',
        // Contact
        'work_email'                 => 'Work Email',
        'work_phone'                 => 'Work Phone',
        'work_mobile'                => 'Work Mobile',
        'private_email'              => 'Private Email',
        // Contract & Status
        'employment_status'          => 'Employment Status',
        'contract_id'                => 'Contract',
        'hire_date'                  => 'Hire Date',
        'first_contract_date'        => 'First Contract Date',
        'wage'                       => 'Wage',
        'payment_method'             => 'Payment Method',
        'probation_start_date'       => 'Probation Start',
        'probation_end_date'         => 'Probation End',
        // Personal
        'gender'                     => 'Gender',
        'birthday'                   => 'Date of Birth',
        'marital_status'             => 'Marital Status',
        'nationality'                => 'Nationality',
        'certificate_level'          => 'Certificate Level',
        // Documents
        'visa_no'                    => 'Visa Number',
        'visa_expire'                => 'Visa Expiry',
        'work_permit_no'             => 'Work Permit No.',
        'work_permit_expiration_date' => 'Work Permit Expiry',
        // Linked records
        'user_id'                    => 'Linked User',
        'contact_id'                 => 'Linked Contact',
        // Departure
        'departure_date'             => 'Departure Date',
        'departure_reason_id'        => 'Departure Reason',
        'departure_description'      => 'Departure Notes',
        // Status
        'active'                     => 'Active',
    ];

    public array $sortable = [
        'name'       => 'name',
        'department' => 'department_id',
        'job'        => 'job_id',
        'status'     => 'employment_status',
        'hire_date'  => 'hire_date',
        'company'    => 'company_id',
    ];

    public array $searchable = [
        'name'              => ['label' => 'Name', 'column' => 'name', 'type' => 'string'],
        'name_ar'           => ['label' => 'Arabic Name', 'column' => 'name_ar', 'type' => 'string'],
        'name_en'           => ['label' => 'English Name', 'column' => 'name_en', 'type' => 'string'],
        'employee_code'     => ['label' => 'Employee Code', 'column' => 'employee_code', 'type' => 'string'],
        'work_email'        => ['label' => 'Work Email', 'column' => 'work_email', 'type' => 'email'],
        'work_phone'        => ['label' => 'Work Phone', 'column' => 'work_phone', 'type' => 'string'],
        'job_title'         => ['label' => 'Job Title', 'column' => 'job_title', 'type' => 'string'],
        'identification_id' => ['label' => 'ID Number', 'column' => 'identification_id', 'type' => 'string'],
        'ssnid'             => ['label' => 'SSN No', 'column' => 'ssnid', 'type' => 'string'],
        'passport_id'       => ['label' => 'Passport No', 'column' => 'passport_id', 'type' => 'string'],
        'employment_status' => ['label' => 'Status', 'column' => 'employment_status', 'type' => 'string'],
        'active'            => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'hire_date'         => ['label' => 'Hire Date', 'column' => 'hire_date', 'type' => 'date'],
        'visa_expire'       => ['label' => 'Visa Expiry', 'column' => 'visa_expire', 'type' => 'date'],
        'company_id'        => [
            'label'    => 'Company',
            'column'   => 'company_id',
            'type'     => 'relation',
            'relation' => ['table' => 'companies', 'field' => 'name'],
        ],
        'department_id'     => [
            'label'    => 'Department',
            'column'   => 'department_id',
            'type'     => 'relation',
            'relation' => ['table' => 'hr_departments', 'field' => 'name'],
        ],
        'job_id'            => [
            'label'    => 'Job Position',
            'column'   => 'job_id',
            'type'     => 'relation',
            'relation' => ['table' => 'hr_jobs', 'field' => 'name'],
        ],
        'created_at'        => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
        'updated_at'        => ['label' => 'Updated on', 'column' => 'updated_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'name', 'name_ar', 'name_en', 'family_name', 'mother_name',
        'employee_code', 'first_name', 'last_name', 'avatar',
        'barcode', 'pin_code', 'notes',
        'work_email', 'work_phone', 'work_mobile', 'job_title',
        'company_id', 'department_id', 'job_id', 'work_location_id', 'resource_calendar_id',
        'timezone', 'parent_id', 'coach_id', 'expense_manager_id', 'attendance_manager_id', 'user_id',
        'private_email', 'private_phone', 'private_mobile', 'private_address',
        'km_home_work', 'private_car_plate',
        'country', 'state', 'city', 'zip',
        'nationality', 'identification_id', 'passport_id', 'ssnid',
        'gender', 'birthday', 'place_of_birth', 'country_of_birth',
        'marital_status', 'spouse_name', 'spouse_birthdate', 'children',
        'certificate_level', 'study_field', 'study_school',
        'visa_no', 'work_permit_no', 'visa_expire', 'work_permit_expiration_date', 'work_permit_file',
        'employment_status', 'hire_date', 'first_contract_date', 'end_date',
        'departure_date', 'departure_reason_id', 'departure_description',
        'probation_start_date', 'probation_end_date',
        'contract_id', 'wage', 'payment_method',
        'emergency_contact', 'emergency_phone', 'emergency_relation',
        'contact_id',
        'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'active'                     => 'boolean',
        'birthday'                   => 'date',
        'spouse_birthdate'           => 'date',
        'hire_date'                  => 'date',
        'first_contract_date'        => 'date',
        'end_date'                   => 'date',
        'departure_date'             => 'date',
        'probation_start_date'       => 'date',
        'probation_end_date'         => 'date',
        'visa_expire'                => 'date',
        'work_permit_expiration_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }

    public function resourceCalendar(): BelongsTo
    {
        return $this->belongsTo(ResourceCalendar::class, 'resource_calendar_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'parent_id');
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'coach_id');
    }

    public function expenseManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'expense_manager_id');
    }

    public function attendanceManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'attendance_manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function departureReason(): BelongsTo
    {
        return $this->belongsTo(DepartureReason::class, 'departure_reason_id');
    }

    public function currentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'employee_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'employee_id')->with(['skill', 'skillType', 'skillLevel']);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class, 'employee_id');
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmployeeEmergencyContact::class, 'employee_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(EmployeeDependent::class, 'employee_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            EmployeeCategory::class,
            'hr_employee_category_rel',
            'employee_id',
            'category_id'
        );
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

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('active', false);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('name_ar', 'like', "%{$search}%")
              ->orWhere('name_en', 'like', "%{$search}%")
              ->orWhere('work_email', 'like', "%{$search}%")
              ->orWhere('work_phone', 'like', "%{$search}%")
              ->orWhere('employee_code', 'like', "%{$search}%");
        });
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        if (empty($companyIds)) return $query;
        return $query->whereIn('company_id', $companyIds);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? route('files.serve', $this->avatar) : null;
    }

    public static function employmentStatusLabel(string $status): string
    {
        return match ($status) {
            'draft'      => 'Draft',
            'active'     => 'Active',
            'probation'  => 'Probation',
            'suspended'  => 'Suspended',
            'resigned'   => 'Resigned',
            'terminated' => 'Terminated',
            default      => ucfirst($status),
        };
    }

    public static function employmentStatusColor(string $status): string
    {
        return match ($status) {
            'active'     => 'green',
            'probation'  => 'blue',
            'suspended'  => 'orange',
            'resigned'   => 'red',
            'terminated' => 'red',
            default      => 'gray',
        };
    }
}
