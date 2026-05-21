<?php

namespace Database\Seeders;

use App\Models\Contacts\Contact;
use App\Models\Contacts\Tag;
use App\Models\Employees\Contract;
use App\Models\Employees\Department;
use App\Models\Employees\DepartureReason;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCategory;
use App\Models\Employees\EmployeeSkill;
use App\Models\Employees\Job;
use App\Models\Employees\ResourceCalendar;
use App\Models\Employees\Skill;
use App\Models\Employees\SkillLevel;
use App\Models\Employees\SkillType;
use App\Models\Employees\WorkLocation;
use App\Models\Settings\Company;
use App\Models\User;
use App\Models\Workflow\Group;
use App\Models\Workflow\Manager;
use App\Models\Workflow\ProcedureStep;
use App\Models\Workflow\ProcedureTemplate;
use App\Models\Workflow\TicketTemplate;
use App\Models\Workflow\WorkflowTemplateInput;
use App\Models\Workflow\WorkflowUser;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCompanies();
        $this->seedTags();
        $this->seedContacts();
        $this->seedWorkflow();
        $this->seedEmployees();
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
                'resolve_max_duration' => 168,
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
                'resolve_max_duration'  => 24,
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
                'resolve_max_duration'  => 48,
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
                'resolve_max_duration'  => 8,
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

        $this->command->info('Employees seeded — ' . $allEmployees->count() . ' records created.');
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
}
