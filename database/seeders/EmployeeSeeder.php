<?php

namespace Database\Seeders;

use App\Models\Employees\Department;
use App\Models\Employees\DepartureReason;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCategory;
use App\Models\Employees\EmployeeSkill;
use App\Models\Employees\Contract;
use App\Models\Employees\Job;
use App\Models\Employees\ResourceCalendar;
use App\Models\Employees\SkillLevel;
use App\Models\Employees\SkillType;
use App\Models\Employees\Skill;
use App\Models\Employees\WorkLocation;
use App\Models\Settings\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $this->command->warn('No company found. Run CompanySeeder first.');
            return;
        }

        // ── Departure Reasons ─────────────────────────────────────────
        $reasons = [];
        foreach (['Resigned', 'Contract End', 'Fired', 'Retired', 'Mutual Agreement'] as $name) {
            $reasons[$name] = DepartureReason::firstOrCreate(['name' => $name], ['active' => true]);
        }

        // ── Work Locations ────────────────────────────────────────────
        $locations = [];
        foreach (['Head Office — Baghdad', 'Remote', 'Branch — Basra', 'Branch — Erbil'] as $name) {
            $locations[$name] = WorkLocation::firstOrCreate(
                ['name' => $name, 'company_id' => $company->id],
                ['active' => true]
            );
        }

        // ── Resource Calendars ────────────────────────────────────────
        // 0=Sat,1=Sun,2=Mon,3=Tue,4=Wed,5=Thu,6=Fri
        $calStandard = $this->makeCalendar($company->id, 'Standard 40 Hours (Sun–Thu)', 8.0, [
            [1, 'morning',   8.0,  12.0],
            [1, 'afternoon', 13.0, 17.0],
            [2, 'morning',   8.0,  12.0],
            [2, 'afternoon', 13.0, 17.0],
            [3, 'morning',   8.0,  12.0],
            [3, 'afternoon', 13.0, 17.0],
            [4, 'morning',   8.0,  12.0],
            [4, 'afternoon', 13.0, 17.0],
            [5, 'morning',   8.0,  12.0],
            [5, 'afternoon', 13.0, 17.0],
        ]);

        $calMorning = $this->makeCalendar($company->id, 'Morning Shift (Sun–Thu 8:00–14:00)', 6.0, [
            [1, 'morning', 8.0, 14.0],
            [2, 'morning', 8.0, 14.0],
            [3, 'morning', 8.0, 14.0],
            [4, 'morning', 8.0, 14.0],
            [5, 'morning', 8.0, 14.0],
        ]);

        $calFull = $this->makeCalendar($company->id, 'Full Week (Sat–Thu)', 8.0, [
            [0, 'morning',   8.5,  12.0],
            [0, 'afternoon', 13.0, 17.5],
            [1, 'morning',   8.5,  12.0],
            [1, 'afternoon', 13.0, 17.5],
            [2, 'morning',   8.5,  12.0],
            [2, 'afternoon', 13.0, 17.5],
            [3, 'morning',   8.5,  12.0],
            [3, 'afternoon', 13.0, 17.5],
            [4, 'morning',   8.5,  12.0],
            [4, 'afternoon', 13.0, 17.5],
            [5, 'morning',   8.5,  12.0],
            [5, 'afternoon', 13.0, 17.5],
        ]);

        // ── Departments ───────────────────────────────────────────────
        $deptExec  = Department::firstOrCreate(['name' => 'Executive Office',       'company_id' => $company->id], ['active' => true]);
        $deptSoft  = Department::firstOrCreate(['name' => 'Software Development',   'company_id' => $company->id], ['active' => true, 'parent_id' => $deptExec->id]);
        $deptDigit = Department::firstOrCreate(['name' => 'Digital Solutions',      'company_id' => $company->id], ['active' => true, 'parent_id' => $deptSoft->id]);
        $deptHR    = Department::firstOrCreate(['name' => 'Human Resources',        'company_id' => $company->id], ['active' => true, 'parent_id' => $deptExec->id]);
        $deptFin   = Department::firstOrCreate(['name' => 'Finance',                'company_id' => $company->id], ['active' => true, 'parent_id' => $deptExec->id]);
        $deptOps   = Department::firstOrCreate(['name' => 'Operations',             'company_id' => $company->id], ['active' => true, 'parent_id' => $deptExec->id]);
        $deptSupp  = Department::firstOrCreate(['name' => 'Technical Support',      'company_id' => $company->id], ['active' => true, 'parent_id' => $deptOps->id]);

        // ── Job Positions ─────────────────────────────────────────────
        $jobs = [];
        foreach ([
            'CEO', 'CTO', 'Head of Software Solutions',
            'Senior Software Developer', 'Full Stack Developer', 'Backend Developer',
            'Database Administrator', 'UI/UX Designer',
            'HR Manager', 'HR Specialist',
            'Finance Manager', 'Financial Analyst',
            'Operations Manager', 'Technical Support Engineer',
        ] as $title) {
            $jobs[$title] = Job::firstOrCreate(['name' => $title, 'company_id' => $company->id], ['active' => true]);
        }

        // ── Employee Categories ───────────────────────────────────────
        $cats = [];
        foreach ([
            ['Management',    '#714B67'],
            ['Technical',     '#1f66d1'],
            ['Administrative','#13bfd7'],
            ['Finance',       '#f28b2e'],
            ['Senior Staff',  '#5a2ca0'],
        ] as [$name, $color]) {
            $cats[$name] = EmployeeCategory::firstOrCreate(['name' => $name], ['color' => $color, 'active' => true]);
        }

        // ── Skill Types + Skills + Levels ─────────────────────────────
        [$skillTypes, $skillLevels] = $this->seedSkills($company->id);

        // ── Employees ────────────────────────────────────────────────
        // Level 1: CEO
        $ceo = $this->makeEmployee([
            'name'          => 'رفل حسين',
            'name_en'       => 'Rafl Hussein',
            'family_name'   => 'Hussein',
            'job_title'     => 'Chief Executive Officer',
            'work_email'    => 'rafl.hussein@company.iq',
            'work_phone'    => '+964-770-001-0001',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1975-03-15',
            'hire_date'     => '2010-01-01',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 3,
            'certificate_level' => 'master',
            'study_field'   => 'Business Administration',
            'study_school'  => 'University of Baghdad',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptExec, $jobs['CEO'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management'], $cats['Senior Staff']]);

        // Level 2: CTO / Dept heads
        $cto = $this->makeEmployee([
            'name'          => 'كرار ستار',
            'name_en'       => 'Karrar Sattar',
            'family_name'   => 'Sattar',
            'job_title'     => 'Head of Software Solutions',
            'work_email'    => 'karrar.sattar@company.iq',
            'work_phone'    => '+964-770-001-0002',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1988-07-22',
            'hire_date'     => '2015-03-10',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 2,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Computer Science',
            'study_school'  => 'University of Technology',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptSoft, $jobs['Head of Software Solutions'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management'], $cats['Technical'], $cats['Senior Staff']], $ceo);

        $hrManager = $this->makeEmployee([
            'name'          => 'سارة محمد',
            'name_en'       => 'Sara Mohammed',
            'family_name'   => 'Mohammed',
            'job_title'     => 'HR Manager',
            'work_email'    => 'sara.mohammed@company.iq',
            'work_phone'    => '+964-770-001-0010',
            'nationality'   => 'Iraqi',
            'gender'        => 'female',
            'birthday'      => '1985-11-08',
            'hire_date'     => '2016-06-01',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 1,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Human Resources',
            'study_school'  => 'Al-Mustansiriyah University',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptHR, $jobs['HR Manager'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management']], $ceo);

        $finManager = $this->makeEmployee([
            'name'          => 'أحمد الكريم',
            'name_en'       => 'Ahmed Al-Kareem',
            'family_name'   => 'Al-Kareem',
            'job_title'     => 'Finance Manager',
            'work_email'    => 'ahmed.kareem@company.iq',
            'work_phone'    => '+964-770-001-0020',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1982-05-19',
            'hire_date'     => '2014-09-15',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 4,
            'certificate_level' => 'master',
            'study_field'   => 'Accounting',
            'study_school'  => 'University of Kufa',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptFin, $jobs['Finance Manager'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management'], $cats['Finance']], $ceo);

        $opsManager = $this->makeEmployee([
            'name'          => 'علي حسن',
            'name_en'       => 'Ali Hassan',
            'family_name'   => 'Hassan',
            'job_title'     => 'Operations Manager',
            'work_email'    => 'ali.hassan@company.iq',
            'work_phone'    => '+964-770-001-0030',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1980-09-02',
            'hire_date'     => '2013-02-20',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 3,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Engineering',
            'study_school'  => 'University of Basra',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptOps, $jobs['Operations Manager'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Management']], $ceo);

        // Level 3: Engineers / Developers
        $dev1 = $this->makeEmployee([
            'name'          => 'حيدر علي',
            'name_en'       => 'Hayder Ali',
            'family_name'   => 'Ali',
            'job_title'     => 'Full Stack Developer',
            'work_email'    => 'hayder.ali@company.iq',
            'work_phone'    => '+964-770-001-0003',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1992-04-10',
            'hire_date'     => '2019-01-15',
            'employment_status' => 'active',
            'marital_status'=> 'single',
            'children'      => 0,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Software Engineering',
            'study_school'  => 'University of Technology',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptDigit, $jobs['Full Stack Developer'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Technical']], $cto);

        $dev2 = $this->makeEmployee([
            'name'          => 'زينب خالد',
            'name_en'       => 'Zainab Khalid',
            'family_name'   => 'Khalid',
            'job_title'     => 'Backend Developer',
            'work_email'    => 'zainab.khalid@company.iq',
            'work_phone'    => '+964-770-001-0004',
            'nationality'   => 'Iraqi',
            'gender'        => 'female',
            'birthday'      => '1994-08-25',
            'hire_date'     => '2020-03-01',
            'employment_status' => 'active',
            'marital_status'=> 'single',
            'children'      => 0,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Computer Science',
            'study_school'  => 'University of Mosul',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptDigit, $jobs['Backend Developer'], $locations['Remote'], $calStandard, [$cats['Technical']], $cto);

        $dev3 = $this->makeEmployee([
            'name'          => 'محمد جاسم',
            'name_en'       => 'Mohammed Jassim',
            'family_name'   => 'Jassim',
            'job_title'     => 'Senior Software Developer',
            'work_email'    => 'mohammed.jassim@company.iq',
            'work_phone'    => '+964-770-001-0005',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1990-12-18',
            'hire_date'     => '2018-07-10',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 2,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Information Technology',
            'study_school'  => 'Nahrain University',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptSoft, $jobs['Senior Software Developer'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Technical'], $cats['Senior Staff']], $cto);

        $dba = $this->makeEmployee([
            'name'          => 'لؤي عبدالله',
            'name_en'       => 'Luay Abdullah',
            'family_name'   => 'Abdullah',
            'job_title'     => 'Database Administrator',
            'work_email'    => 'luay.abdullah@company.iq',
            'work_phone'    => '+964-770-001-0006',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1988-02-14',
            'hire_date'     => '2017-04-05',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 1,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Computer Engineering',
            'study_school'  => 'University of Baghdad',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptSoft, $jobs['Database Administrator'], $locations['Head Office — Baghdad'], $calFull, [$cats['Technical']], $cto);

        $designer = $this->makeEmployee([
            'name'          => 'نور الهدى',
            'name_en'       => 'Nour Al-Huda',
            'family_name'   => 'Al-Huda',
            'job_title'     => 'UI/UX Designer',
            'work_email'    => 'nour.alhuda@company.iq',
            'work_phone'    => '+964-770-001-0007',
            'nationality'   => 'Iraqi',
            'gender'        => 'female',
            'birthday'      => '1995-06-30',
            'hire_date'     => '2021-02-15',
            'employment_status' => 'active',
            'marital_status'=> 'single',
            'children'      => 0,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Graphic Design',
            'study_school'  => 'Academy of Fine Arts',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptDigit, $jobs['UI/UX Designer'], $locations['Remote'], $calMorning, [$cats['Technical']], $cto);

        $hr1 = $this->makeEmployee([
            'name'          => 'فاطمة رحيم',
            'name_en'       => 'Fatima Rahim',
            'family_name'   => 'Rahim',
            'job_title'     => 'HR Specialist',
            'work_email'    => 'fatima.rahim@company.iq',
            'work_phone'    => '+964-770-001-0011',
            'nationality'   => 'Iraqi',
            'gender'        => 'female',
            'birthday'      => '1993-09-17',
            'hire_date'     => '2020-08-01',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 1,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Human Resources Management',
            'study_school'  => 'Al-Mustansiriyah University',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptHR, $jobs['HR Specialist'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Administrative']], $hrManager);

        $fin1 = $this->makeEmployee([
            'name'          => 'عمر فاضل',
            'name_en'       => 'Omar Fadhil',
            'family_name'   => 'Fadhil',
            'job_title'     => 'Financial Analyst',
            'work_email'    => 'omar.fadhil@company.iq',
            'work_phone'    => '+964-770-001-0021',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1991-01-05',
            'hire_date'     => '2019-11-20',
            'employment_status' => 'active',
            'marital_status'=> 'married',
            'children'      => 2,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Finance',
            'study_school'  => 'University of Basra',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptFin, $jobs['Financial Analyst'], $locations['Head Office — Baghdad'], $calStandard, [$cats['Finance']], $finManager);

        $supp1 = $this->makeEmployee([
            'name'          => 'باسم طارق',
            'name_en'       => 'Basim Tariq',
            'family_name'   => 'Tariq',
            'job_title'     => 'Technical Support Engineer',
            'work_email'    => 'basim.tariq@company.iq',
            'work_phone'    => '+964-770-001-0031',
            'nationality'   => 'Iraqi',
            'gender'        => 'male',
            'birthday'      => '1996-03-22',
            'hire_date'     => '2022-01-10',
            'employment_status' => 'probation',
            'marital_status'=> 'single',
            'children'      => 0,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Telecommunications',
            'study_school'  => 'University of Baghdad',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptSupp, $jobs['Technical Support Engineer'], $locations['Head Office — Baghdad'], $calMorning, [$cats['Technical']], $opsManager);

        $supp2 = $this->makeEmployee([
            'name'          => 'رنا عبدالرزاق',
            'name_en'       => 'Rana Abdul-Razzaq',
            'family_name'   => 'Abdul-Razzaq',
            'job_title'     => 'Technical Support Engineer',
            'work_email'    => 'rana.razzaq@company.iq',
            'work_phone'    => '+964-770-001-0032',
            'nationality'   => 'Iraqi',
            'gender'        => 'female',
            'birthday'      => '1997-07-14',
            'hire_date'     => '2022-06-01',
            'employment_status' => 'active',
            'marital_status'=> 'single',
            'children'      => 0,
            'certificate_level' => 'bachelor',
            'study_field'   => 'Network Engineering',
            'study_school'  => 'Middle Technical University',
            'timezone'      => 'Asia/Baghdad',
        ], $company, $deptSupp, $jobs['Technical Support Engineer'], $locations['Branch — Basra'], $calMorning, [$cats['Technical']], $opsManager);

        // Update dept managers
        $deptExec->update(['manager_id' => $ceo->id]);
        $deptSoft->update(['manager_id' => $cto->id]);
        $deptDigit->update(['manager_id' => $cto->id]);
        $deptHR->update(['manager_id'   => $hrManager->id]);
        $deptFin->update(['manager_id'  => $finManager->id]);
        $deptOps->update(['manager_id'  => $opsManager->id]);
        $deptSupp->update(['manager_id' => $opsManager->id]);

        // ── Skills for developers ─────────────────────────────────────
        $this->assignSkills($dev1,    $skillTypes, $skillLevels, ['PHP' => 90, 'JavaScript' => 85, 'MySQL' => 75]);
        $this->assignSkills($dev2,    $skillTypes, $skillLevels, ['PHP' => 80, 'Python' => 70, 'MySQL' => 80]);
        $this->assignSkills($dev3,    $skillTypes, $skillLevels, ['PHP' => 95, 'JavaScript' => 90, 'MySQL' => 85]);
        $this->assignSkills($dba,     $skillTypes, $skillLevels, ['MySQL' => 95, 'PostgreSQL' => 80]);
        $this->assignSkills($designer,$skillTypes, $skillLevels, ['Figma' => 90, 'CSS' => 85]);
        $this->assignSkills($cto,     $skillTypes, $skillLevels, ['PHP' => 85, 'JavaScript' => 80, 'MySQL' => 75]);

        // ── Contracts ─────────────────────────────────────────────────
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
                'wage'          => match($emp->job?->name) {
                    'CEO'                       => 5000.00,
                    'Head of Software Solutions'=> 4000.00,
                    'HR Manager'                => 3000.00,
                    'Finance Manager'           => 3000.00,
                    'Operations Manager'        => 2800.00,
                    'Senior Software Developer' => 2500.00,
                    'Full Stack Developer'      => 2000.00,
                    'Backend Developer'         => 1800.00,
                    'Database Administrator'    => 2200.00,
                    'UI/UX Designer'            => 1700.00,
                    'Financial Analyst'         => 1600.00,
                    default                     => 1200.00,
                },
            ]);
            $emp->update(['contract_id' => $contract->id, 'wage' => $contract->wage]);
        }

        $this->command->info('EmployeeSeeder complete — ' . $allEmployees->count() . ' employees created.');
    }

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
                'company_id'          => $company->id,
                'department_id'       => $dept->id,
                'job_id'              => $job->id,
                'work_location_id'    => $location->id,
                'resource_calendar_id'=> $calendar->id,
                'parent_id'           => $manager?->id,
                'coach_id'            => $manager?->id,
                'expense_manager_id'  => $manager?->id,
                'attendance_manager_id' => $manager?->id,
                'payment_method'      => 'bank_transfer',
                'country'             => 'Iraq',
                'country_of_birth'    => 'Iraq',
                'active'              => true,
                'birthday'            => isset($data['birthday']) ? Carbon::parse($data['birthday']) : null,
                'hire_date'           => isset($data['hire_date']) ? Carbon::parse($data['hire_date']) : null,
                'first_contract_date' => isset($data['hire_date']) ? Carbon::parse($data['hire_date']) : null,
            ])
        );

        if ($categories) {
            $employee->categories()->syncWithoutDetaching(collect($categories)->pluck('id'));
        }

        return $employee;
    }

    private function seedSkills(int $companyId): array
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
                'Figma'      => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
                'CSS'        => [[10, 'Beginner'], [50, 'Intermediate'], [80, 'Advanced'], [100, 'Expert']],
            ],
        ];

        $skillTypes = [];
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

            // Find the type for this skill
            $type = $skillTypes[$skill->skillType?->name ?? ''] ?? null;
            if (!$type instanceof SkillType) {
                $type = $skill->skillType;
            }

            // Pick closest level
            $levels = $skillLevels[$skillName] ?? [];
            $level  = null;
            foreach (array_keys($levels) as $threshold) {
                if ($progress >= $threshold) $level = $levels[$threshold];
            }

            EmployeeSkill::firstOrCreate(
                ['employee_id' => $employee->id, 'skill_id' => $skill->id],
                [
                    'skill_type_id'  => $type?->id,
                    'skill_level_id' => $level?->id,
                ]
            );
        }
    }
}
