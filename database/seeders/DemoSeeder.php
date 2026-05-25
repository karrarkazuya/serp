<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Contacts\Contact;
use App\Models\Contacts\Tag;
use App\Models\Employees\Attendance;
use App\Models\Employees\Contract;
use App\Models\Employees\Department;
use App\Models\Employees\DepartureReason;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeAppreciation;
use App\Models\Employees\EmployeeBonus;
use App\Models\Employees\EmployeeCategory;
use App\Models\Employees\EmployeeJobGrade;
use App\Models\Employees\EmployeeReward;
use App\Models\Employees\EmployeeSanction;
use App\Models\Employees\EmployeeSkill;
use App\Models\Employees\Job;
use App\Models\Employees\ResourceCalendar;
use App\Models\Employees\Skill;
use App\Models\Employees\SkillLevel;
use App\Models\Employees\SkillType;
use App\Models\Employees\WorkLocation;
use App\Models\Inventory\Location;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use App\Models\Settings\Company;
use App\Models\User;
use App\Models\Workflow\Group;
use App\Models\Workflow\Manager;
use App\Models\Workflow\ProcedureStep;
use App\Models\Workflow\ProcedureTemplate;
use App\Models\Workflow\TicketTemplate;
use App\Models\Workflow\WorkflowTemplateInput;
use App\Models\Workflow\WorkflowUser;
use App\Services\Accounting\AccountingService;
use App\Services\Employees\AttendanceService;
use App\Services\Inventory\PickingService;
use App\Services\Inventory\ProductService as InventoryProductService;
use App\Services\Inventory\ScrapService;
use App\Services\Inventory\WarehouseService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCompanies();
        $this->seedTags();
        $this->seedContacts();
        $this->seedWorkflow();
        $this->seedEmployees();
        $this->seedAccounting();
        $this->seedInventory();
    }

    // ── Companies ─────────────────────────────────────────────────────────────

    private function seedCompanies(): void
    {
        $admin     = User::where('email', 'admin@example.com')->first();
        $basicUser = User::where('email', 'user@example.com')->first();

        $rows = [
            [
                'name'     => 'Acme Holdings',
                'email'    => 'info@acme-holdings.com',
                'phone'    => '+1 555-100-0010',
                'website'  => 'https://acme-holdings.com',
                'street'   => '1 Corporate Plaza',
                'city'     => 'New York',
                'state'    => 'NY',
                'country'  => 'United States',
                'zip'      => '10001',
                'currency' => 'USD',
                'active'   => true,
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ],
            [
                'name'     => 'TechStart Europe',
                'email'    => 'hello@techstart.eu',
                'phone'    => '+44 20 9000 1234',
                'website'  => 'https://techstart.eu',
                'street'   => '10 Silicon Way',
                'city'     => 'London',
                'country'  => 'United Kingdom',
                'zip'      => 'EC1A 1BB',
                'currency' => 'GBP',
                'active'   => true,
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ],
            [
                'name'     => 'Gulf Operations LLC',
                'email'    => 'ops@gulf-ops.ae',
                'phone'    => '+971 4 123 4567',
                'city'     => 'Dubai',
                'country'  => 'UAE',
                'currency' => 'AED',
                'active'   => true,
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ],
        ];

        $companies = [];
        foreach ($rows as $data) {
            $company = Company::updateOrCreate(['name' => $data['name']], $data);
            $company->logMessage('Company created.', 'log');
            $companies[] = $company;
        }

        [$acme, $techStart, $gulf] = $companies;

        if ($admin) {
            $admin->companies()->syncWithoutDetaching([$acme->id, $techStart->id, $gulf->id]);
            $admin->update(['company_id' => $acme->id]);
        }

        if ($basicUser) {
            $basicUser->companies()->syncWithoutDetaching([$acme->id, $techStart->id]);
            $basicUser->update(['company_id' => $acme->id]);
        }
    }

    // ── Contact Tags ──────────────────────────────────────────────────────────

    private function seedTags(): void
    {
        $tags = [
            ['name' => 'Customer',  'color' => '#10B981'],
            ['name' => 'Supplier',  'color' => '#3B82F6'],
            ['name' => 'Partner',   'color' => '#8B5CF6'],
            ['name' => 'VIP',       'color' => '#F59E0B'],
            ['name' => 'Lead',      'color' => '#EF4444'],
            ['name' => 'Prospect',  'color' => '#F97316'],
            ['name' => 'Archived',  'color' => '#6B7280'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], $tag);
        }
    }

    // ── Contacts ──────────────────────────────────────────────────────────────

    private function seedContacts(): void
    {
        $faker     = Faker::create();
        $admin     = User::where('email', 'admin@example.com')->first();
        $acme      = Company::where('name', 'Acme Holdings')->first();
        $techStart = Company::where('name', 'TechStart Europe')->first();
        $gulf      = Company::where('name', 'Gulf Operations LLC')->first();
        $companies = collect([$acme, $techStart, $gulf])->filter();
        $tags      = Tag::all();

        $fixed = [
            [
                'name'         => 'Acme Corporation',
                'contact_type' => 'company',
                'email'        => 'info@acme.com',
                'phones'       => ['+1 555-100-0001'],
                'website'      => 'https://acme.com',
                'street'       => '123 Industrial Ave',
                'city'         => 'New York',
                'state'        => 'NY',
                'country'      => 'United States',
                'zip'          => '10001',
                'active'       => true,
                'company_id'   => $acme?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
            [
                'name'         => 'Jane Smith',
                'company_name' => 'Acme Corporation',
                'contact_type' => 'individual',
                'email'        => 'jane.smith@acme.com',
                'phones'       => ['+1 555-100-0002', '+1 555-200-0002'],
                'job_position' => 'Chief Executive Officer',
                'city'         => 'New York',
                'state'        => 'NY',
                'country'      => 'United States',
                'active'       => true,
                'company_id'   => $acme?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
            [
                'name'         => 'TechStart Ltd',
                'contact_type' => 'company',
                'email'        => 'hello@techstart.io',
                'phones'       => ['+44 20 1234 5678'],
                'website'      => 'https://techstart.io',
                'street'       => '10 Silicon Way',
                'city'         => 'London',
                'country'      => 'United Kingdom',
                'zip'          => 'EC1A 1BB',
                'active'       => true,
                'company_id'   => $techStart?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
            [
                'name'         => 'Mohammed Al-Rashid',
                'contact_type' => 'individual',
                'email'        => 'm.alrashid@example.com',
                'phones'       => ['+971 50 123 4567'],
                'job_position' => 'Sales Director',
                'city'         => 'Dubai',
                'country'      => 'UAE',
                'active'       => true,
                'company_id'   => $gulf?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
            [
                'name'         => 'Old Supplier Co',
                'contact_type' => 'company',
                'email'        => 'contact@oldsupplier.com',
                'city'         => 'Chicago',
                'state'        => 'IL',
                'country'      => 'United States',
                'active'       => false,
                'notes'        => 'Archived — contract ended in 2024.',
                'company_id'   => $acme?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ],
        ];

        foreach ($fixed as $data) {
            $phones = $data['phones'] ?? [];
            unset($data['phones']);
            $contact = Contact::updateOrCreate(
                ['name' => $data['name'], 'email' => $data['email'] ?? null],
                $data
            );
            foreach ($phones as $phone) {
                $contact->phones()->firstOrCreate(['phone' => $phone]);
            }
            $contact->logMessage('Contact created.', 'log');
        }

        $jobTitles = [
            'Account Manager', 'Sales Representative', 'Marketing Manager',
            'Product Manager', 'Software Engineer', 'Financial Analyst',
            'Operations Manager', 'Business Development Manager', 'HR Manager',
            'Project Manager', 'Director of Sales', 'CTO', 'CFO', 'COO',
            'Procurement Officer', 'Supply Chain Manager', 'Legal Counsel',
            'Customer Success Manager', 'UX Designer', 'Data Analyst',
        ];

        for ($i = 0; $i < 195; $i++) {
            $isCompany = $faker->boolean(25);
            $company   = $companies->isNotEmpty() ? $companies->random() : null;

            $contact = Contact::create([
                'name'         => $isCompany ? $faker->company : $faker->name,
                'contact_type' => $isCompany ? 'company' : 'individual',
                'email'        => $faker->unique()->safeEmail,
                'job_position' => !$isCompany && $faker->boolean(65) ? $faker->randomElement($jobTitles) : null,
                'company_name' => !$isCompany && $faker->boolean(45) ? $faker->company : null,
                'website'      => $isCompany && $faker->boolean(55) ? 'https://www.' . $faker->domainName : null,
                'street'       => $faker->boolean(65) ? $faker->streetAddress : null,
                'city'         => $faker->boolean(65) ? $faker->city : null,
                'state'        => $faker->boolean(60) ? $faker->state : null,
                'country'      => $faker->boolean(65) ? $faker->country : null,
                'zip'          => $faker->boolean(60) ? $faker->postcode : null,
                'tax_id'       => $isCompany && $faker->boolean(35) ? $faker->numerify('##-#######') : null,
                'notes'        => $faker->boolean(15) ? $faker->sentences(rand(1, 3), true) : null,
                'active'       => $faker->boolean(88),
                'company_id'   => $company?->id,
                'created_by'   => $admin?->id,
                'updated_by'   => $admin?->id,
            ]);

            if ($faker->boolean(75)) {
                $contact->phones()->create(['phone' => $faker->phoneNumber]);
            }
            if (!$isCompany && $faker->boolean(55)) {
                $contact->phones()->create(['phone' => $faker->phoneNumber]);
            }

            if ($tags->isNotEmpty() && $faker->boolean(40)) {
                $contact->tags()->sync(
                    $tags->random(rand(1, min(2, $tags->count())))->pluck('id')->toArray()
                );
            }
        }
    }

    // ── Workflow Config ───────────────────────────────────────────────────────

    private function seedWorkflow(): void
    {
        $admin   = User::where('email', 'admin@example.com')->first();
        $company = Company::first();

        $allGroup  = Group::updateOrCreate(['name' => 'All Staff'],  ['active' => true]);
        $mgmtGroup = Group::updateOrCreate(['name' => 'Management'], ['active' => true]);

        $itDept = Department::updateOrCreate(
            ['name' => 'IT'],
            ['company_id' => $company?->id, 'active' => true]
        );
        $hrDept = Department::updateOrCreate(
            ['name' => 'Human Resources'],
            ['company_id' => $company?->id, 'active' => true]
        );

        if ($admin) {
            $wu = WorkflowUser::updateOrCreate(
                ['user_id' => $admin->id],
                ['default_department_id' => $itDept->id, 'active' => true]
            );
            $wu->groups()->syncWithoutDetaching([$allGroup->id, $mgmtGroup->id]);
            $wu->assignableDepartments()->syncWithoutDetaching([$itDept->id, $hrDept->id]);

            $manager = Manager::firstOrCreate(['workflow_user_id' => $wu->id], ['active' => true]);
            $manager->departments()->syncWithoutDetaching([$itDept->id, $hrDept->id]);
        }

        $ticketTpl = TicketTemplate::updateOrCreate(
            ['name' => 'IT Support Request'],
            [
                'description'           => 'General IT support ticket.',
                'default_group_id'      => $allGroup->id,
                'default_department_id' => $itDept->id,
                'resolve_max_duration'  => 48,
                'enabled'               => true,
                'active'                => true,
            ]
        );
        $ticketTpl->departments()->syncWithoutDetaching([$itDept->id, $hrDept->id]);

        WorkflowTemplateInput::firstOrCreate(
            ['owner_id' => $ticketTpl->id, 'owner_type' => 'ticket_template', 'name' => 'Problem Description'],
            ['type' => 'char', 'is_required' => true, 'sort_order' => 1]
        );

        $catInput = WorkflowTemplateInput::firstOrCreate(
            ['owner_id' => $ticketTpl->id, 'owner_type' => 'ticket_template', 'name' => 'Category'],
            ['type' => 'select', 'is_required' => true, 'sort_order' => 2]
        );
        foreach (['Hardware', 'Software', 'Network', 'Account'] as $opt) {
            $catInput->options()->firstOrCreate(['name' => $opt]);
        }

        $procTpl = ProcedureTemplate::updateOrCreate(
            ['name' => 'Employee Onboarding'],
            [
                'description'          => 'Standard new employee onboarding procedure.',
                'default_group_id'     => $mgmtGroup->id,
                'creator_see_tasks'    => true,
                'enabled'              => true,
                'active'               => true,
            ]
        );
        $procTpl->departments()->syncWithoutDetaching([$hrDept->id]);

        if ($procTpl->steps()->count() === 0) {
            $step1 = ProcedureStep::create([
                'procedure_template_id' => $procTpl->id,
                'name'                  => 'Prepare Workstation',
                'description'           => 'Set up laptop, accounts, and equipment.',
                'default_department_id' => $itDept->id,
                'enabled'               => true,
            ]);
            WorkflowTemplateInput::create([
                'owner_id'    => $step1->id,
                'owner_type'  => 'procedure_step',
                'name'        => 'Equipment Serial Number',
                'type'        => 'char',
                'is_required' => true,
                'sort_order'  => 1,
            ]);

            $step2 = ProcedureStep::create([
                'procedure_template_id' => $procTpl->id,
                'name'                  => 'HR Documentation',
                'description'           => 'Collect signed contracts and ID documents.',
                'default_department_id' => $hrDept->id,
                'enabled'               => true,
            ]);
            $docInput = WorkflowTemplateInput::create([
                'owner_id'    => $step2->id,
                'owner_type'  => 'procedure_step',
                'name'        => 'Documents Status',
                'type'        => 'select',
                'is_required' => true,
                'sort_order'  => 1,
            ]);
            foreach (['Complete', 'Partial', 'Pending'] as $opt) {
                $docInput->options()->create(['name' => $opt]);
            }

            $step3 = ProcedureStep::create([
                'procedure_template_id' => $procTpl->id,
                'name'                  => 'Orientation Complete',
                'description'           => 'Confirm employee completed orientation session.',
                'default_department_id' => $hrDept->id,
                'enabled'               => true,
            ]);
            WorkflowTemplateInput::create([
                'owner_id'    => $step3->id,
                'owner_type'  => 'procedure_step',
                'name'        => 'Completion Notes',
                'type'        => 'char',
                'is_required' => false,
                'sort_order'  => 1,
            ]);

            $step1->nextSteps()->attach($step2->id);
            $step2->nextSteps()->attach($step3->id);
        }
    }

    // ── Employees ─────────────────────────────────────────────────────────────

    private function seedEmployees(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found — skipping employee seed.');
            return;
        }

        // Departure reasons
        foreach (['Resigned', 'Contract End', 'Fired', 'Retired', 'Mutual Agreement'] as $name) {
            DepartureReason::firstOrCreate(['name' => $name], ['active' => true]);
        }

        // Work locations
        $locations = [];
        foreach (['Head Office — Baghdad', 'Remote', 'Branch — Basra', 'Branch — Erbil'] as $name) {
            $locations[$name] = WorkLocation::firstOrCreate(
                ['name' => $name, 'company_id' => $company->id],
                ['active' => true]
            );
        }

        // Resource calendars
        $calStandard = $this->makeCalendar($company->id, 'Standard 40 Hours (Sun–Thu)', 8.0, [
            [1, 'morning', 8.0, 12.0], [1, 'afternoon', 13.0, 17.0],
            [2, 'morning', 8.0, 12.0], [2, 'afternoon', 13.0, 17.0],
            [3, 'morning', 8.0, 12.0], [3, 'afternoon', 13.0, 17.0],
            [4, 'morning', 8.0, 12.0], [4, 'afternoon', 13.0, 17.0],
            [5, 'morning', 8.0, 12.0], [5, 'afternoon', 13.0, 17.0],
        ]);

        $calMorning = $this->makeCalendar($company->id, 'Morning Shift (Sun–Thu 8:00–14:00)', 6.0, [
            [1, 'morning', 8.0, 14.0], [2, 'morning', 8.0, 14.0],
            [3, 'morning', 8.0, 14.0], [4, 'morning', 8.0, 14.0],
            [5, 'morning', 8.0, 14.0],
        ]);

        $calFull = $this->makeCalendar($company->id, 'Full Week (Sat–Thu)', 8.0, [
            [0, 'morning', 8.5, 12.0], [0, 'afternoon', 13.0, 17.5],
            [1, 'morning', 8.5, 12.0], [1, 'afternoon', 13.0, 17.5],
            [2, 'morning', 8.5, 12.0], [2, 'afternoon', 13.0, 17.5],
            [3, 'morning', 8.5, 12.0], [3, 'afternoon', 13.0, 17.5],
            [4, 'morning', 8.5, 12.0], [4, 'afternoon', 13.0, 17.5],
            [5, 'morning', 8.5, 12.0], [5, 'afternoon', 13.0, 17.5],
        ]);

        // Departments
        $deptExec  = Department::firstOrCreate(['name' => 'Executive Office',     'company_id' => $company->id], ['active' => true]);
        $deptSoft  = Department::firstOrCreate(['name' => 'Software Development', 'company_id' => $company->id], ['active' => true, 'parent_id' => $deptExec->id]);
        $deptDigit = Department::firstOrCreate(['name' => 'Digital Solutions',    'company_id' => $company->id], ['active' => true, 'parent_id' => $deptSoft->id]);
        $deptHR    = Department::firstOrCreate(['name' => 'Human Resources',      'company_id' => $company->id], ['active' => true, 'parent_id' => $deptExec->id]);
        $deptFin   = Department::firstOrCreate(['name' => 'Finance',              'company_id' => $company->id], ['active' => true, 'parent_id' => $deptExec->id]);
        $deptOps   = Department::firstOrCreate(['name' => 'Operations',           'company_id' => $company->id], ['active' => true, 'parent_id' => $deptExec->id]);
        $deptSupp  = Department::firstOrCreate(['name' => 'Technical Support',    'company_id' => $company->id], ['active' => true, 'parent_id' => $deptOps->id]);

        // Jobs
        $jobs = [];
        foreach ([
            'CEO', 'Head of Software Solutions',
            'Senior Software Developer', 'Full Stack Developer', 'Backend Developer',
            'Database Administrator', 'UI/UX Designer',
            'HR Manager', 'HR Specialist',
            'Finance Manager', 'Financial Analyst',
            'Operations Manager', 'Technical Support Engineer',
        ] as $title) {
            $jobs[$title] = Job::firstOrCreate(['name' => $title, 'company_id' => $company->id], ['active' => true]);
        }

        // Categories
        $cats = [];
        foreach ([
            ['Management',     '#714B67'],
            ['Technical',      '#1f66d1'],
            ['Administrative', '#13bfd7'],
            ['Finance',        '#f28b2e'],
            ['Senior Staff',   '#5a2ca0'],
        ] as [$name, $color]) {
            $cats[$name] = EmployeeCategory::firstOrCreate(['name' => $name], ['color' => $color, 'active' => true]);
        }

        // Skills
        [$skillTypes, $skillLevels] = $this->seedSkillTypes($company->id);

        // Hierarchy — Level 1
        $ceo = $this->makeEmployee(['name' => 'رفل حسين', 'name_en' => 'Rafl Hussein', 'family_name' => 'Hussein', 'job_title' => 'Chief Executive Officer', 'work_email' => 'rafl.hussein@company.iq', 'work_phone' => '+964-770-001-0001', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1975-03-15', 'hire_date' => '2010-01-01', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 3, 'certificate_level' => 'master', 'study_field' => 'Business Administration', 'study_school' => 'University of Baghdad', 'timezone' => 'Asia/Baghdad'], $company, $deptExec, $jobs['CEO'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management'], $cats['Senior Staff']]);

        // Level 2
        $cto       = $this->makeEmployee(['name' => 'كرار ستار', 'name_en' => 'Karrar Sattar', 'family_name' => 'Sattar', 'job_title' => 'Head of Software Solutions', 'work_email' => 'karrar.sattar@company.iq', 'work_phone' => '+964-770-001-0002', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1988-07-22', 'hire_date' => '2015-03-10', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 2, 'certificate_level' => 'bachelor', 'study_field' => 'Computer Science', 'study_school' => 'University of Technology', 'timezone' => 'Asia/Baghdad'], $company, $deptSoft, $jobs['Head of Software Solutions'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management'], $cats['Technical'], $cats['Senior Staff']], $ceo);
        $hrManager = $this->makeEmployee(['name' => 'سارة محمد', 'name_en' => 'Sara Mohammed', 'family_name' => 'Mohammed', 'job_title' => 'HR Manager', 'work_email' => 'sara.mohammed@company.iq', 'work_phone' => '+964-770-001-0010', 'nationality' => 'Iraqi', 'gender' => 'female', 'birthday' => '1985-11-08', 'hire_date' => '2016-06-01', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 1, 'certificate_level' => 'bachelor', 'study_field' => 'Human Resources', 'study_school' => 'Al-Mustansiriyah University', 'timezone' => 'Asia/Baghdad'], $company, $deptHR, $jobs['HR Manager'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management']], $ceo);
        $finManager = $this->makeEmployee(['name' => 'أحمد الكريم', 'name_en' => 'Ahmed Al-Kareem', 'family_name' => 'Al-Kareem', 'job_title' => 'Finance Manager', 'work_email' => 'ahmed.kareem@company.iq', 'work_phone' => '+964-770-001-0020', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1982-05-19', 'hire_date' => '2014-09-15', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 4, 'certificate_level' => 'master', 'study_field' => 'Accounting', 'study_school' => 'University of Kufa', 'timezone' => 'Asia/Baghdad'], $company, $deptFin, $jobs['Finance Manager'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management'], $cats['Finance']], $ceo);
        $opsManager = $this->makeEmployee(['name' => 'علي حسن', 'name_en' => 'Ali Hassan', 'family_name' => 'Hassan', 'job_title' => 'Operations Manager', 'work_email' => 'ali.hassan@company.iq', 'work_phone' => '+964-770-001-0030', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1980-09-02', 'hire_date' => '2013-02-20', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 3, 'certificate_level' => 'bachelor', 'study_field' => 'Engineering', 'study_school' => 'University of Basra', 'timezone' => 'Asia/Baghdad'], $company, $deptOps, $jobs['Operations Manager'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management']], $ceo);

        // Level 3
        $dev1     = $this->makeEmployee(['name' => 'حيدر علي', 'name_en' => 'Hayder Ali', 'family_name' => 'Ali', 'job_title' => 'Full Stack Developer', 'work_email' => 'hayder.ali@company.iq', 'work_phone' => '+964-770-001-0003', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1992-04-10', 'hire_date' => '2019-01-15', 'employment_status' => 'active', 'marital_status' => 'single', 'children' => 0, 'certificate_level' => 'bachelor', 'study_field' => 'Software Engineering', 'study_school' => 'University of Technology', 'timezone' => 'Asia/Baghdad'], $company, $deptDigit, $jobs['Full Stack Developer'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Technical']], $cto);
        $dev2     = $this->makeEmployee(['name' => 'زينب خالد', 'name_en' => 'Zainab Khalid', 'family_name' => 'Khalid', 'job_title' => 'Backend Developer', 'work_email' => 'zainab.khalid@company.iq', 'work_phone' => '+964-770-001-0004', 'nationality' => 'Iraqi', 'gender' => 'female', 'birthday' => '1994-08-25', 'hire_date' => '2020-03-01', 'employment_status' => 'active', 'marital_status' => 'single', 'children' => 0, 'certificate_level' => 'bachelor', 'study_field' => 'Computer Science', 'study_school' => 'University of Mosul', 'timezone' => 'Asia/Baghdad'], $company, $deptDigit, $jobs['Backend Developer'], $locations['Remote'], $calStandard, [$cats['Technical']], $cto);
        $dev3     = $this->makeEmployee(['name' => 'محمد جاسم', 'name_en' => 'Mohammed Jassim', 'family_name' => 'Jassim', 'job_title' => 'Senior Software Developer', 'work_email' => 'mohammed.jassim@company.iq', 'work_phone' => '+964-770-001-0005', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1990-12-18', 'hire_date' => '2018-07-10', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 2, 'certificate_level' => 'bachelor', 'study_field' => 'Information Technology', 'study_school' => 'Nahrain University', 'timezone' => 'Asia/Baghdad'], $company, $deptSoft, $jobs['Senior Software Developer'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Technical'], $cats['Senior Staff']], $cto);
        $dba      = $this->makeEmployee(['name' => 'لؤي عبدالله', 'name_en' => 'Luay Abdullah', 'family_name' => 'Abdullah', 'job_title' => 'Database Administrator', 'work_email' => 'luay.abdullah@company.iq', 'work_phone' => '+964-770-001-0006', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1988-02-14', 'hire_date' => '2017-04-05', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 1, 'certificate_level' => 'bachelor', 'study_field' => 'Computer Engineering', 'study_school' => 'University of Baghdad', 'timezone' => 'Asia/Baghdad'], $company, $deptSoft, $jobs['Database Administrator'], $locations['Head Office — Baghdad'], $calFull, [$cats['Technical']], $cto);
        $designer = $this->makeEmployee(['name' => 'نور الهدى', 'name_en' => 'Nour Al-Huda', 'family_name' => 'Al-Huda', 'job_title' => 'UI/UX Designer', 'work_email' => 'nour.alhuda@company.iq', 'work_phone' => '+964-770-001-0007', 'nationality' => 'Iraqi', 'gender' => 'female', 'birthday' => '1995-06-30', 'hire_date' => '2021-02-15', 'employment_status' => 'active', 'marital_status' => 'single', 'children' => 0, 'certificate_level' => 'bachelor', 'study_field' => 'Graphic Design', 'study_school' => 'Academy of Fine Arts', 'timezone' => 'Asia/Baghdad'], $company, $deptDigit, $jobs['UI/UX Designer'], $locations['Remote'], $calMorning, [$cats['Technical']], $cto);
        $hr1      = $this->makeEmployee(['name' => 'فاطمة رحيم', 'name_en' => 'Fatima Rahim', 'family_name' => 'Rahim', 'job_title' => 'HR Specialist', 'work_email' => 'fatima.rahim@company.iq', 'work_phone' => '+964-770-001-0011', 'nationality' => 'Iraqi', 'gender' => 'female', 'birthday' => '1993-09-17', 'hire_date' => '2020-08-01', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 1, 'certificate_level' => 'bachelor', 'study_field' => 'Human Resources Management', 'study_school' => 'Al-Mustansiriyah University', 'timezone' => 'Asia/Baghdad'], $company, $deptHR, $jobs['HR Specialist'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Administrative']], $hrManager);
        $fin1     = $this->makeEmployee(['name' => 'عمر فاضل', 'name_en' => 'Omar Fadhil', 'family_name' => 'Fadhil', 'job_title' => 'Financial Analyst', 'work_email' => 'omar.fadhil@company.iq', 'work_phone' => '+964-770-001-0021', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1991-01-05', 'hire_date' => '2019-11-20', 'employment_status' => 'active', 'marital_status' => 'married', 'children' => 2, 'certificate_level' => 'bachelor', 'study_field' => 'Finance', 'study_school' => 'University of Basra', 'timezone' => 'Asia/Baghdad'], $company, $deptFin, $jobs['Financial Analyst'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Finance']], $finManager);
        $supp1    = $this->makeEmployee(['name' => 'باسم طارق', 'name_en' => 'Basim Tariq', 'family_name' => 'Tariq', 'job_title' => 'Technical Support Engineer', 'work_email' => 'basim.tariq@company.iq', 'work_phone' => '+964-770-001-0031', 'nationality' => 'Iraqi', 'gender' => 'male', 'birthday' => '1996-03-22', 'hire_date' => '2022-01-10', 'employment_status' => 'probation', 'marital_status' => 'single', 'children' => 0, 'certificate_level' => 'bachelor', 'study_field' => 'Telecommunications', 'study_school' => 'University of Baghdad', 'timezone' => 'Asia/Baghdad'], $company, $deptSupp, $jobs['Technical Support Engineer'], $locations['Head Office — Baghdad'], $calMorning, [$cats['Technical']], $opsManager);
        $supp2    = $this->makeEmployee(['name' => 'رنا عبدالرزاق', 'name_en' => 'Rana Abdul-Razzaq', 'family_name' => 'Abdul-Razzaq', 'job_title' => 'Technical Support Engineer', 'work_email' => 'rana.razzaq@company.iq', 'work_phone' => '+964-770-001-0032', 'nationality' => 'Iraqi', 'gender' => 'female', 'birthday' => '1997-07-14', 'hire_date' => '2022-06-01', 'employment_status' => 'active', 'marital_status' => 'single', 'children' => 0, 'certificate_level' => 'bachelor', 'study_field' => 'Network Engineering', 'study_school' => 'Middle Technical University', 'timezone' => 'Asia/Baghdad'], $company, $deptSupp, $jobs['Technical Support Engineer'], $locations['Branch — Basra'], $calMorning, [$cats['Technical']], $opsManager);

        // Department managers
        $deptExec->update(['manager_id' => $ceo->id]);
        $deptSoft->update(['manager_id' => $cto->id]);
        $deptDigit->update(['manager_id' => $cto->id]);
        $deptHR->update(['manager_id'   => $hrManager->id]);
        $deptFin->update(['manager_id'  => $finManager->id]);
        $deptOps->update(['manager_id'  => $opsManager->id]);
        $deptSupp->update(['manager_id' => $opsManager->id]);

        // Skills
        $this->assignSkills($dev1,     $skillTypes, $skillLevels, ['PHP' => 90, 'JavaScript' => 85, 'MySQL' => 75]);
        $this->assignSkills($dev2,     $skillTypes, $skillLevels, ['PHP' => 80, 'Python' => 70, 'MySQL' => 80]);
        $this->assignSkills($dev3,     $skillTypes, $skillLevels, ['PHP' => 95, 'JavaScript' => 90, 'MySQL' => 85]);
        $this->assignSkills($dba,      $skillTypes, $skillLevels, ['MySQL' => 95, 'PostgreSQL' => 80]);
        $this->assignSkills($designer, $skillTypes, $skillLevels, ['Figma' => 90, 'CSS' => 85]);
        $this->assignSkills($cto,      $skillTypes, $skillLevels, ['PHP' => 85, 'JavaScript' => 80, 'MySQL' => 75]);

        // Contracts
        $allEmployees = collect([$ceo, $cto, $hrManager, $finManager, $opsManager, $dev1, $dev2, $dev3, $dba, $designer, $hr1, $fin1, $supp1, $supp2]);

        foreach ($allEmployees as $emp) {
            $hireDate = $emp->hire_date ?? Carbon::now()->subYears(2);
            $contract = Contract::create([
                'employee_id'   => $emp->id,
                'name'          => 'Employment Contract ' . $hireDate->format('Y'),
                'contract_type' => 'full_time',
                'state'         => 'open',
                'date_start'    => $hireDate,
                'date_end'      => null,
                'wage'          => match ($emp->job?->name) {
                    'CEO'                        => 5000.00,
                    'Head of Software Solutions' => 4000.00,
                    'HR Manager'                 => 3000.00,
                    'Finance Manager'            => 3000.00,
                    'Operations Manager'         => 2800.00,
                    'Senior Software Developer'  => 2500.00,
                    'Full Stack Developer'       => 2000.00,
                    'Backend Developer'          => 1800.00,
                    'Database Administrator'     => 2200.00,
                    'UI/UX Designer'             => 1700.00,
                    'Financial Analyst'          => 1600.00,
                    default                      => 1200.00,
                },
            ]);
            $emp->update(['contract_id' => $contract->id, 'wage' => $contract->wage]);
        }

        $this->seedAllocations($allEmployees);
        $this->seedAttendances($allEmployees);
        $this->seedRequests($allEmployees, $company);

        $this->command->info('Employees seeded — ' . $allEmployees->count() . ' records created.');
    }

    // ── Leave / Time-off / Overtime requests ─────────────────────────────────

    private function seedRequests(\Illuminate\Support\Collection $employees, \App\Models\Settings\Company $company): void
    {
        // 1. Per-company balance config.
        \App\Models\Employees\RequestBalanceConfig::updateOrCreate(
            ['company_id' => $company->id],
            ['leave_days_per_month' => 1.75, 'leave_days_max' => 30, 'time_off_hours_per_month' => 8],
        );

        // 2. Subtypes (global = company_id null so every company can use them).
        $subtypeDefs = [
            ['name' => 'Sick Leave',         'type' => 'leave',    'cuts_salary' => false, 'cuts_balance' => true,  'requires_attachment' => true],
            ['name' => 'Paid Leave',         'type' => 'leave',    'cuts_salary' => true,  'cuts_balance' => false],
            ['name' => 'Unpaid Leave',       'type' => 'leave',    'cuts_salary' => false, 'cuts_balance' => true],
            ['name' => 'Remote Work',        'type' => 'leave',    'cuts_salary' => false, 'cuts_balance' => false],
            ['name' => 'Personal Time Off',  'type' => 'time_off', 'cuts_salary' => false, 'cuts_balance' => true],
            ['name' => 'Doctor Visit',       'type' => 'time_off', 'cuts_salary' => false, 'cuts_balance' => false],
            ['name' => 'Overtime',           'type' => 'overtime', 'cuts_salary' => false, 'cuts_balance' => false, 'factor' => 2.0],
        ];
        $subtypes = collect();
        foreach ($subtypeDefs as $def) {
            $subtypes[$def['name']] = \App\Models\Employees\RequestSubtype::firstOrCreate(
                ['name' => $def['name'], 'company_id' => null],
                array_merge([
                    'cuts_salary' => false, 'cuts_balance' => false, 'factor' => 1.0,
                    'requires_title' => true, 'requires_description' => false, 'requires_attachment' => false,
                    'active' => true,
                ], $def),
            );
        }

        // 3. Seed an employee record for the admin user so they can submit + HR-approve.
        $admin = \App\Models\User::where('email', 'admin@example.com')->first();
        if ($admin && !\App\Models\Employees\Employee::where('user_id', $admin->id)->exists()) {
            $hrDept = \App\Models\Employees\Department::where('name', 'Human Resources')->first();
            $adminEmployee = \App\Models\Employees\Employee::create([
                'name'                  => 'System Admin',
                'name_en'               => 'System Admin',
                'employee_code'         => 'ADMIN-001',
                'work_email'            => 'admin@example.com',
                'company_id'            => $company->id,
                'department_id'         => $hrDept?->id,
                'employment_status'     => 'active',
                'hire_date'             => '2024-01-01',
                'user_id'               => $admin->id,
                'resource_calendar_id'  => \App\Models\Employees\ResourceCalendar::first()->id,
                'attendance_manager_id' => $employees->where('job_title', 'Chief Executive Officer')->first()?->id,
                'active'                => true,
            ]);
            $employees = $employees->push($adminEmployee);
        }

        // 4. Give every employee an initial balance for testing.
        $balanceSvc = app(\App\Services\Employees\BalanceService::class);
        foreach ($employees as $emp) {
            $bal = $balanceSvc->getOrCreate($emp);
            $bal->update([
                'leave_days_balance'     => 10,
                'time_off_hours_balance' => 8,
                'last_credited_month'    => now()->startOfMonth()->toDateString(),
            ]);
        }

        // 5. Sample requests across various states.
        $requestSvc = app(\App\Services\Employees\EmployeeRequestService::class);

        // Make Sick Leave NOT require an attachment in demo so the seeder
        // can drive it without uploading a real file.
        $subtypes['Sick Leave']->update(['requires_attachment' => false]);

        // Mix of FUTURE and PAST samples — the past ones let the user see
        // how the approval back-links the attendance row. The seedRequests
        // step below ensures attendance exists for every past-sample date.
        // Time-off offsets are chosen to land on Sun-Thu (working days for
        // the Standard 40 schedule) so the validation doesn't reject them.
        // Helpers to anchor on the nearest working day rather than guessing offsets.
        $todayDow      = (now()->dayOfWeek + 1) % 7;          // S-ERP: Sat=0..Fri=6
        $nextWorkingOffset = function (int $startOffset) use ($todayDow): int {
            $d = $startOffset;
            // Working days for Standard 40 = Sun..Thu  (sys dow 1..5).
            while (true) {
                $dow = (($todayDow + $d) % 7 + 7) % 7;
                if ($dow >= 1 && $dow <= 5) return $d;
                $d += ($startOffset >= 0 ? 1 : -1);
            }
        };

        $samples = [
            // [employee_idx, subtype, start_offset, end_offset, type, title, startH|null, endH|null]
            // ── Past (linked to attendance — visible on the attendance show page) ──
            [5,  'Sick Leave',         -7, -6, 'leave',    'Flu — bed rest',    null, null],
            [6,  'Paid Leave',         -5, -5, 'leave',    'Personal day',      null, null],
            [7,  'Unpaid Leave',       -10,-9, 'leave',    'Family emergency',  null, null],
            [8,  'Personal Time Off',  $nextWorkingOffset(-3), $nextWorkingOffset(-3), 'time_off', 'Bank visit',        10.0, 12.0],
            [9,  'Doctor Visit',       $nextWorkingOffset(-2), $nextWorkingOffset(-2), 'time_off', 'Specialist',        14.0, 16.0],
            [10, 'Overtime',           -1, -1, 'overtime', 'Production deploy', 19.0, 22.0],
            // ── Future ──
            [3,  'Paid Leave',          5,  7, 'leave',    'Family event',     null, null],
            [4,  'Unpaid Leave',       10, 12, 'leave',    'Personal time',    null, null],
            [11, 'Remote Work',         2,  2, 'leave',    'Working from home',null, null],
            [12, 'Personal Time Off',  $nextWorkingOffset(3), $nextWorkingOffset(3), 'time_off', 'Errand',            9.0, 11.0],
        ];

        foreach ($samples as $i => [$idx, $stName, $startOff, $endOff, $type, $title, $startH, $endH]) {
            $emp = $employees->values()->get($idx);
            if (!$emp) continue;
            $subtype = $subtypes[$stName];
            $startDate = now()->copy()->addDays($startOff)->startOfDay();
            $endDate   = now()->copy()->addDays($endOff)->endOfDay();
            if ($type !== 'leave') {
                $startDate = $startDate->copy()->setTime((int) floor($startH), (int) (($startH - floor($startH)) * 60));
                $endDate   = now()->copy()->addDays($endOff)->setTime((int) floor($endH), (int) (($endH - floor($endH)) * 60));
            }

            // For PAST samples: ensure an attendance row exists for every
            // covered day so the approval-side-effect has something to back-link
            // to. Days where the employee was scheduled to work get a normal
            // punch-in/out; day-offs get an empty row.
            if ($startOff < 0) {
                $cursor = $startDate->copy()->startOfDay();
                $stop   = $endDate->copy()->endOfDay();
                while ($cursor->lte($stop)) {
                    $exists = \App\Models\Employees\Attendance::where('employee_id', $emp->id)
                        ->whereDate('attendance_date', $cursor->toDateString())
                        ->exists();
                    if (!$exists) {
                        $sysDow = ($cursor->dayOfWeek + 1) % 7;
                        $blocks = $emp->resourceCalendar?->attendances
                            ->where('day_of_week', $sysDow)->values()->all() ?? [];
                        $data = [
                            'employee_id'          => $emp->id,
                            'company_id'           => $emp->company_id,
                            'resource_calendar_id' => $emp->resource_calendar_id,
                            'attendance_date'      => $cursor->toDateString(),
                        ];
                        if (!empty($blocks)) {
                            // Working day → realistic punch matching the schedule.
                            $earliest = (float) collect($blocks)->min('hour_from');
                            $latest   = (float) collect($blocks)->max('hour_to');
                            $data['check_in']  = $this->floatToCarbon($cursor, $earliest + mt_rand(-5, 10) / 60)->toDateTimeString();
                            $data['check_out'] = $this->floatToCarbon($cursor, $latest   + mt_rand(-5, 10) / 60)->toDateTimeString();
                        }
                        app(\App\Services\Employees\AttendanceService::class)
                            ->create($data);
                    }
                    $cursor->addDay();
                }
            }

            try {
                $req = $requestSvc->create([
                    'employee_id' => $emp->id,
                    'subtype_id'  => $subtype->id,
                    'type'        => $type,
                    'start_at'    => $startDate->toDateTimeString(),
                    'end_at'      => $endDate->toDateTimeString(),
                    'title'       => $title,
                    'description' => null,
                    'attachment'  => null,
                ]);
            } catch (\Throwable $e) {
                $this->command->warn("  request seed skipped (#$i $stName): " . $e->getMessage());
                continue;
            }

            // Drive a few to terminal states for demo variety. Past samples are
            // always fully approved so the attendance back-link shows up on the
            // attendance show page (the whole point of the past demo data).
            $managerUserId = $emp->attendanceManager?->user_id ?? $admin?->id ?? 1;
            $hrUserId      = $admin?->id ?? 1;
            try {
                if ($startOff < 0) {
                    $requestSvc->decide($req, 'manager', 'approve', null, $managerUserId);
                    $requestSvc->decide($req->refresh(), 'hr', 'approve', null, $hrUserId);
                    continue;
                }
                switch ($i % 4) {
                    case 0: // manager approves; HR also approves => approved
                        $requestSvc->decide($req, 'manager', 'approve', null, $managerUserId);
                        $requestSvc->decide($req->refresh(), 'hr', 'approve', null, $hrUserId);
                        break;
                    case 1: // manager approves; HR pending => pending
                        $requestSvc->decide($req, 'manager', 'approve', null, $managerUserId);
                        break;
                    case 2: // manager rejects => rejected
                        $requestSvc->decide($req, 'manager', 'reject', 'Conflicts with project deadline', $managerUserId);
                        break;
                    case 3: // HR override approve
                        $requestSvc->decide($req, 'hr', 'approve', null, $hrUserId);
                        break;
                }
            } catch (\Throwable $e) {
                $this->command->warn("  decision seed skipped (#$i): " . $e->getMessage());
            }
        }

        $this->command->info('Requests seeded — ' . count($samples) . ' samples + admin employee.');
    }

    // ── Attendance ───────────────────────────────────────────────────────────

    private function seedAttendances(\Illuminate\Support\Collection $employees): void
    {
        $service = app(AttendanceService::class);
        $today   = Carbon::today();

        // 30 calendar days back, weighted random scenarios per employee per day.
        $created = 0;
        foreach ($employees as $employee) {
            if (!$employee->resource_calendar_id) continue;

            for ($i = 30; $i >= 1; $i--) {
                $date = $today->copy()->subDays($i);

                $exists = Attendance::where('employee_id', $employee->id)
                    ->where('attendance_date', $date->toDateString())
                    ->exists();
                if ($exists) continue;

                // Resolve scheduled blocks for this date — drives the scenario picker.
                $sysDow = ($date->dayOfWeek + 1) % 7;
                $blocks = $employee->resourceCalendar?->attendances
                    ->where('day_of_week', $sysDow)
                    ->values()
                    ->all() ?? [];

                if (empty($blocks)) {
                    // Day off — no punches needed (recompute sets is_day_off).
                    $service->create([
                        'employee_id'     => $employee->id,
                        'attendance_date' => $date->toDateString(),
                    ]);
                    $created++;
                    continue;
                }

                $earliest = (float) collect($blocks)->min('hour_from');
                $latest   = (float) collect($blocks)->max('hour_to');

                // Pick scenario by weighted bucket.
                $roll = mt_rand(1, 100);
                $data = [
                    'employee_id'     => $employee->id,
                    'attendance_date' => $date->toDateString(),
                ];

                if ($roll <= 10) {
                    // 10% absence — leave check_in/out null.
                } elseif ($roll <= 25) {
                    // 15% overtime — checked in early or out late.
                    $checkIn  = $this->floatToCarbon($date, $earliest - (mt_rand(0, 30) / 60));
                    $checkOut = $this->floatToCarbon($date, $latest + ((mt_rand(30, 120)) / 60));
                    $data['check_in']  = $checkIn->toDateTimeString();
                    $data['check_out'] = $checkOut->toDateTimeString();
                } elseif ($roll <= 45) {
                    // 20% shortage — short by 0.5–2h on the end.
                    $checkIn  = $this->floatToCarbon($date, $earliest + (mt_rand(0, 15) / 60));
                    $checkOut = $this->floatToCarbon($date, $latest - (mt_rand(30, 120) / 60));
                    $data['check_in']  = $checkIn->toDateTimeString();
                    $data['check_out'] = $checkOut->toDateTimeString();
                } else {
                    // 55% on-time present (small drift).
                    $checkIn  = $this->floatToCarbon($date, $earliest + (mt_rand(-10, 10) / 60));
                    $checkOut = $this->floatToCarbon($date, $latest + (mt_rand(-10, 10) / 60));
                    $data['check_in']  = $checkIn->toDateTimeString();
                    $data['check_out'] = $checkOut->toDateTimeString();
                }

                $service->create($data);
                $created++;
            }
        }

        $this->command->info("Attendance seeded — {$created} records.");
    }

    private function floatToCarbon(Carbon $date, float $hour): Carbon
    {
        $dayOffset = (int) floor($hour / 24);
        $remaining = $hour - ($dayOffset * 24);
        $h = (int) floor($remaining);
        $m = (int) round(($remaining - $h) * 60);
        return $date->copy()->startOfDay()->addDays($dayOffset)->addHours($h)->addMinutes($m);
    }

    // ── Allocations (Salary Components) ──────────────────────────────────────

    private function seedAllocations(\Illuminate\Support\Collection $employees): void
    {
        // Index employees by job title for easy lookup
        $byJob = $employees->keyBy(fn ($e) => $e->job?->name ?? '');

        $ceo        = $byJob['CEO'] ?? null;
        $cto        = $byJob['Head of Software Solutions'] ?? null;
        $hrManager  = $byJob['HR Manager'] ?? null;
        $finManager = $byJob['Finance Manager'] ?? null;
        $opsManager = $byJob['Operations Manager'] ?? null;
        $dev1       = $employees->firstWhere('work_email', 'hayder.ali@company.iq');
        $dev2       = $employees->firstWhere('work_email', 'zainab.khalid@company.iq');
        $dev3       = $employees->firstWhere('work_email', 'mohammed.jassim@company.iq');
        $dba        = $byJob['Database Administrator'] ?? null;
        $designer   = $byJob['UI/UX Designer'] ?? null;
        $hr1        = $employees->firstWhere('work_email', 'fatima.rahim@company.iq');
        $fin1       = $byJob['Financial Analyst'] ?? null;
        $supp1      = $employees->firstWhere('work_email', 'basim.tariq@company.iq');
        $supp2      = $employees->firstWhere('work_email', 'rana.razzaq@company.iq');

        // ── Job Grades ────────────────────────────────────────────────────────
        // Define pay-scale grades; financial_specialization = grade supplement (IQD equivalent)

        $gradeExec = EmployeeJobGrade::firstOrCreate(
            ['organizational_structure' => 'المستوى التنفيذي'],
            ['assignment_type' => 'دائمي', 'data_status' => 'current', 'financial_specialization' => 1500.00, 'affective_date' => '2024-01-01', 'active' => true]
        );
        $gradeExec->employees()->syncWithoutDetaching(collect([$ceo])->filter()->pluck('id'));

        $gradeSrMgr = EmployeeJobGrade::firstOrCreate(
            ['organizational_structure' => 'مستوى الإدارة العليا'],
            ['assignment_type' => 'دائمي', 'data_status' => 'current', 'financial_specialization' => 1000.00, 'affective_date' => '2024-01-01', 'active' => true]
        );
        $gradeSrMgr->employees()->syncWithoutDetaching(collect([$cto, $hrManager, $finManager, $opsManager])->filter()->pluck('id'));

        $gradeSrTech = EmployeeJobGrade::firstOrCreate(
            ['organizational_structure' => 'المستوى التقني المتقدم'],
            ['assignment_type' => 'دائمي', 'data_status' => 'current', 'financial_specialization' => 600.00, 'affective_date' => '2024-01-01', 'active' => true]
        );
        $gradeSrTech->employees()->syncWithoutDetaching(collect([$dev3, $dba])->filter()->pluck('id'));

        $gradeTech = EmployeeJobGrade::firstOrCreate(
            ['organizational_structure' => 'المستوى التقني'],
            ['assignment_type' => 'دائمي', 'data_status' => 'current', 'financial_specialization' => 400.00, 'affective_date' => '2024-01-01', 'active' => true]
        );
        $gradeTech->employees()->syncWithoutDetaching(collect([$dev1, $dev2, $designer, $fin1])->filter()->pluck('id'));

        $gradeAdmin = EmployeeJobGrade::firstOrCreate(
            ['organizational_structure' => 'المستوى الإداري'],
            ['assignment_type' => 'دائمي', 'data_status' => 'current', 'financial_specialization' => 300.00, 'affective_date' => '2024-01-01', 'active' => true]
        );
        $gradeAdmin->employees()->syncWithoutDetaching(collect([$hr1])->filter()->pluck('id'));

        $gradeEntry = EmployeeJobGrade::firstOrCreate(
            ['organizational_structure' => 'مستوى الدعم الفني'],
            ['assignment_type' => 'عقدي', 'data_status' => 'current', 'financial_specialization' => 200.00, 'affective_date' => '2024-01-01', 'active' => true]
        );
        $gradeEntry->employees()->syncWithoutDetaching(collect([$supp1, $supp2])->filter()->pluck('id'));

        // ── Bonuses & Promotions (علاوات وترفيعات) ────────────────────────────

        $bonusHousing = EmployeeBonus::firstOrCreate(
            ['name' => 'علاوة الإسكان'],
            [
                'organizational_structure' => 'جميع الأقسام',
                'assignment_type'          => 'دائمي',
                'data_status'              => 'current',
                'financial_specialization' => 400.00,
                'affective_date'           => '2024-01-01',
                'issued_by'                => 'إدارة الموارد البشرية',
                'notes'                    => 'علاوة إسكان شهرية ثابتة لجميع الموظفين الدائميين.',
                'active'                   => true,
            ]
        );
        $bonusHousing->employees()->syncWithoutDetaching($employees->pluck('id'));

        $bonusTransport = EmployeeBonus::firstOrCreate(
            ['name' => 'علاوة النقل'],
            [
                'organizational_structure' => 'جميع الأقسام',
                'assignment_type'          => 'دائمي',
                'data_status'              => 'current',
                'financial_specialization' => 150.00,
                'affective_date'           => '2024-01-01',
                'issued_by'                => 'إدارة الموارد البشرية',
                'notes'                    => 'علاوة نقل شهرية لتغطية تكاليف التنقل.',
                'active'                   => true,
            ]
        );
        $bonusTransport->employees()->syncWithoutDetaching($employees->pluck('id'));

        $bonusTechSpec = EmployeeBonus::firstOrCreate(
            ['name' => 'علاوة التخصص التقني'],
            [
                'organizational_structure' => 'قسم تطوير البرمجيات',
                'assignment_type'          => 'دائمي',
                'data_status'              => 'current',
                'financial_specialization' => 350.00,
                'affective_date'           => '2023-07-01',
                'issued_by'                => 'إدارة الموارد البشرية',
                'notes'                    => 'علاوة خاصة بالكوادر التقنية في قسم البرمجيات.',
                'active'                   => true,
            ]
        );
        $bonusTechSpec->employees()->syncWithoutDetaching(
            collect([$cto, $dev1, $dev2, $dev3, $dba, $designer])->filter()->pluck('id')
        );

        $bonusPromotion = EmployeeBonus::firstOrCreate(
            ['name' => 'ترفيع 2024 - زيادة علاوة الأقدمية'],
            [
                'organizational_structure' => 'الإدارة العليا والتقنيون',
                'assignment_type'          => 'دائمي',
                'data_status'              => 'current',
                'financial_specialization' => 250.00,
                'affective_date'           => '2024-07-01',
                'issued_by'                => 'مجلس الإدارة',
                'notes'                    => 'علاوة أقدمية سنوية بمناسبة إتمام 5 سنوات خدمة أو أكثر.',
                'active'                   => true,
            ]
        );
        $bonusPromotion->employees()->syncWithoutDetaching(
            collect([$ceo, $cto, $hrManager, $finManager, $opsManager, $dev3, $dba])->filter()->pluck('id')
        );

        $bonusRisk = EmployeeBonus::firstOrCreate(
            ['name' => 'علاوة الخطورة والمسؤولية'],
            [
                'organizational_structure' => 'الإدارة التنفيذية',
                'assignment_type'          => 'دائمي',
                'data_status'              => 'current',
                'financial_specialization' => 300.00,
                'affective_date'           => '2024-01-01',
                'issued_by'                => 'مجلس الإدارة',
                'notes'                    => 'علاوة خطورة ومسؤولية لشاغلي المناصب الإدارية.',
                'active'                   => true,
            ]
        );
        $bonusRisk->employees()->syncWithoutDetaching(
            collect([$ceo, $cto, $hrManager, $finManager, $opsManager])->filter()->pluck('id')
        );

        // ── Thanks & Appreciation (شكر وتقدير) ────────────────────────────────

        $appExcellence = EmployeeAppreciation::firstOrCreate(
            ['name' => 'شهادة شكر - الأداء المتميز 2025'],
            [
                'document_type'            => 'certificate',
                'organizational_structure' => 'قسم تطوير البرمجيات',
                'assignment_type'          => 'سنوي',
                'data_status'              => 'current',
                'financial_specialization' => 0.00,
                'issue_date'               => '2025-12-31',
                'issued_by'                => 'المدير التنفيذي',
                'document_number'          => 'APP-2025-001',
                'notes'                    => 'تقديراً للجهود المبذولة والأداء المتميز خلال عام 2025.',
                'active'                   => true,
            ]
        );
        $appExcellence->employees()->syncWithoutDetaching(
            collect([$cto, $dev3, $dba])->filter()->pluck('id')
        );

        $appProject = EmployeeAppreciation::firstOrCreate(
            ['name' => 'شكر جزيل - إنجاز مشروع التحول الرقمي'],
            [
                'document_type'            => 'certificate',
                'organizational_structure' => 'فريق التحول الرقمي',
                'assignment_type'          => 'مرحلي',
                'data_status'              => 'current',
                'financial_specialization' => 0.00,
                'issue_date'               => '2025-06-15',
                'issued_by'                => 'رئيس مجلس الإدارة',
                'document_number'          => 'APP-2025-002',
                'notes'                    => 'شكر وتقدير لفريق العمل على إتمام مشروع التحول الرقمي في الموعد المحدد.',
                'active'                   => true,
            ]
        );
        $appProject->employees()->syncWithoutDetaching(
            collect([$cto, $dev1, $dev2, $dev3, $designer])->filter()->pluck('id')
        );

        $appHR = EmployeeAppreciation::firstOrCreate(
            ['name' => 'تكريم - موظف الربع الأول 2026'],
            [
                'organizational_structure' => 'الموارد البشرية',
                'assignment_type'          => 'ربع سنوي',
                'data_status'              => 'current',
                'financial_specialization' => 0.00,
                'issue_date'               => '2026-04-01',
                'issued_by'                => 'إدارة الموارد البشرية',
                'document_number'          => 'APP-2026-001',
                'notes'                    => 'جائزة موظف الربع - التزام ومبادرة استثنائية.',
                'active'                   => true,
            ]
        );
        $appHR->employees()->syncWithoutDetaching(
            collect([$hr1])->filter()->pluck('id')
        );

        // ── Disciplinary Sanctions (عقوبات انضباطية) ─────────────────────────
        // financial_specialization = deduction amount

        $sanctionWarning = EmployeeSanction::firstOrCreate(
            ['name' => 'إنذار خطي - تأخر متكرر'],
            [
                'document_type'            => 'other',
                'organizational_structure' => 'قسم الدعم الفني',
                'assignment_type'          => 'انضباطي',
                'data_status'              => 'previous',
                'financial_specialization' => 50.00,
                'issue_date'               => '2025-03-10',
                'affective_date'           => '2025-03-10',
                'issued_by'                => 'مدير العمليات',
                'document_number'          => 'SANC-2025-001',
                'notes'                    => 'إنذار خطي بسبب التأخر المتكرر في الحضور. الخصم 50 دولار من راتب شهر آذار.',
                'active'                   => true,
            ]
        );
        $sanctionWarning->employees()->syncWithoutDetaching(
            collect([$supp1])->filter()->pluck('id')
        );

        $sanctionAbsence = EmployeeSanction::firstOrCreate(
            ['name' => 'خصم راتب - غياب بدون إذن'],
            [
                'document_type'            => 'other',
                'organizational_structure' => 'قسم الدعم الفني',
                'assignment_type'          => 'انضباطي',
                'data_status'              => 'current',
                'financial_specialization' => 100.00,
                'issue_date'               => '2026-02-05',
                'affective_date'           => '2026-02-01',
                'issued_by'                => 'إدارة الموارد البشرية',
                'document_number'          => 'SANC-2026-001',
                'notes'                    => 'خصم يومين راتب بسبب الغياب بدون إذن مسبق.',
                'active'                   => true,
            ]
        );
        $sanctionAbsence->employees()->syncWithoutDetaching(
            collect([$supp2])->filter()->pluck('id')
        );

        $sanctionDeduction = EmployeeSanction::firstOrCreate(
            ['name' => 'خصم مالي - إهمال في العمل'],
            [
                'document_type'            => 'other',
                'organizational_structure' => 'قسم الموارد البشرية',
                'assignment_type'          => 'انضباطي',
                'data_status'              => 'previous',
                'financial_specialization' => 200.00,
                'issue_date'               => '2024-11-01',
                'affective_date'           => '2024-11-01',
                'issued_by'                => 'مجلس التأديب',
                'document_number'          => 'SANC-2024-001',
                'notes'                    => 'خصم مالي بسبب إهمال واضح أثر على مستوى الخدمة.',
                'active'                   => true,
            ]
        );
        $sanctionDeduction->employees()->syncWithoutDetaching(
            collect([$hr1])->filter()->pluck('id')
        );

        // ── Rewards & Penalties (مكافآت وغرامات) ─────────────────────────────

        $rewardExcellence = EmployeeReward::firstOrCreate(
            ['name' => 'مكافأة التميز - الربع الأول 2026'],
            [
                'organizational_structure' => 'قسم تطوير البرمجيات',
                'assignment_type'          => 'ربع سنوي',
                'data_status'              => 'current',
                'financial_specialization' => 500.00,
                'issue_date'               => '2026-04-01',
                'affective_date'           => '2026-04-01',
                'issued_by'                => 'المدير التنفيذي',
                'document_number'          => 'RWD-2026-001',
                'notes'                    => 'مكافأة مالية لأعلى أداء في الربع الأول من 2026.',
                'active'                   => true,
            ]
        );
        $rewardExcellence->employees()->syncWithoutDetaching(
            collect([$dev3, $cto])->filter()->pluck('id')
        );

        $rewardLongService = EmployeeReward::firstOrCreate(
            ['name' => 'مكافأة الخدمة الطويلة - 10 سنوات'],
            [
                'organizational_structure' => 'الإدارة التنفيذية',
                'assignment_type'          => 'استثنائي',
                'data_status'              => 'current',
                'financial_specialization' => 750.00,
                'issue_date'               => '2025-01-01',
                'affective_date'           => '2025-01-01',
                'issued_by'                => 'مجلس الإدارة',
                'document_number'          => 'RWD-2025-001',
                'notes'                    => 'مكافأة خاصة لإتمام 10 سنوات خدمة متواصلة.',
                'active'                   => true,
            ]
        );
        $rewardLongService->employees()->syncWithoutDetaching(
            collect([$ceo, $finManager, $opsManager])->filter()->pluck('id')
        );

        $rewardProject = EmployeeReward::firstOrCreate(
            ['name' => 'مكافأة إتمام مشروع ERP'],
            [
                'organizational_structure' => 'فريق مشروع ERP',
                'assignment_type'          => 'مرحلي',
                'data_status'              => 'current',
                'financial_specialization' => 400.00,
                'issue_date'               => '2025-09-30',
                'affective_date'           => '2025-09-30',
                'issued_by'                => 'المدير التنفيذي',
                'document_number'          => 'RWD-2025-002',
                'notes'                    => 'مكافأة مالية لأعضاء الفريق على إنجاز منظومة ERP في الموعد المحدد.',
                'active'                   => true,
            ]
        );
        $rewardProject->employees()->syncWithoutDetaching(
            collect([$cto, $dev1, $dev2, $dev3, $dba, $designer])->filter()->pluck('id')
        );

        $rewardAnnual = EmployeeReward::firstOrCreate(
            ['name' => 'المكافأة السنوية 2025'],
            [
                'organizational_structure' => 'جميع الأقسام',
                'assignment_type'          => 'سنوي',
                'data_status'              => 'current',
                'financial_specialization' => 600.00,
                'issue_date'               => '2025-12-31',
                'affective_date'           => '2025-12-31',
                'issued_by'                => 'مجلس الإدارة',
                'document_number'          => 'RWD-2025-003',
                'notes'                    => 'مكافأة نهاية السنة الممنوحة لجميع الموظفين بناءً على الأداء العام.',
                'active'                   => true,
            ]
        );
        $rewardAnnual->employees()->syncWithoutDetaching($employees->pluck('id'));

        $this->command->info('Allocations seeded — job grades, bonuses, appreciations, sanctions, rewards.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeCalendar(int $companyId, string $name, float $hoursPerDay, array $attendances): ResourceCalendar
    {
        $calendar = ResourceCalendar::firstOrCreate(
            ['name' => $name, 'company_id' => $companyId],
            ['hours_per_day' => $hoursPerDay, 'flexible_hours' => false, 'active' => true]
        );

        if ($calendar->attendances()->count() === 0) {
            foreach ($attendances as $i => [$dow, $period, $from, $to]) {
                $calendar->attendances()->create([
                    'day_of_week' => $dow,
                    'day_period'  => $period,
                    'hour_from'   => $from,
                    'hour_to'     => $to,
                    'sequence'    => $i,
                ]);
            }
        }

        return $calendar;
    }

    private function makeEmployee(array $data, Company $company, Department $dept, Job $job, WorkLocation $location, ResourceCalendar $calendar, array $categories = [], ?Employee $manager = null): Employee
    {
        $employee = Employee::firstOrCreate(
            ['work_email' => $data['work_email']],
            array_merge($data, [
                'company_id'             => $company->id,
                'department_id'          => $dept->id,
                'job_id'                 => $job->id,
                'work_location_id'       => $location->id,
                'resource_calendar_id'   => $calendar->id,
                'parent_id'              => $manager?->id,
                'coach_id'               => $manager?->id,
                'expense_manager_id'     => $manager?->id,
                'attendance_manager_id'  => $manager?->id,
                'payment_method'         => 'bank_transfer',
                'country'                => 'Iraq',
                'country_of_birth'       => 'Iraq',
                'active'                 => true,
                'birthday'               => isset($data['birthday']) ? Carbon::parse($data['birthday']) : null,
                'hire_date'              => isset($data['hire_date']) ? Carbon::parse($data['hire_date']) : null,
                'first_contract_date'    => isset($data['hire_date']) ? Carbon::parse($data['hire_date']) : null,
            ])
        );

        if ($categories) {
            $employee->categories()->syncWithoutDetaching(collect($categories)->pluck('id'));
        }

        return $employee;
    }

    private function seedSkillTypes(int $companyId): array
    {
        $skillDefs = [
            'Programming Languages' => [
                'PHP'        => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
                'Python'     => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
                'JavaScript' => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
            ],
            'Databases' => [
                'MySQL'      => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
                'PostgreSQL' => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
            ],
            'Design' => [
                'Figma' => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
                'CSS'   => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
            ],
        ];

        $skillTypes  = [];
        $skillLevels = [];

        foreach ($skillDefs as $typeName => $skills) {
            $type = SkillType::firstOrCreate(['name' => $typeName], ['active' => true]);
            $skillTypes[$typeName] = $type;

            foreach ($skills as $skillName => $levels) {
                $skill = Skill::firstOrCreate(['name' => $skillName, 'skill_type_id' => $type->id], ['active' => true]);
                $skillTypes[$skillName] = $skill;

                foreach ($levels as [$progress, $levelName]) {
                    $level = SkillLevel::firstOrCreate(
                        ['name' => $levelName, 'skill_type_id' => $type->id],
                        ['level_progress' => $progress, 'sequence' => $progress]
                    );
                    $skillLevels[$skillName][$progress] = $level;
                }
            }
        }

        return [$skillTypes, $skillLevels];
    }

    private function assignSkills(Employee $employee, array $skillTypes, array $skillLevels, array $skills): void
    {
        foreach ($skills as $skillName => $progress) {
            $skill = $skillTypes[$skillName] ?? null;
            if (!$skill instanceof Skill) continue;

            $type = $skill->skillType instanceof SkillType ? $skill->skillType : ($skillTypes[$skill->skillType?->name ?? ''] ?? null);

            $level = null;
            foreach (array_keys($skillLevels[$skillName] ?? []) as $threshold) {
                if ($progress >= $threshold) $level = $skillLevels[$skillName][$threshold];
            }

            EmployeeSkill::firstOrCreate(
                ['employee_id' => $employee->id, 'skill_id' => $skill->id],
                ['skill_type_id' => $type?->id, 'skill_level_id' => $level?->id]
            );
        }
    }

    // ── Accounting ────────────────────────────────────────────────────────────

    /**
     * Install the standard chart + journals into every demo company,
     * then post a realistic Q1 bookkeeping scenario on Acme Holdings.
     */
    private function seedAccounting(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) return;

        Auth::login($admin);
        try {
            $core = new CoreSeeder();
            foreach (Company::all() as $company) {
                $core->installAccountingForCompany($company);
            }

            $acme = Company::where('name', 'Acme Holdings')->first();
            if (!$acme) return;

            // Skip if Acme already has demo entries (idempotent re-runs)
            if (AccountMove::where('company_id', $acme->id)->exists()) return;

            $this->postDemoEntriesFor($acme);
        } finally {
            Auth::logout();
        }
    }

    /**
     * Posts ~10 entries that cover sales, purchases, payments, and a quarter-end
     * adjustment, plus one draft entry so both states are visible in the UI.
     * Trial balance is verified to be exactly zero before printing the summary.
     */
    private function postDemoEntriesFor(Company $company): void
    {
        $svc = app(AccountingService::class);

        $accounts = Account::where('company_id', $company->id)->get()->keyBy('code');
        $journals = AccountJournal::where('company_id', $company->id)->get()->keyBy('code');

        // Pick the first customer-style contact as the partner on credit-sale entries.
        $partner = Contact::where('company_id', $company->id)
            ->where('contact_type', 'company')
            ->orderBy('id')
            ->first();
        $partnerId = $partner?->id;

        // Iraqi UAS code reference:
        //   1811 صندوق المركز         | 183 نقدية لدى المصارف   | 2313 مخصص اندثار آلات
        //   1614 مدينون قطاع خاص      | 2614 مجهزون قطاع خاص    | 372 اندثار آلات ومعدات
        //   1371 مخزون البضائع للبيع   | 321 الخامات والمواد     | 324 مواد التعبئة
        //   4211 صافي مبيعات بضائع    | 4221 تغير مخزون بضائع   | 3352 استئجار مباني
        //   3111 رواتب                | 3366 خدمات مصرفية       | 211 رأس المال المدفوع
        $entries = [
            // 1. مساهمة رأس المال — Owner contribution to bootstrap the bank account
            [
                'journal'   => 'BANK',
                'date'      => '2026-01-02',
                'ref'       => 'OPEN-2026',
                'narration' => 'مساهمة افتتاحية في رأس المال من المالك. | Opening capital contribution.',
                'lines' => [
                    ['code' => '183', 'name' => 'إيداع رأس المال (Capital deposit)',          'debit' => 50_000, 'credit' => 0],
                    ['code' => '211', 'name' => 'إيداع رأس المال (Capital deposit)',          'debit' => 0,      'credit' => 50_000],
                ],
            ],
            // 2. شراء بضاعة افتتاحية — Initial inventory stock (paid from bank)
            [
                'journal' => 'BANK', 'date' => '2026-01-05',
                'lines' => [
                    ['code' => '1371', 'name' => 'شراء بضاعة افتتاحية (Initial goods)',        'debit' => 15_000, 'credit' => 0],
                    ['code' => '183',  'name' => 'شراء بضاعة افتتاحية (Initial goods)',        'debit' => 0,      'credit' => 15_000],
                ],
            ],
            // 3. بيع نقدي — Cash sale to walk-in customer
            [
                'journal' => 'INV', 'date' => '2026-01-15',
                'lines' => [
                    ['code' => '1811', 'name' => 'بيع نقدي (Cash sale)',                       'debit' => 1_200,  'credit' => 0],
                    ['code' => '4211', 'name' => 'بيع نقدي (Cash sale)',                       'debit' => 0,      'credit' => 1_200],
                ],
            ],
            // 4. بيع آجل — Credit sale to private-sector customer (linked partner)
            [
                'journal' => 'INV', 'date' => '2026-01-20', 'partner_id' => $partnerId,
                'ref' => 'SO-1042',
                'lines' => [
                    ['code' => '1614', 'name' => 'فاتورة SO-1042 (Invoice)',                   'debit' => 12_000, 'credit' => 0, 'partner_id' => $partnerId],
                    ['code' => '4211', 'name' => 'فاتورة SO-1042 (Invoice)',                   'debit' => 0,      'credit' => 12_000, 'partner_id' => $partnerId],
                ],
            ],
            // 5. تغير مخزون البضاعة — Inventory change for the credit sale
            //    UAS treatment: Dr 4221 (change in goods inventory — a revenue contra),
            //                   Cr 1371 (reduce goods inventory asset).
            [
                'journal' => 'MISC', 'date' => '2026-01-20',
                'narration' => 'إثبات تغير مخزون البضاعة لفاتورة SO-1042.',
                'lines' => [
                    ['code' => '4221', 'name' => 'تغير مخزون بضائع SO-1042 (Inventory change)','debit' => 7_200,  'credit' => 0],
                    ['code' => '1371', 'name' => 'تغير مخزون بضائع SO-1042 (Inventory change)','debit' => 0,      'credit' => 7_200],
                ],
            ],
            // 6. فاتورة مجهز — Vendor bill: raw materials + packaging (3-line entry)
            [
                'journal' => 'BILL', 'date' => '2026-01-25',
                'ref' => 'BILL-MAT-001',
                'lines' => [
                    ['code' => '321',  'name' => 'خامات (Raw materials)',                      'debit' => 150,    'credit' => 0],
                    ['code' => '324',  'name' => 'مواد تعبئة (Packaging materials)',           'debit' => 50,     'credit' => 0],
                    ['code' => '2614', 'name' => 'فاتورة BILL-MAT-001',                        'debit' => 0,      'credit' => 200],
                ],
            ],
            // 7. تسديد المجهز — Pay the supplier
            [
                'journal' => 'BANK', 'date' => '2026-01-30',
                'ref' => 'PAY-BILL-MAT-001',
                'lines' => [
                    ['code' => '2614', 'name' => 'تسديد BILL-MAT-001 (Pay supplier)',          'debit' => 200,    'credit' => 0],
                    ['code' => '183',  'name' => 'تسديد BILL-MAT-001 (Pay supplier)',          'debit' => 0,      'credit' => 200],
                ],
            ],
            // 8. إيجار شباط — February building rent
            [
                'journal' => 'BANK', 'date' => '2026-02-01',
                'lines' => [
                    ['code' => '3352', 'name' => 'إيجار شهر شباط (February rent)',             'debit' => 2_500,  'credit' => 0],
                    ['code' => '183',  'name' => 'إيجار شهر شباط (February rent)',             'debit' => 0,      'credit' => 2_500],
                ],
            ],
            // 9. تحصيل من زبون — Customer pays the SO-1042 invoice
            [
                'journal' => 'BANK', 'date' => '2026-02-15', 'partner_id' => $partnerId,
                'ref' => 'RCPT-SO-1042',
                'lines' => [
                    ['code' => '183',  'name' => 'تحصيل SO-1042 (Customer receipt)',           'debit' => 12_000, 'credit' => 0, 'partner_id' => $partnerId],
                    ['code' => '1614', 'name' => 'تحصيل SO-1042 (Customer receipt)',           'debit' => 0,      'credit' => 12_000, 'partner_id' => $partnerId],
                ],
            ],
            // 10. تسويات نهاية الفصل — Q1 month-end: machinery depreciation + bank fees (4 lines)
            [
                'journal' => 'MISC', 'date' => '2026-02-28',
                'narration' => 'تسويات نهاية الربع الأول: قسط اندثار + رسوم مصرفية.',
                'lines' => [
                    ['code' => '372',  'name' => 'اندثار الربع الأول (Q1 depreciation)',        'debit' => 500,    'credit' => 0],
                    ['code' => '3366', 'name' => 'رسوم مصرفية (Bank service charges)',         'debit' => 25,     'credit' => 0],
                    ['code' => '2313', 'name' => 'مخصص اندثار آلات الربع الأول',                'debit' => 0,      'credit' => 500],
                    ['code' => '183',  'name' => 'رسوم مصرفية (Bank service charges)',         'debit' => 0,      'credit' => 25],
                ],
            ],
        ];

        // Post all of the above
        $postedCount = 0;
        foreach ($entries as $entry) {
            $move = $svc->createMove(
                [
                    'company_id' => $company->id,
                    'journal_id' => $journals[$entry['journal']]->id,
                    'partner_id' => $entry['partner_id'] ?? null,
                    'date'       => $entry['date'],
                    'ref'        => $entry['ref'] ?? null,
                    'move_type'  => 'entry',
                    'currency'   => $company->currency ?: 'USD',
                    'narration'  => $entry['narration'] ?? null,
                ],
                array_map(fn ($line) => [
                    'account_id' => $accounts[$line['code']]->id,
                    'partner_id' => $line['partner_id'] ?? null,
                    'name'       => $line['name'],
                    'debit'      => $line['debit'],
                    'credit'     => $line['credit'],
                ], $entry['lines'])
            );
            $svc->postMove($move);
            $postedCount++;
        }

        // 11. قيد مسودة (DRAFT) — رواتب آذار قيد الموافقة | March payroll pending approval
        $svc->createMove(
            [
                'company_id' => $company->id,
                'journal_id' => $journals['BANK']->id,
                'date'       => '2026-03-01',
                'ref'        => 'PAYROLL-MAR (DRAFT)',
                'move_type'  => 'entry',
                'currency'   => $company->currency ?: 'USD',
                'narration'  => 'رواتب شهر آذار — بانتظار الموافقة قبل الترحيل.',
            ],
            [
                ['account_id' => $accounts['3111']->id, 'name' => 'رواتب آذار (March salaries)', 'debit' => 8_000, 'credit' => 0],
                ['account_id' => $accounts['183']->id,  'name' => 'رواتب آذار (March salaries)', 'debit' => 0,     'credit' => 8_000],
            ]
        );

        // Sanity check — trial balance must be exactly zero across the company's CoA.
        $trial = 0.0;
        foreach ($accounts as $account) {
            $trial += $svc->getAccountBalance($account);
        }
        $trial = round($trial, 2);

        $this->command?->info(sprintf(
            'Accounting seeded — %s: %d posted entries, 1 draft, trial balance %.2f.',
            $company->name,
            $postedCount,
            $trial
        ));
    }

    // ── Inventory ─────────────────────────────────────────────────────────────

    private function seedInventory(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) return;

        Auth::login($admin);
        try {
            $this->seedInventoryData();
        } finally {
            Auth::logout();
        }
    }

    private function seedInventoryData(): void
    {
        $warehouseSvc = app(WarehouseService::class);
        $productSvc   = app(InventoryProductService::class);
        $pickingSvc   = app(PickingService::class);
        $scrapSvc     = app(ScrapService::class);

        $acme      = Company::where('name', 'Acme Holdings')->first();
        $techStart = Company::where('name', 'TechStart Europe')->first();
        $gulf      = Company::where('name', 'Gulf Operations LLC')->first();

        $units = Uom::where('name', 'Units')->first();
        $kg    = Uom::where('name', 'kg')->first() ?? $units;

        $supplierLoc = Location::where('usage', 'supplier')->whereNull('company_id')->first();
        $customerLoc = Location::where('usage', 'customer')->whereNull('company_id')->first();
        $scrapLoc    = Location::where('scrap_location', true)->whereNull('company_id')->first();

        // ── Acme Holdings ─────────────────────────────────────────────────
        if ($acme && $units) {
            // Skip if already seeded
            if (Product::where('company_id', $acme->id)->exists()) {
                $this->command?->info('Inventory already seeded for Acme Holdings — skipping.');
            } else {
                $this->seedAcmeInventory($acme, $warehouseSvc, $productSvc, $pickingSvc, $scrapSvc, $units, $kg, $supplierLoc, $customerLoc, $scrapLoc);
            }
        }

        // ── TechStart Europe ──────────────────────────────────────────────
        if ($techStart && $units) {
            if (!Product::where('company_id', $techStart->id)->exists()) {
                $this->seedTechStartInventory($techStart, $warehouseSvc, $productSvc, $pickingSvc, $units, $supplierLoc, $customerLoc);
            }
        }

        // ── Gulf Operations LLC ───────────────────────────────────────────
        if ($gulf && $units) {
            if (!Product::where('company_id', $gulf->id)->exists()) {
                $this->seedGulfInventory($gulf, $warehouseSvc, $productSvc, $pickingSvc, $units, $supplierLoc);
            }
        }
    }

    private function seedAcmeInventory(
        Company $company,
        WarehouseService $warehouseSvc,
        InventoryProductService $productSvc,
        PickingService $pickingSvc,
        ScrapService $scrapSvc,
        Uom $units,
        Uom $kg,
        ?Location $supplierLoc,
        ?Location $customerLoc,
        ?Location $scrapLoc,
    ): void {
        // Warehouse
        $warehouse = Warehouse::where('company_id', $company->id)->first()
            ?? $warehouseSvc->create(['company_id' => $company->id, 'name' => 'Acme Main Warehouse', 'short_name' => 'AMW', 'active' => true]);

        $stockLoc = $warehouse->stockLocation;

        // Product categories (global — no company_id)
        $catElec = ProductCategory::firstOrCreate(['name' => 'Electronics'],    ['active' => true]);
        $catOff  = ProductCategory::firstOrCreate(['name' => 'Office Supplies'], ['active' => true]);
        $catSvc  = ProductCategory::firstOrCreate(['name' => 'Services'],        ['active' => true]);

        // Products — storable, consumable, service; tracking: none / lot / serial
        $laptop = $productSvc->create(['company_id' => $company->id, 'category_id' => $catElec->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Dell Laptop 15"',       'internal_reference' => 'EL-001', 'product_type' => 'storable',   'tracking' => 'none', 'cost' => 750.00,  'sale_price' => 1100.00, 'active' => true]);
        $monitor = $productSvc->create(['company_id' => $company->id, 'category_id' => $catElec->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => '27" 4K Monitor',         'internal_reference' => 'EL-002', 'product_type' => 'storable',   'tracking' => 'none', 'cost' => 320.00,  'sale_price' => 480.00,  'active' => true]);
        $keyboard = $productSvc->create(['company_id' => $company->id, 'category_id' => $catElec->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Mechanical Keyboard',   'internal_reference' => 'EL-003', 'product_type' => 'storable',   'tracking' => 'none', 'cost' => 45.00,   'sale_price' => 89.00,   'active' => true]);
        $mouse = $productSvc->create(['company_id' => $company->id, 'category_id' => $catElec->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Wireless Mouse',           'internal_reference' => 'EL-004', 'product_type' => 'storable',   'tracking' => 'none', 'cost' => 18.00,   'sale_price' => 35.00,   'active' => true]);
        $paper = $productSvc->create(['company_id' => $company->id, 'category_id' => $catOff->id,  'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'A4 Copy Paper (500 sh)',   'internal_reference' => 'OF-001', 'product_type' => 'consumable', 'tracking' => 'none', 'cost' => 4.50,    'sale_price' => 8.00,    'active' => true]);
        $toner = $productSvc->create(['company_id' => $company->id, 'category_id' => $catOff->id,  'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Laser Toner Cartridge',    'internal_reference' => 'OF-002', 'product_type' => 'consumable', 'tracking' => 'none', 'cost' => 28.00,   'sale_price' => 55.00,   'active' => true]);
        $productSvc->create(['company_id' => $company->id, 'category_id' => $catSvc->id,  'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'IT Consulting (per hour)',          'internal_reference' => 'SV-001', 'product_type' => 'service',    'tracking' => 'none',   'cost' => 50.00,   'sale_price' => 120.00,  'active' => true]);

        $receiptOp  = OperationType::where('company_id', $company->id)->where('code', 'incoming')->first();
        $deliveryOp = OperationType::where('company_id', $company->id)->where('code', 'outgoing')->first();

        if (!$receiptOp || !$stockLoc || !$supplierLoc) {
            $this->command?->warn('Acme: missing operation type or location, skipping transfers.');
            return;
        }

        // Receipt 1 — laptops, monitors, keyboards (validated → stock in)
        $r1 = $pickingSvc->create([
            'company_id' => $company->id, 'operation_type_id' => $receiptOp->id,
            'location_src_id' => $supplierLoc->id, 'location_dest_id' => $stockLoc->id,
            'origin' => 'PO/2026/001', 'scheduled_date' => now()->subDays(60), 'active' => true,
        ], [
            ['product_id' => $laptop->id,   'uom_id' => $units->id, 'product_qty' => 10,  'name' => $laptop->name],
            ['product_id' => $monitor->id,  'uom_id' => $units->id, 'product_qty' => 15,  'name' => $monitor->name],
            ['product_id' => $keyboard->id, 'uom_id' => $units->id, 'product_qty' => 30,  'name' => $keyboard->name],
        ]);
        $pickingSvc->confirm($r1);
        $pickingSvc->validate($r1->fresh());

        // Receipt 2 — mice, paper, toner (validated → stock in)
        $r2 = $pickingSvc->create([
            'company_id' => $company->id, 'operation_type_id' => $receiptOp->id,
            'location_src_id' => $supplierLoc->id, 'location_dest_id' => $stockLoc->id,
            'origin' => 'PO/2026/002', 'scheduled_date' => now()->subDays(45), 'active' => true,
        ], [
            ['product_id' => $mouse->id,  'uom_id' => $units->id, 'product_qty' => 50,  'name' => $mouse->name],
            ['product_id' => $paper->id,  'uom_id' => $units->id, 'product_qty' => 200, 'name' => $paper->name],
            ['product_id' => $toner->id,  'uom_id' => $units->id, 'product_qty' => 20,  'name' => $toner->name],
        ]);
        $pickingSvc->confirm($r2);
        $pickingSvc->validate($r2->fresh());

        if ($deliveryOp && $customerLoc) {
            // Delivery 1 — monitors + keyboards to customer (validated → stock out)
            $d1 = $pickingSvc->create([
                'company_id' => $company->id, 'operation_type_id' => $deliveryOp->id,
                'location_src_id' => $stockLoc->id, 'location_dest_id' => $customerLoc->id,
                'origin' => 'SO/2026/015', 'scheduled_date' => now()->subDays(30), 'active' => true,
            ], [
                ['product_id' => $monitor->id,  'uom_id' => $units->id, 'product_qty' => 5,  'name' => $monitor->name],
                ['product_id' => $keyboard->id, 'uom_id' => $units->id, 'product_qty' => 10, 'name' => $keyboard->name],
            ]);
            $pickingSvc->confirm($d1);
            $pickingSvc->checkAvailability($d1->fresh());
            $pickingSvc->validate($d1->fresh());

            // Delivery 2 — mice pending (confirmed, not validated)
            $d2 = $pickingSvc->create([
                'company_id' => $company->id, 'operation_type_id' => $deliveryOp->id,
                'location_src_id' => $stockLoc->id, 'location_dest_id' => $customerLoc->id,
                'origin' => 'SO/2026/021', 'scheduled_date' => now()->addDays(3), 'active' => true,
            ], [
                ['product_id' => $mouse->id, 'uom_id' => $units->id, 'product_qty' => 15, 'name' => $mouse->name],
            ]);
            $pickingSvc->confirm($d2);
            $pickingSvc->checkAvailability($d2->fresh());
        }

        // Scrap — 2 defective keyboards written off
        if ($scrapLoc) {
            $scrap = $scrapSvc->create([
                'company_id' => $company->id, 'product_id' => $keyboard->id,
                'uom_id' => $units->id, 'location_id' => $stockLoc->id,
                'scrap_location_id' => $scrapLoc->id, 'scrap_qty' => 2,
            ]);
            $scrapSvc->validate($scrap);
        }

        $this->command?->info("Inventory seeded for {$company->name}: 7 products, 2 receipts, 2 deliveries, 1 scrap.");
    }

    private function seedTechStartInventory(
        Company $company,
        WarehouseService $warehouseSvc,
        InventoryProductService $productSvc,
        PickingService $pickingSvc,
        Uom $units,
        ?Location $supplierLoc,
        ?Location $customerLoc,
    ): void {
        $warehouse = Warehouse::where('company_id', $company->id)->first()
            ?? $warehouseSvc->create(['company_id' => $company->id, 'name' => 'TechStart Warehouse', 'short_name' => 'TSE', 'active' => true]);

        $stockLoc = $warehouse->stockLocation;

        $catHW = ProductCategory::firstOrCreate(['name' => 'Hardware'],  ['active' => true]);
        $catSW = ProductCategory::firstOrCreate(['name' => 'Licensing'], ['active' => true]);

        $router = $productSvc->create(['company_id' => $company->id, 'category_id' => $catHW->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Enterprise Router',       'internal_reference' => 'HW-001', 'product_type' => 'storable',   'tracking' => 'none', 'cost' => 200.00, 'sale_price' => 380.00, 'active' => true]);
        $switch = $productSvc->create(['company_id' => $company->id, 'category_id' => $catHW->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => '24-Port Network Switch', 'internal_reference' => 'HW-002', 'product_type' => 'storable',   'tracking' => 'none',   'cost' => 150.00, 'sale_price' => 275.00, 'active' => true]);
        $ups    = $productSvc->create(['company_id' => $company->id, 'category_id' => $catHW->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'UPS 1500VA',              'internal_reference' => 'HW-003', 'product_type' => 'storable',   'tracking' => 'none', 'cost' => 95.00,  'sale_price' => 175.00, 'active' => true]);
        $productSvc->create(['company_id' => $company->id, 'category_id' => $catSW->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Annual Software License',          'internal_reference' => 'SW-001', 'product_type' => 'service',    'tracking' => 'none',   'cost' => 80.00,  'sale_price' => 150.00, 'active' => true]);

        $receiptOp  = OperationType::where('company_id', $company->id)->where('code', 'incoming')->first();
        $deliveryOp = OperationType::where('company_id', $company->id)->where('code', 'outgoing')->first();

        if (!$receiptOp || !$stockLoc || !$supplierLoc) return;

        $r1 = $pickingSvc->create([
            'company_id' => $company->id, 'operation_type_id' => $receiptOp->id,
            'location_src_id' => $supplierLoc->id, 'location_dest_id' => $stockLoc->id,
            'origin' => 'PO/TSE/001', 'scheduled_date' => now()->subDays(20), 'active' => true,
        ], [
            ['product_id' => $router->id, 'uom_id' => $units->id, 'product_qty' => 20, 'name' => $router->name],
            ['product_id' => $switch->id, 'uom_id' => $units->id, 'product_qty' => 15, 'name' => $switch->name],
            ['product_id' => $ups->id,    'uom_id' => $units->id, 'product_qty' => 10, 'name' => $ups->name],
        ]);
        $pickingSvc->confirm($r1);
        $pickingSvc->validate($r1->fresh());

        if ($deliveryOp && $customerLoc) {
            $d1 = $pickingSvc->create([
                'company_id' => $company->id, 'operation_type_id' => $deliveryOp->id,
                'location_src_id' => $stockLoc->id, 'location_dest_id' => $customerLoc->id,
                'origin' => 'SO/TSE/008', 'scheduled_date' => now()->subDays(7), 'active' => true,
            ], [
                ['product_id' => $switch->id, 'uom_id' => $units->id, 'product_qty' => 5, 'name' => $switch->name],
            ]);
            $pickingSvc->confirm($d1);
            $pickingSvc->checkAvailability($d1->fresh());
            $pickingSvc->validate($d1->fresh());
        }

        $this->command?->info("Inventory seeded for {$company->name}: 4 products, 1 receipt, 1 delivery.");
    }

    private function seedGulfInventory(
        Company $company,
        WarehouseService $warehouseSvc,
        InventoryProductService $productSvc,
        PickingService $pickingSvc,
        Uom $units,
        ?Location $supplierLoc,
    ): void {
        $warehouse = Warehouse::where('company_id', $company->id)->first()
            ?? $warehouseSvc->create(['company_id' => $company->id, 'name' => 'Gulf Main Depot', 'short_name' => 'GLD', 'active' => true]);

        $stockLoc = $warehouse->stockLocation;

        $catSafety = ProductCategory::firstOrCreate(['name' => 'Safety Equipment'],  ['active' => true]);
        $catTools  = ProductCategory::firstOrCreate(['name' => 'Tools & Equipment'], ['active' => true]);

        $helmet  = $productSvc->create(['company_id' => $company->id, 'category_id' => $catSafety->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Hard Hat (Type II)',   'internal_reference' => 'SF-001', 'product_type' => 'storable', 'tracking' => 'none', 'cost' => 12.00, 'sale_price' => 25.00, 'active' => true]);
        $vest    = $productSvc->create(['company_id' => $company->id, 'category_id' => $catSafety->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Hi-Vis Safety Vest',  'internal_reference' => 'SF-002', 'product_type' => 'storable', 'tracking' => 'none', 'cost' => 8.00,  'sale_price' => 18.00, 'active' => true]);
        $gloves  = $productSvc->create(['company_id' => $company->id, 'category_id' => $catSafety->id, 'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Safety Gloves (pair)', 'internal_reference' => 'SF-003', 'product_type' => 'consumable', 'tracking' => 'none', 'cost' => 3.50, 'sale_price' => 7.00,  'active' => true]);
        $productSvc->create(['company_id' => $company->id, 'category_id' => $catTools->id,  'uom_id' => $units->id, 'uom_po_id' => $units->id, 'name' => 'Industrial Drill Set',            'internal_reference' => 'TL-001', 'product_type' => 'storable', 'tracking' => 'none', 'cost' => 180.00, 'sale_price' => 320.00, 'active' => true]);

        $receiptOp = OperationType::where('company_id', $company->id)->where('code', 'incoming')->first();

        if (!$receiptOp || !$stockLoc || !$supplierLoc) return;

        $r1 = $pickingSvc->create([
            'company_id' => $company->id, 'operation_type_id' => $receiptOp->id,
            'location_src_id' => $supplierLoc->id, 'location_dest_id' => $stockLoc->id,
            'origin' => 'PO/GULF/001', 'scheduled_date' => now()->subDays(15), 'active' => true,
        ], [
            ['product_id' => $helmet->id, 'uom_id' => $units->id, 'product_qty' => 100, 'name' => $helmet->name],
            ['product_id' => $vest->id,   'uom_id' => $units->id, 'product_qty' => 80,  'name' => $vest->name],
            ['product_id' => $gloves->id, 'uom_id' => $units->id, 'product_qty' => 200, 'name' => $gloves->name],
        ]);
        $pickingSvc->confirm($r1);
        $pickingSvc->validate($r1->fresh());

        $this->command?->info("Inventory seeded for {$company->name}: 4 products, 1 receipt.");
    }
}
