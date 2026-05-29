<?php

namespace App\Providers;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingAccountGroup;
use App\Models\Accounting\AccountingIncoterm;
use App\Models\Accounting\AccountingPaymentTerm;
use App\Models\Accounting\AccountingPaymentTermLine;
use App\Models\Accounting\AccountingTaxGroup;
use App\Models\Accounting\AccountJournal;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\Inventory\Location;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Move;
use App\Models\Inventory\MoveLine;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\ProductSupplier;
use App\Models\Inventory\PutawayRule;
use App\Models\Inventory\Quant;
use App\Models\Inventory\ReorderRule;
use App\Models\Inventory\Route;
use App\Models\Inventory\RouteRule;
use App\Models\Inventory\ScrapOrder;
use App\Models\Inventory\Uom;
use App\Models\Inventory\UomCategory;
use App\Models\Inventory\Warehouse;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountMoveLine;
use App\Models\Accounting\AccountPartialReconcile;
use App\Models\Accounting\AccountPayment;
use App\Models\Accounting\AccountTax;
use App\Models\Accounting\CurrencyRate;
use App\Models\Chat\ChatRoom;
use App\Models\Contacts\Contact;
use App\Models\Employees\Attendance;
use App\Models\Employees\EmployeeBalance;
use App\Models\Employees\EmployeeRequest;
use App\Models\Employees\PlannedDay;
use App\Models\Employees\PlannedRSchedule;
use App\Models\Employees\RequestBalanceConfig;
use App\Models\Employees\RequestSubtype;
use App\Models\Employees\Contract;
use App\Models\Employees\Department as EmployeeDepartment;
use App\Models\Employees\DepartureReason;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeBankAccount;
use App\Models\Employees\EmployeeCategory;
use App\Models\Employees\EmployeeDependent;
use App\Models\Employees\EmployeeCertificate;
use App\Models\Employees\EmployeeDocument;
use App\Models\Employees\EmployeePosition;
use App\Models\Employees\EmployeeBonus;
use App\Models\Employees\EmployeeAppreciation;
use App\Models\Employees\EmployeeSanction;
use App\Models\Employees\EmployeeReward;
use App\Models\Employees\EmployeeJobGrade;
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
use App\Models\UserFavoriteSearch;
use App\Models\Workflow\Group;
use App\Models\Workflow\Procedure;
use App\Models\Workflow\ProcedureTemplate;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\TicketTemplate;
use App\Models\Workflow\Manager;
use App\Models\Workflow\WorkflowSharedLink;
use App\Models\Chat\ChatMessageFile;
use App\Models\File;
use App\Models\Workflow\ProcedureStep;
use App\Models\Workflow\TicketProcedureLine;
use App\Models\Workflow\WorkflowRecordInput;
use App\Models\Workflow\WorkflowTemplateInput;
use App\Models\Workflow\WorkflowTemplateInputOption;
use App\Models\Workflow\WorkflowUser;
use App\Observers\AuditableObserver;
use App\Observers\CompanyAccountingObserver;
use App\Services\Accounting\AccountingService;
use App\Services\FileService;
use App\Policies\Accounting\AccountingAccountGroupPolicy;
use App\Policies\Accounting\AccountingIncotermPolicy;
use App\Policies\Accounting\AccountingPaymentTermPolicy;
use App\Policies\Accounting\AccountingTaxGroupPolicy;
use App\Policies\Accounting\AccountJournalPolicy;
use App\Policies\Accounting\AccountPaymentPolicy;
use App\Policies\Accounting\AccountMovePolicy;
use App\Policies\Accounting\AccountPolicy;
use App\Policies\Accounting\AccountTaxPolicy;
use App\Policies\Accounting\CurrencyRatePolicy;
use App\Policies\Inventory\InventoryAdjustmentPolicy;
use App\Policies\Inventory\LocationPolicy;
use App\Policies\Inventory\LotPolicy;
use App\Policies\Inventory\OperationTypePolicy;
use App\Policies\Inventory\PickingPolicy;
use App\Policies\Inventory\ProductCategoryPolicy;
use App\Policies\Inventory\ProductPolicy;
use App\Policies\Inventory\PutawayRulePolicy;
use App\Policies\Inventory\ReorderRulePolicy;
use App\Policies\Inventory\RoutePolicy;
use App\Policies\Inventory\ScrapOrderPolicy;
use App\Policies\Inventory\UomPolicy;
use App\Policies\Inventory\WarehousePolicy;
use App\Policies\Chat\ChatRoomPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContactPolicy;
use App\Policies\Employees\AttendancePolicy;
use App\Policies\Employees\EmployeeRequestPolicy;
use App\Policies\Employees\PlannedDayPolicy;
use App\Policies\Employees\RequestSubtypePolicy;
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
use App\Services\Inventory\AdjustmentService;
use App\Services\Inventory\PickingService;
use App\Services\Inventory\ProductService;
use App\Services\Inventory\ScrapService;
use App\Services\Inventory\WarehouseService;
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
        $this->app->singleton(FileService::class);
        $this->app->singleton(AccountingService::class);
        $this->app->singleton(ProductService::class);
        $this->app->singleton(WarehouseService::class);
        $this->app->singleton(PickingService::class);
        $this->app->singleton(ScrapService::class);
        $this->app->singleton(AdjustmentService::class);
    }

    public function boot(): void
    {
        Blade::component('list', \App\View\Components\TableList::class);

        Gate::policy(ChatRoom::class, ChatRoomPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(PlannedDay::class, PlannedDayPolicy::class);
        Gate::policy(EmployeeRequest::class, EmployeeRequestPolicy::class);
        Gate::policy(RequestSubtype::class, RequestSubtypePolicy::class);
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
        Gate::policy(Manager::class, ManagerPolicy::class);
        Gate::policy(WorkflowUser::class, WorkflowUserPolicy::class);
        Gate::policy(TicketTemplate::class, TicketTemplatePolicy::class);
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(ProcedureTemplate::class, ProcedureTemplatePolicy::class);
        Gate::policy(Procedure::class, ProcedurePolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(AccountJournal::class, AccountJournalPolicy::class);
        Gate::policy(AccountMove::class, AccountMovePolicy::class);
        Gate::policy(AccountTax::class, AccountTaxPolicy::class);
        Gate::policy(CurrencyRate::class, CurrencyRatePolicy::class);
        Gate::policy(AccountingPaymentTerm::class, AccountingPaymentTermPolicy::class);
        Gate::policy(AccountingIncoterm::class, AccountingIncotermPolicy::class);
        Gate::policy(AccountingTaxGroup::class, AccountingTaxGroupPolicy::class);
        Gate::policy(AccountingAccountGroup::class, AccountingAccountGroupPolicy::class);
        Gate::policy(AccountPayment::class, AccountPaymentPolicy::class);
        // Inventory
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Picking::class, PickingPolicy::class);
        Gate::policy(Lot::class, LotPolicy::class);
        Gate::policy(ScrapOrder::class, ScrapOrderPolicy::class);
        Gate::policy(ReorderRule::class, ReorderRulePolicy::class);
        Gate::policy(InventoryAdjustment::class, InventoryAdjustmentPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(OperationType::class, OperationTypePolicy::class);
        Gate::policy(Route::class, RoutePolicy::class);
        Gate::policy(PutawayRule::class, PutawayRulePolicy::class);
        Gate::policy(Uom::class, UomPolicy::class);

        foreach ([
            Notification::class,
            User::class,
            UserFavoriteSearch::class,
            Company::class,
            Contact::class,
            \App\Models\Contacts\Tag::class,
            Role::class,
            Permission::class,
            Setting::class,
            \App\Models\Chatter\ChatterMessage::class,
            Group::class,
            Manager::class,
            WorkflowUser::class,
            Ticket::class,
            TicketTemplate::class,
            Procedure::class,
            ProcedureStep::class,
            ProcedureTemplate::class,
            TicketProcedureLine::class,
            WorkflowSharedLink::class,
            WorkflowRecordInput::class,
            WorkflowTemplateInput::class,
            WorkflowTemplateInputOption::class,
            ChatMessageFile::class,
            File::class,
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
            EmployeeCertificate::class,
            EmployeePosition::class,
            EmployeeBonus::class,
            EmployeeAppreciation::class,
            EmployeeSanction::class,
            EmployeeReward::class,
            EmployeeJobGrade::class,
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
            Attendance::class,
            PlannedDay::class,
            PlannedRSchedule::class,
            RequestSubtype::class,
            RequestBalanceConfig::class,
            EmployeeBalance::class,
            EmployeeRequest::class,
            // Inventory module
            Product::class,
            ProductCategory::class,
            ProductSupplier::class,
            Warehouse::class,
            Location::class,
            OperationType::class,
            Route::class,
            RouteRule::class,
            PutawayRule::class,
            Picking::class,
            Move::class,
            MoveLine::class,
            Lot::class,
            Quant::class,
            ScrapOrder::class,
            ReorderRule::class,
            InventoryAdjustment::class,
            InventoryAdjustmentLine::class,
            Uom::class,
            UomCategory::class,
            // Accounting module
            Account::class,
            AccountJournal::class,
            AccountMove::class,
            AccountMoveLine::class,
            AccountPayment::class,
            AccountPartialReconcile::class,
            AccountTax::class,
            \App\Models\Accounting\Currency::class,
            CurrencyRate::class,
            AccountingPaymentTerm::class,
            AccountingPaymentTermLine::class,
            AccountingIncoterm::class,
            AccountingTaxGroup::class,
            AccountingAccountGroup::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }

        // Auto-install the Iraqi UAS chart of accounts + journals on every new company.
        Company::observe(CompanyAccountingObserver::class);

        // D8 (Odoo parity): block direct mutation/deletion of posted move lines.
        // Reset-to-draft is the supported path for editing posted entries.
        \App\Models\Accounting\AccountMoveLine::observe(\App\Observers\Accounting\AccountMoveLineObserver::class);

        // Inventory equivalent: a `done` Move has already moved units between
        // quants — mutating its qty/locations/product after the fact would
        // desynchronise the ledger from the stock that physically moved. The
        // picking service drives the state machine; this observer is the
        // backstop for any direct Eloquent write that slips past it.
        \App\Models\Inventory\Move::observe(\App\Observers\Inventory\MoveObserver::class);
        // Same protection one level down: move lines on a done move are the
        // per-lot trail that produced the quant updates. MoveLine has no
        // `state` of its own — the observer reads the parent Move's state.
        \App\Models\Inventory\MoveLine::observe(\App\Observers\Inventory\MoveLineObserver::class);

        // When an employee's working schedule changes, wipe + regenerate their
        // planned-days buffer so the new calendar takes effect from tomorrow.
        Employee::observe(\App\Observers\Employees\EmployeeScheduleObserver::class);

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
