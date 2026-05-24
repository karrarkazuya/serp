<?php

/*
|--------------------------------------------------------------------------
| Exportable Models Registry
|--------------------------------------------------------------------------
|
| Defines the whitelist of models that can be exported via the generic
| POST /export endpoint. Each entry specifies:
|
|   class           — fully-qualified Eloquent model class
|   permission      — permission key checked before exporting
|   company_scoped  — whether to filter by active company IDs
|   filename        — default download filename (without extension)
|   fields          — allowed export columns [key, label, column]
|
| Only columns listed in `fields` can ever be exported. The ExportController
| validates every requested field key against this list.
|
*/

return [

    'contacts' => [
        'class'          => \App\Models\Contacts\Contact::class,
        'permission'     => 'contacts.export',
        'company_scoped' => true,
        'filename'       => 'contacts',
        'fields'         => [
            ['key' => 'id',           'label' => 'ID',           'column' => 'id'],
            ['key' => 'name',         'label' => 'Name',         'column' => 'name'],
            ['key' => 'contact_type', 'label' => 'Type',         'column' => 'contact_type'],
            ['key' => 'email',        'label' => 'Email',        'column' => 'email'],
            ['key' => 'company_name', 'label' => 'Company Name', 'column' => 'company_name'],
            ['key' => 'job_position', 'label' => 'Job Position', 'column' => 'job_position'],
            ['key' => 'website',      'label' => 'Website',      'column' => 'website'],
            ['key' => 'street',       'label' => 'Street',       'column' => 'street'],
            ['key' => 'city',         'label' => 'City',         'column' => 'city'],
            ['key' => 'state',        'label' => 'State',        'column' => 'state'],
            ['key' => 'country',      'label' => 'Country',      'column' => 'country'],
            ['key' => 'zip',          'label' => 'ZIP',          'column' => 'zip'],
            ['key' => 'tax_id',       'label' => 'Tax ID',       'column' => 'tax_id'],
            ['key' => 'active',       'label' => 'Active',       'column' => 'active'],
            ['key' => 'created_at',   'label' => 'Created On',   'column' => 'created_at'],
            ['key' => 'updated_at',   'label' => 'Updated On',   'column' => 'updated_at'],
        ],
    ],

    'employees' => [
        'class'          => \App\Models\Employees\Employee::class,
        'permission'     => 'employees.export',
        'company_scoped' => true,
        'filename'       => 'employees',
        'fields'         => [
            ['key' => 'id',                'label' => 'ID',                  'column' => 'id'],
            ['key' => 'employee_code',     'label' => 'Employee Code',       'column' => 'employee_code'],
            ['key' => 'name',              'label' => 'Name',                'column' => 'name'],
            ['key' => 'name_ar',           'label' => 'Arabic Name',         'column' => 'name_ar'],
            ['key' => 'name_en',           'label' => 'English Name',        'column' => 'name_en'],
            ['key' => 'job_title',         'label' => 'Job Title',           'column' => 'job_title'],
            ['key' => 'work_email',        'label' => 'Work Email',          'column' => 'work_email'],
            ['key' => 'work_phone',        'label' => 'Work Phone',          'column' => 'work_phone'],
            ['key' => 'work_mobile',       'label' => 'Work Mobile',         'column' => 'work_mobile'],
            ['key' => 'employment_status', 'label' => 'Employment Status',   'column' => 'employment_status'],
            ['key' => 'hire_date',         'label' => 'Hire Date',           'column' => 'hire_date'],
            ['key' => 'gender',            'label' => 'Gender',              'column' => 'gender'],
            ['key' => 'birthday',          'label' => 'Date of Birth',       'column' => 'birthday'],
            ['key' => 'nationality',       'label' => 'Nationality',         'column' => 'nationality'],
            ['key' => 'marital_status',    'label' => 'Marital Status',      'column' => 'marital_status'],
            ['key' => 'identification_id', 'label' => 'ID Number',           'column' => 'identification_id'],
            ['key' => 'passport_id',       'label' => 'Passport No.',        'column' => 'passport_id'],
            ['key' => 'ssnid',             'label' => 'SSN No.',             'column' => 'ssnid'],
            ['key' => 'visa_no',           'label' => 'Visa Number',         'column' => 'visa_no'],
            ['key' => 'visa_expire',       'label' => 'Visa Expiry',         'column' => 'visa_expire'],
            ['key' => 'work_permit_no',    'label' => 'Work Permit No.',     'column' => 'work_permit_no'],
            ['key' => 'certificate_level', 'label' => 'Certificate Level',   'column' => 'certificate_level'],
            ['key' => 'departure_date',    'label' => 'Departure Date',      'column' => 'departure_date'],
            ['key' => 'active',            'label' => 'Active',              'column' => 'active'],
            ['key' => 'created_at',        'label' => 'Created On',          'column' => 'created_at'],
            ['key' => 'updated_at',        'label' => 'Updated On',          'column' => 'updated_at'],
        ],
    ],

    'workflow.tickets' => [
        'class'          => \App\Models\Workflow\Ticket::class,
        'permission'     => 'workflow.tickets.export',
        'company_scoped' => true,
        'filename'       => 'tickets',
        'extra_params'   => [
            'state' => 'state',
        ],
        'fields'         => [
            ['key' => 'id',               'label' => 'ID',               'column' => 'id'],
            ['key' => 'name',             'label' => 'Name',             'column' => 'name'],
            ['key' => 'description',      'label' => 'Description',      'column' => 'description'],
            ['key' => 'state',            'label' => 'State',            'column' => 'state'],
            ['key' => 'priority',         'label' => 'Priority',         'column' => 'priority'],
            ['key' => 'resolve_deadline', 'label' => 'Deadline',         'column' => 'resolve_deadline'],
            ['key' => 'resolve_duration', 'label' => 'Duration (hrs)',   'column' => 'resolve_duration'],
            ['key' => 'active',           'label' => 'Active',           'column' => 'active'],
            ['key' => 'created_at',       'label' => 'Created On',       'column' => 'created_at'],
            ['key' => 'updated_at',       'label' => 'Updated On',       'column' => 'updated_at'],
        ],
    ],

    'accounting.moves' => [
        'class'          => \App\Models\Accounting\AccountMove::class,
        'permission'     => 'accounting.export',
        'company_scoped' => true,
        'filename'       => 'journal-entries',
        'extra_params'   => [
            'state'      => 'state',
            'journal_id' => 'journal_id',
        ],
        'fields'         => [
            ['key' => 'id',            'label' => 'ID',            'column' => 'id'],
            ['key' => 'name',          'label' => 'Number',        'column' => 'name'],
            ['key' => 'date',          'label' => 'Date',          'column' => 'date'],
            ['key' => 'ref',           'label' => 'Reference',     'column' => 'ref'],
            ['key' => 'state',         'label' => 'State',         'column' => 'state'],
            ['key' => 'payment_state', 'label' => 'Payment State', 'column' => 'payment_state'],
            ['key' => 'currency',      'label' => 'Currency',      'column' => 'currency'],
            ['key' => 'amount_total',  'label' => 'Amount',        'column' => 'amount_total'],
            ['key' => 'narration',     'label' => 'Notes',         'column' => 'narration'],
            ['key' => 'invoice_date_due',   'label' => 'Due Date',        'column' => 'invoice_date_due'],
            ['key' => 'invoice_origin',     'label' => 'Source Document', 'column' => 'invoice_origin'],
            ['key' => 'created_at',    'label' => 'Created On',    'column' => 'created_at'],
            ['key' => 'updated_at',    'label' => 'Updated On',    'column' => 'updated_at'],
        ],
    ],

    'employees.bonuses' => [
        'class'          => \App\Models\Employees\EmployeeBonus::class,
        'permission'     => 'employees.export',
        'company_scoped' => false,
        'filename'       => 'bonuses',
        'fields'         => [
            ['key' => 'id',                        'label' => 'ID',                       'column' => 'id'],
            ['key' => 'name',                      'label' => 'Name',                     'column' => 'name'],
            ['key' => 'document_type',             'label' => 'Document Type',            'column' => 'document_type'],
            ['key' => 'document_number',           'label' => 'Document Number',          'column' => 'document_number'],
            ['key' => 'issued_by',                 'label' => 'Issued By',                'column' => 'issued_by'],
            ['key' => 'organizational_structure',  'label' => 'Organizational Structure', 'column' => 'organizational_structure'],
            ['key' => 'assignment_type',           'label' => 'Assignment Type',          'column' => 'assignment_type'],
            ['key' => 'data_status',               'label' => 'Data Status',              'column' => 'data_status'],
            ['key' => 'financial_specialization',  'label' => 'Financial Specialization', 'column' => 'financial_specialization'],
            ['key' => 'affective_date',            'label' => 'Affective Date',           'column' => 'affective_date'],
            ['key' => 'issue_date',                'label' => 'Issue Date',               'column' => 'issue_date'],
            ['key' => 'expiry_date',               'label' => 'Expiry Date',              'column' => 'expiry_date'],
            ['key' => 'active',                    'label' => 'Active',                   'column' => 'active'],
            ['key' => 'created_at',                'label' => 'Created On',               'column' => 'created_at'],
        ],
    ],

    'employees.appreciations' => [
        'class'          => \App\Models\Employees\EmployeeAppreciation::class,
        'permission'     => 'employees.export',
        'company_scoped' => false,
        'filename'       => 'appreciations',
        'fields'         => [
            ['key' => 'id',                        'label' => 'ID',                       'column' => 'id'],
            ['key' => 'name',                      'label' => 'Name',                     'column' => 'name'],
            ['key' => 'document_type',             'label' => 'Document Type',            'column' => 'document_type'],
            ['key' => 'document_number',           'label' => 'Document Number',          'column' => 'document_number'],
            ['key' => 'issued_by',                 'label' => 'Issued By',                'column' => 'issued_by'],
            ['key' => 'organizational_structure',  'label' => 'Organizational Structure', 'column' => 'organizational_structure'],
            ['key' => 'assignment_type',           'label' => 'Assignment Type',          'column' => 'assignment_type'],
            ['key' => 'data_status',               'label' => 'Data Status',              'column' => 'data_status'],
            ['key' => 'financial_specialization',  'label' => 'Financial Specialization', 'column' => 'financial_specialization'],
            ['key' => 'affective_date',            'label' => 'Affective Date',           'column' => 'affective_date'],
            ['key' => 'issue_date',                'label' => 'Issue Date',               'column' => 'issue_date'],
            ['key' => 'expiry_date',               'label' => 'Expiry Date',              'column' => 'expiry_date'],
            ['key' => 'active',                    'label' => 'Active',                   'column' => 'active'],
            ['key' => 'created_at',                'label' => 'Created On',               'column' => 'created_at'],
        ],
    ],

    'employees.sanctions' => [
        'class'          => \App\Models\Employees\EmployeeSanction::class,
        'permission'     => 'employees.export',
        'company_scoped' => false,
        'filename'       => 'sanctions',
        'fields'         => [
            ['key' => 'id',                        'label' => 'ID',                       'column' => 'id'],
            ['key' => 'name',                      'label' => 'Name',                     'column' => 'name'],
            ['key' => 'document_type',             'label' => 'Document Type',            'column' => 'document_type'],
            ['key' => 'document_number',           'label' => 'Document Number',          'column' => 'document_number'],
            ['key' => 'issued_by',                 'label' => 'Issued By',                'column' => 'issued_by'],
            ['key' => 'organizational_structure',  'label' => 'Organizational Structure', 'column' => 'organizational_structure'],
            ['key' => 'assignment_type',           'label' => 'Assignment Type',          'column' => 'assignment_type'],
            ['key' => 'data_status',               'label' => 'Data Status',              'column' => 'data_status'],
            ['key' => 'financial_specialization',  'label' => 'Financial Specialization', 'column' => 'financial_specialization'],
            ['key' => 'affective_date',            'label' => 'Affective Date',           'column' => 'affective_date'],
            ['key' => 'issue_date',                'label' => 'Issue Date',               'column' => 'issue_date'],
            ['key' => 'expiry_date',               'label' => 'Expiry Date',              'column' => 'expiry_date'],
            ['key' => 'active',                    'label' => 'Active',                   'column' => 'active'],
            ['key' => 'created_at',                'label' => 'Created On',               'column' => 'created_at'],
        ],
    ],

    'employees.rewards' => [
        'class'          => \App\Models\Employees\EmployeeReward::class,
        'permission'     => 'employees.export',
        'company_scoped' => false,
        'filename'       => 'rewards',
        'fields'         => [
            ['key' => 'id',                        'label' => 'ID',                       'column' => 'id'],
            ['key' => 'name',                      'label' => 'Name',                     'column' => 'name'],
            ['key' => 'document_type',             'label' => 'Document Type',            'column' => 'document_type'],
            ['key' => 'document_number',           'label' => 'Document Number',          'column' => 'document_number'],
            ['key' => 'issued_by',                 'label' => 'Issued By',                'column' => 'issued_by'],
            ['key' => 'organizational_structure',  'label' => 'Organizational Structure', 'column' => 'organizational_structure'],
            ['key' => 'assignment_type',           'label' => 'Assignment Type',          'column' => 'assignment_type'],
            ['key' => 'data_status',               'label' => 'Data Status',              'column' => 'data_status'],
            ['key' => 'financial_specialization',  'label' => 'Financial Specialization', 'column' => 'financial_specialization'],
            ['key' => 'affective_date',            'label' => 'Affective Date',           'column' => 'affective_date'],
            ['key' => 'issue_date',                'label' => 'Issue Date',               'column' => 'issue_date'],
            ['key' => 'expiry_date',               'label' => 'Expiry Date',              'column' => 'expiry_date'],
            ['key' => 'active',                    'label' => 'Active',                   'column' => 'active'],
            ['key' => 'created_at',                'label' => 'Created On',               'column' => 'created_at'],
        ],
    ],

    'employees.job-grades' => [
        'class'          => \App\Models\Employees\EmployeeJobGrade::class,
        'permission'     => 'employees.export',
        'company_scoped' => false,
        'filename'       => 'job-grades',
        'fields'         => [
            ['key' => 'id',                        'label' => 'ID',                       'column' => 'id'],
            ['key' => 'organizational_structure',  'label' => 'Organizational Structure', 'column' => 'organizational_structure'],
            ['key' => 'assignment_type',           'label' => 'Assignment Type',          'column' => 'assignment_type'],
            ['key' => 'data_status',               'label' => 'Data Status',              'column' => 'data_status'],
            ['key' => 'financial_specialization',  'label' => 'Financial Specialization', 'column' => 'financial_specialization'],
            ['key' => 'affective_date',            'label' => 'Affective Date',           'column' => 'affective_date'],
            ['key' => 'active',                    'label' => 'Active',                   'column' => 'active'],
            ['key' => 'created_at',                'label' => 'Created On',               'column' => 'created_at'],
        ],
    ],

    'employees.certificates' => [
        'class'          => \App\Models\Employees\EmployeeCertificate::class,
        'permission'     => 'employees.export',
        'company_scoped' => false,
        'filename'       => 'certificates',
        'fields'         => [
            ['key' => 'id',                       'label' => 'ID',                      'column' => 'id'],
            ['key' => 'certificate_type',         'label' => 'Certificate Type',        'column' => 'certificate_type'],
            ['key' => 'study_type',               'label' => 'Study Type',              'column' => 'study_type'],
            ['key' => 'issuing_institution',      'label' => 'Issuing Institution',     'column' => 'issuing_institution'],
            ['key' => 'country',                  'label' => 'Country',                 'column' => 'country'],
            ['key' => 'data_status',              'label' => 'Data Status',             'column' => 'data_status'],
            ['key' => 'specialization_type',      'label' => 'Specialization Type',     'column' => 'specialization_type'],
            ['key' => 'financial_specialization', 'label' => 'Financial Specialization','column' => 'financial_specialization'],
            ['key' => 'graduate_date',            'label' => 'Graduate Date',           'column' => 'graduate_date'],
            ['key' => 'affective_date',           'label' => 'Affective Date',          'column' => 'affective_date'],
            ['key' => 'active',                   'label' => 'Active',                  'column' => 'active'],
            ['key' => 'created_at',               'label' => 'Created On',              'column' => 'created_at'],
        ],
    ],

    'users' => [
        'class'          => \App\Models\User::class,
        'permission'     => 'users.export',
        'company_scoped' => false,
        'filename'       => 'users',
        'fields'         => [
            ['key' => 'id',           'label' => 'ID',           'column' => 'id'],
            ['key' => 'name',         'label' => 'Name',         'column' => 'name'],
            ['key' => 'email',        'label' => 'Email',        'column' => 'email'],
            ['key' => 'job_position', 'label' => 'Job Position', 'column' => 'job_position'],
            ['key' => 'active',       'label' => 'Active',       'column' => 'active'],
            ['key' => 'created_at',   'label' => 'Created On',   'column' => 'created_at'],
            ['key' => 'updated_at',   'label' => 'Updated On',   'column' => 'updated_at'],
        ],
    ],

];
