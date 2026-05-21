<?php

namespace App\Providers;

use App\Models\Chat\ChatRoom;
use App\Models\Contacts\Contact;
use App\Models\Employees\Contract;
use App\Models\Employees\Department as EmployeeDepartment;
use App\Models\Employees\DepartureReason;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeBankAccount;
use App\Models\Employees\EmployeeCategory;
use App\Models\Employees\EmployeeDependent;
use App\Models\Employees\EmployeeDocument;
use App\Models\Employees\EmployeeEmergencyContact;
use App\Models\Employees\EmployeeSkill;
use App\Models\Employees\Job;
use App\Models\Employees\ResourceCalendar;
use App\Models\Employees\ResourceCalendarAttendance;
use App\Models\Employees\Skill;
use App\Models\Employees\SkillLevel;
use App\Models\Employees\SkillType;
use App\Models\Employees\WorkLocation;
use App\Models\Employees\ResumeLineType;
use App\Models\Employees\EmploymentType;
use App\Models\Employees\Badge;
use App\Models\Employees\Challenge;
use App\Models\Employees\Goal;
use App\Models\Security\Permission;
use App\Models\Security\Role;
use App\Models\Settings\Company;
use App\Models\Settings\Setting;
use App\Models\Notification;
use App\Models\User;
use App\Models\Workflow\Department;
use App\Models\Workflow\Group;
use App\Models\Workflow\Procedure;
use App\Models\Workflow\ProcedureTemplate;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\TicketTemplate;
use App\Models\Workflow\Manager;
use App\Models\Workflow\WorkflowSharedLink;
use App\Models\Chat\ChatMessageFile;
use App\Models\Workflow\WorkflowRecordInput;
use App\Models\Workflow\WorkflowUser;
use App\Observers\AuditableObserver;
use App\Policies\Chat\ChatRoomPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContactPolicy;
use App\Policies\Employees\BadgePolicy;
use App\Policies\Employees\ChallengePolicy;
use App\Policies\Employees\ContractPolicy;
use App\Policies\Employees\DepartmentPolicy as EmployeeDepartmentPolicy;
use App\Policies\Employees\DepartureReasonPolicy;
use App\Policies\Employees\EmployeePolicy;
use App\Policies\Employees\EmploymentTypePolicy;
use App\Policies\Employees\GoalPolicy;
use App\Policies\Employees\JobPolicy;
use App\Policies\Employees\ResumeLineTypePolicy;
use App\Policies\Employees\SkillTypePolicy;
use App\Policies\Employees\WorkLocationPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingPolicy;
use App\Policies\UserPolicy;
use App\Policies\Workflow\DepartmentPolicy;
use App\Policies\Workflow\GroupPolicy;
use App\Policies\Workflow\ManagerPolicy;
use App\Policies\Workflow\ProcedurePolicy;
use App\Policies\Workflow\ProcedureTemplatePolicy;
use App\Policies\Workflow\TicketPolicy;
use App\Policies\Workflow\TicketTemplatePolicy;
use App\Policies\Workflow\WorkflowUserPolicy;
use App\Services\Chatter\ChatterService;
use App\Services\Company\CompanyContextService;
use App\Services\Company\CompanyService;
use App\Services\Contacts\ContactService;
use App\Services\Employees\DepartmentService as EmployeeDepartmentService;
use App\Services\Employees\EmployeeService;
use App\Services\Employees\JobService;
use App\Services\Workflow\ProcedureService;
use App\Services\Workflow\TicketService;
use App\Services\Workflow\WorkflowConfigService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatterService::class);
        $this->app->singleton(ContactService::class);
        $this->app->singleton(CompanyContextService::class);
        $this->app->singleton(CompanyService::class);
        $this->app->singleton(EmployeeService::class);
        $this->app->singleton(EmployeeDepartmentService::class);
        $this->app->singleton(JobService::class);
        $this->app->singleton(TicketService::class);
        $this->app->singleton(ProcedureService::class);
        $this->app->singleton(WorkflowConfigService::class);
    }

    public function boot(): void
    {
        Blade::component('list', \App\View\Components\TableList::class);

        Gate::policy(ChatRoom::class, ChatRoomPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(EmployeeDepartment::class, EmployeeDepartmentPolicy::class);
        Gate::policy(Job::class, JobPolicy::class);
        Gate::policy(WorkLocation::class, WorkLocationPolicy::class);
        Gate::policy(Contract::class, ContractPolicy::class);
        Gate::policy(DepartureReason::class, DepartureReasonPolicy::class);
        Gate::policy(SkillType::class, SkillTypePolicy::class);
        Gate::policy(ResumeLineType::class, ResumeLineTypePolicy::class);
        Gate::policy(EmploymentType::class, EmploymentTypePolicy::class);
        Gate::policy(Badge::class, BadgePolicy::class);
        Gate::policy(Challenge::class, ChallengePolicy::class);
        Gate::policy(Goal::class, GoalPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Manager::class, ManagerPolicy::class);
        Gate::policy(WorkflowUser::class, WorkflowUserPolicy::class);
        Gate::policy(TicketTemplate::class, TicketTemplatePolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(ProcedureTemplate::class, ProcedureTemplatePolicy::class);
        Gate::policy(Procedure::class, ProcedurePolicy::class);

        foreach ([
            Notification::class,
            User::class,
            Company::class,
            Contact::class,
            \App\Models\Contacts\Tag::class,
            Role::class,
            Permission::class,
            Setting::class,
            \App\Models\Chatter\ChatterMessage::class,
            Group::class,
            Department::class,
            Manager::class,
            WorkflowUser::class,
            Ticket::class,
            TicketTemplate::class,
            Procedure::class,
            ProcedureTemplate::class,
            WorkflowSharedLink::class,
            WorkflowRecordInput::class,
            ChatMessageFile::class,
            // Employees module
            Employee::class,
            EmployeeDepartment::class,
            Job::class,
            WorkLocation::class,
            ResourceCalendar::class,
            ResourceCalendarAttendance::class,
            SkillType::class,
            Skill::class,
            SkillLevel::class,
            EmployeeSkill::class,
            Contract::class,
            EmployeeDocument::class,
            EmployeeBankAccount::class,
            EmployeeEmergencyContact::class,
            EmployeeDependent::class,
            EmployeeCategory::class,
            DepartureReason::class,
            ResumeLineType::class,
            EmploymentType::class,
            Badge::class,
            Challenge::class,
            Goal::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }

        // Share company context with all views for the navbar switcher
        View::composer('components.navbar', function ($view) {
            if (auth()->check()) {
                $context  = app(CompanyContextService::class);
                $allowed  = auth()->user()->companies()->where('active', true)->orderBy('name')->get();
                $activeIds = $context->getActiveCompanyIds();

                $view->with([
                    'allowedCompanies' => $allowed,
                    'activeCompanyIds' => $activeIds,
                    'companyLabel'     => $context->getLabel(),
                ]);
            }
        });
    }
}
