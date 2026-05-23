<?php

namespace App\Models\Employees;

use App\Models\Contacts\Contact;
use App\Models\Employees\EmployeeCertificate;
use App\Models\Employees\EmployeePosition;
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
        'mother_name'                => 'Mother Name',
        'first_name'                 => 'First Name',
        'last_name'                  => 'Last Name',
        'employee_code'              => 'Employee Code',
        'barcode'                    => 'Barcode',
        'notes'                      => 'Notes',
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
        'timezone'                   => 'Timezone',
        // Work Contact
        'work_email'                 => 'Work Email',
        'work_phone'                 => 'Work Phone',
        'work_mobile'                => 'Work Mobile',
        // Private Contact
        'private_email'              => 'Private Email',
        'private_phone'              => 'Private Phone',
        'private_mobile'             => 'Private Mobile',
        'private_address'            => 'Private Address',
        'private_car_plate'          => 'Car Plate',
        'km_home_work'               => 'Home-Work Distance (km)',
        // Address
        'country'                    => 'Country',
        'state'                      => 'State',
        'city'                       => 'City',
        'zip'                        => 'ZIP',
        // Personal
        'gender'                     => 'Gender',
        'birthday'                   => 'Date of Birth',
        'place_of_birth'             => 'Place of Birth',
        'country_of_birth'           => 'Country of Birth',
        'nationality'                => 'Nationality',
        'identification_id'          => 'ID Number',
        'passport_id'                => 'Passport No.',
        'ssnid'                      => 'SSN No.',
        'marital_status'             => 'Marital Status',
        'spouse_name'                => 'Spouse Name',
        'spouse_birthdate'           => 'Spouse Birthdate',
        'children'                   => 'No. of Dependent Children',
        // Education
        'certificate_level'          => 'Certificate Level',
        'study_field'                => 'Field of Study',
        'study_school'               => 'School',
        // Work Permit
        'visa_no'                    => 'Visa Number',
        'visa_expire'                => 'Visa Expiry',
        'work_permit_no'             => 'Work Permit No.',
        'work_permit_expiration_date' => 'Work Permit Expiry',
        // Contract & Status
        'employment_status'          => 'Employment Status',
        'contract_id'                => 'Contract',
        'hire_date'                  => 'Hire Date',
        'first_contract_date'        => 'First Contract Date',
        'end_date'                   => 'End Date',
        'wage'                       => 'Wage',
        'payment_method'             => 'Payment Method',
        'probation_start_date'       => 'Probation Start',
        'probation_end_date'         => 'Probation End',
        // Emergency
        'emergency_contact'          => 'Emergency Contact',
        'emergency_phone'            => 'Emergency Phone',
        'emergency_relation'         => 'Emergency Relation',
        // Linked records
        'user_id'                    => 'Linked User',
        'contact_id'                 => 'Linked Contact',
        // Departure
        'departure_date'             => 'Departure Date',
        'departure_reason_id'        => 'Departure Reason',
        'departure_description'      => 'Departure Notes',
        // Position extras
        'scientific_title' => 'Scientific Title',
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
        // Identity (included in global text search)
        'name'              => ['label' => 'Name',          'column' => 'name',          'type' => 'string'],
        'name_ar'           => ['label' => 'Arabic Name',   'column' => 'name_ar',       'type' => 'string'],
        'name_en'           => ['label' => 'English Name',  'column' => 'name_en',       'type' => 'string'],
        'family_name'       => ['label' => 'Family Name',   'column' => 'family_name',   'type' => 'string'],
        'mother_name'       => ['label' => 'Mother Name',   'column' => 'mother_name',   'type' => 'string'],
        'first_name'        => ['label' => 'First Name',    'column' => 'first_name',    'type' => 'string'],
        'last_name'         => ['label' => 'Last Name',     'column' => 'last_name',     'type' => 'string'],
        'employee_code'     => ['label' => 'Employee Code', 'column' => 'employee_code', 'type' => 'string'],
        'barcode'           => ['label' => 'Barcode',       'column' => 'barcode',       'type' => 'string'],
        // Work Contact
        'work_email'        => ['label' => 'Work Email',    'column' => 'work_email',    'type' => 'email'],
        'work_phone'        => ['label' => 'Work Phone',    'column' => 'work_phone',    'type' => 'string'],
        'work_mobile'       => ['label' => 'Work Mobile',   'column' => 'work_mobile',   'type' => 'string'],
        'job_title'         => ['label' => 'Job Title',     'column' => 'job_title',     'type' => 'string'],
        'timezone'          => ['label' => 'Timezone',      'column' => 'timezone',      'type' => 'string'],
        // Personal
        'gender'            => ['label' => 'Gender',         'column' => 'gender',         'type' => 'string'],
        'birthday'          => ['label' => 'Date of Birth',  'column' => 'birthday',       'type' => 'date'],
        'nationality'       => ['label' => 'Nationality',    'column' => 'nationality',    'type' => 'string'],
        'place_of_birth'    => ['label' => 'Place of Birth', 'column' => 'place_of_birth', 'type' => 'string'],
        'country_of_birth'  => ['label' => 'Country of Birth', 'column' => 'country_of_birth', 'type' => 'string'],
        'marital_status'    => ['label' => 'Marital Status', 'column' => 'marital_status', 'type' => 'string'],
        // Address
        'country'           => ['label' => 'Country', 'column' => 'country', 'type' => 'string'],
        'city'              => ['label' => 'City',    'column' => 'city',    'type' => 'string'],
        // Documents
        'identification_id' => ['label' => 'ID Number',    'column' => 'identification_id', 'type' => 'string'],
        'ssnid'             => ['label' => 'SSN No',        'column' => 'ssnid',             'type' => 'string'],
        'passport_id'       => ['label' => 'Passport No',   'column' => 'passport_id',       'type' => 'string'],
        'visa_no'           => ['label' => 'Visa No',        'column' => 'visa_no',           'type' => 'string'],
        'work_permit_no'    => ['label' => 'Work Permit No', 'column' => 'work_permit_no',    'type' => 'string'],
        // Education
        'certificate_level' => ['label' => 'Certificate Level', 'column' => 'certificate_level', 'type' => 'string'],
        'study_field'       => ['label' => 'Field of Study',    'column' => 'study_field',       'type' => 'string'],
        'study_school'      => ['label' => 'School',            'column' => 'study_school',      'type' => 'string'],
        // Employment
        'employment_status'          => ['label' => 'Status',          'column' => 'employment_status',          'type' => 'string'],
        'hire_date'                  => ['label' => 'Hire Date',        'column' => 'hire_date',                  'type' => 'date'],
        'first_contract_date'        => ['label' => 'First Contract',   'column' => 'first_contract_date',        'type' => 'date'],
        'end_date'                   => ['label' => 'End Date',         'column' => 'end_date',                   'type' => 'date'],
        'probation_start_date'       => ['label' => 'Probation Start',  'column' => 'probation_start_date',       'type' => 'date'],
        'probation_end_date'         => ['label' => 'Probation End',    'column' => 'probation_end_date',         'type' => 'date'],
        'departure_date'             => ['label' => 'Departure Date',   'column' => 'departure_date',             'type' => 'date'],
        'visa_expire'                => ['label' => 'Visa Expiry',      'column' => 'visa_expire',                'type' => 'date'],
        'work_permit_expiration_date'=> ['label' => 'Permit Expiry',    'column' => 'work_permit_expiration_date','type' => 'date'],
        'wage'                       => ['label' => 'Wage',             'column' => 'wage',                       'type' => 'number'],
        'payment_method'             => ['label' => 'Payment Method',   'column' => 'payment_method',             'type' => 'string'],
        'active'          => ['label' => 'Active',          'column' => 'active',          'type' => 'boolean'],
        'scientific_title'=> ['label' => 'Scientific Title', 'column' => 'scientific_title', 'type' => 'string'],
        // Relations
        'company_id'           => ['label' => 'Company',          'column' => 'company_id',          'type' => 'relation', 'relation' => ['table' => 'companies',             'field' => 'name']],
        'department_id'        => ['label' => 'Department',        'column' => 'department_id',        'type' => 'relation', 'relation' => ['table' => 'hr_departments',        'field' => 'name']],
        'job_id'               => ['label' => 'Job Position',      'column' => 'job_id',               'type' => 'relation', 'relation' => ['table' => 'hr_jobs',               'field' => 'name']],
        'work_location_id'     => ['label' => 'Work Location',     'column' => 'work_location_id',     'type' => 'relation', 'relation' => ['table' => 'hr_work_locations',     'field' => 'name']],
        'resource_calendar_id' => ['label' => 'Working Schedule',  'column' => 'resource_calendar_id', 'type' => 'relation', 'relation' => ['table' => 'hr_resource_calendars', 'field' => 'name']],
        'parent_id'            => ['label' => 'Manager',           'column' => 'parent_id',            'type' => 'relation', 'relation' => ['table' => 'hr_employees',          'field' => 'name']],
        // Timestamps
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
        'updated_at' => ['label' => 'Updated on', 'column' => 'updated_at', 'type' => 'datetime'],
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
        'scientific_title',
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
        'visa_expire'                 => 'date',
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

    public function certificates(): HasMany
    {
        return $this->hasMany(EmployeeCertificate::class, 'employee_id');
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(EmployeePosition::class, 'hr_position_employees', 'employee_id', 'position_id');
    }

    public function currentPosition(): ?EmployeePosition
    {
        return $this->positions()
            ->where('affective_date', '<=', now()->toDateString())
            ->orderByDesc('affective_date')
            ->first();
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
