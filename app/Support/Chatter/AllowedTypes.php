<?php

namespace App\Support\Chatter;

use App\Models\Workflow\Procedure;
use App\Models\Workflow\Ticket;
use App\Services\Company\CompanyContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Single source of truth for which models can host chatter, what permission
 * each requires, and which are company-scoped. Used by both the chatter API
 * (read/write messages) and the chatter file-serving redirect — splitting
 * the lists across two controllers caused them to drift before.
 *
 * To register a new chatter target:
 *   1. Add it to READ_PERMISSIONS (required).
 *   2. Add it to WRITE_PERMISSIONS (required to allow posting comments).
 *   3. If its records carry a company_id boundary, add it to COMPANY_SCOPED.
 */
class AllowedTypes
{
    public const READ_PERMISSIONS = [
        // Contacts
        'App\Models\Contacts\Contact'                    => 'contacts.read',
        // Settings
        'App\Models\Settings\Company'                    => 'companies.read',
        // Users
        'App\Models\User'                                => 'users.read',
        // Workflow
        'App\Models\Workflow\Ticket'                     => 'workflow.tickets.read',
        'App\Models\Workflow\Procedure'                  => 'workflow.procedures.read',
        'App\Models\Workflow\TicketTemplate'             => 'workflow.config.read',
        'App\Models\Workflow\ProcedureTemplate'          => 'workflow.config.read',
        'App\Models\Workflow\Group'                      => 'workflow.config.read',
        'App\Models\Workflow\Manager'                    => 'workflow.config.read',
        'App\Models\Workflow\WorkflowUser'               => 'workflow.config.read',
        // Employees
        'App\Models\Employees\Attendance'                => 'attendance.read',
        'App\Models\Employees\PlannedDay'                => 'planned_schedules.read',
        'App\Models\Employees\Department'                => 'employees.read',
        'App\Models\Employees\Employee'                  => 'employees.read',
        'App\Models\Employees\EmployeePosition'          => 'employees.read',
        'App\Models\Employees\EmployeeCertificate'       => 'employees.read',
        'App\Models\Employees\Contract'                  => 'employees.read',
        'App\Models\Employees\Job'                       => 'employees.read',
        'App\Models\Employees\DepartureReason'           => 'employees.read',
        'App\Models\Employees\EmploymentType'            => 'employees.read',
        'App\Models\Employees\EmployeeCategory'          => 'employees.read',
        'App\Models\Employees\WorkLocation'              => 'employees.read',
        'App\Models\Employees\ResourceCalendar'          => 'employees.read',
        'App\Models\Employees\SkillType'                 => 'employees.read',
        'App\Models\Employees\ResumeLineType'            => 'employees.read',
        'App\Models\Employees\Goal'                      => 'employees.read',
        'App\Models\Employees\Badge'                     => 'employees.read',
        'App\Models\Employees\Challenge'                 => 'employees.read',
        'App\Models\Employees\EmployeeBonus'             => 'employees.read',
        'App\Models\Employees\EmployeeAppreciation'      => 'employees.read',
        'App\Models\Employees\EmployeeSanction'          => 'employees.read',
        'App\Models\Employees\EmployeeReward'            => 'employees.read',
        'App\Models\Employees\EmployeeJobGrade'          => 'employees.read',
        // Accounting
        'App\Models\Accounting\Account'                  => 'accounting.read',
        'App\Models\Accounting\AccountJournal'           => 'accounting.read',
        'App\Models\Accounting\AccountMove'              => 'accounting.read',
        'App\Models\Accounting\AccountPayment'           => 'accounting.read',
        'App\Models\Accounting\AccountTax'               => 'accounting.read',
        'App\Models\Accounting\AccountingAccountGroup'   => 'accounting.read',
        'App\Models\Accounting\AccountingIncoterm'       => 'accounting.read',
        'App\Models\Accounting\AccountingPaymentTerm'    => 'accounting.read',
        'App\Models\Accounting\AccountingTaxGroup'       => 'accounting.read',
        'App\Models\Accounting\CurrencyRate'             => 'accounting.read',
        // Inventory
        'App\Models\Inventory\InventoryAdjustment'       => 'inventory.read',
        'App\Models\Inventory\Location'                  => 'inventory.read',
        'App\Models\Inventory\Lot'                       => 'inventory.read',
        'App\Models\Inventory\OperationType'             => 'inventory.read',
        'App\Models\Inventory\Picking'                   => 'inventory.read',
        'App\Models\Inventory\Product'                   => 'inventory.read',
        'App\Models\Inventory\ProductCategory'           => 'inventory.read',
        'App\Models\Inventory\PutawayRule'               => 'inventory.read',
        'App\Models\Inventory\ReorderRule'               => 'inventory.read',
        'App\Models\Inventory\Route'                     => 'inventory.read',
        'App\Models\Inventory\ScrapOrder'                => 'inventory.read',
        'App\Models\Inventory\Uom'                       => 'inventory.read',
        'App\Models\Inventory\UomCategory'               => 'inventory.read',
        'App\Models\Inventory\Warehouse'                 => 'inventory.read',
    ];

    public const WRITE_PERMISSIONS = [
        'App\Models\Contacts\Contact'                    => 'contacts.write',
        'App\Models\Settings\Company'                    => 'companies.write',
        'App\Models\User'                                => 'users.write',
        'App\Models\Workflow\Ticket'                     => 'workflow.tickets.write',
        'App\Models\Workflow\Procedure'                  => 'workflow.procedures.write',
        'App\Models\Workflow\TicketTemplate'             => 'workflow.config.write',
        'App\Models\Workflow\ProcedureTemplate'          => 'workflow.config.write',
        'App\Models\Workflow\Group'                      => 'workflow.config.write',
        'App\Models\Workflow\Manager'                    => 'workflow.config.write',
        'App\Models\Workflow\WorkflowUser'               => 'workflow.config.write',
        'App\Models\Employees\Attendance'                => 'attendance.write',
        'App\Models\Employees\PlannedDay'                => 'planned_schedules.write',
        'App\Models\Employees\Department'                => 'employees.write',
        'App\Models\Employees\Employee'                  => 'employees.write',
        'App\Models\Employees\EmployeePosition'          => 'employees.write',
        'App\Models\Employees\EmployeeCertificate'       => 'employees.write',
        'App\Models\Employees\Contract'                  => 'employees.write',
        'App\Models\Employees\Job'                       => 'employees.write',
        'App\Models\Employees\DepartureReason'           => 'employees.write',
        'App\Models\Employees\EmploymentType'            => 'employees.write',
        'App\Models\Employees\EmployeeCategory'          => 'employees.write',
        'App\Models\Employees\WorkLocation'              => 'employees.write',
        'App\Models\Employees\ResourceCalendar'          => 'employees.write',
        'App\Models\Employees\SkillType'                 => 'employees.write',
        'App\Models\Employees\ResumeLineType'            => 'employees.write',
        'App\Models\Employees\Goal'                      => 'employees.write',
        'App\Models\Employees\Badge'                     => 'employees.write',
        'App\Models\Employees\Challenge'                 => 'employees.write',
        'App\Models\Employees\EmployeeBonus'             => 'employees.write',
        'App\Models\Employees\EmployeeAppreciation'      => 'employees.write',
        'App\Models\Employees\EmployeeSanction'          => 'employees.write',
        'App\Models\Employees\EmployeeReward'            => 'employees.write',
        'App\Models\Employees\EmployeeJobGrade'          => 'employees.write',
        'App\Models\Accounting\Account'                  => 'accounting.write',
        'App\Models\Accounting\AccountJournal'           => 'accounting.write',
        'App\Models\Accounting\AccountMove'              => 'accounting.write',
        'App\Models\Accounting\AccountPayment'           => 'accounting.write',
        'App\Models\Accounting\AccountTax'               => 'accounting.write',
        'App\Models\Accounting\AccountingAccountGroup'   => 'accounting.write',
        'App\Models\Accounting\AccountingIncoterm'       => 'accounting.write',
        'App\Models\Accounting\AccountingPaymentTerm'    => 'accounting.write',
        'App\Models\Accounting\AccountingTaxGroup'       => 'accounting.write',
        'App\Models\Accounting\CurrencyRate'             => 'accounting.write',
        'App\Models\Inventory\InventoryAdjustment'       => 'inventory.write',
        'App\Models\Inventory\Location'                  => 'inventory.write',
        'App\Models\Inventory\Lot'                       => 'inventory.write',
        'App\Models\Inventory\OperationType'             => 'inventory.write',
        'App\Models\Inventory\Picking'                   => 'inventory.write',
        'App\Models\Inventory\Product'                   => 'inventory.write',
        'App\Models\Inventory\ProductCategory'           => 'inventory.write',
        'App\Models\Inventory\PutawayRule'               => 'inventory.write',
        'App\Models\Inventory\ReorderRule'               => 'inventory.write',
        'App\Models\Inventory\Route'                     => 'inventory.write',
        'App\Models\Inventory\ScrapOrder'                => 'inventory.write',
        'App\Models\Inventory\Uom'                       => 'inventory.write',
        'App\Models\Inventory\UomCategory'               => 'inventory.write',
        'App\Models\Inventory\Warehouse'                 => 'inventory.write',
    ];

    /**
     * Types whose chatter must be gated by the actor's active companies.
     * Excluded on purpose:
     *   - User                                          (company_id is a default-company preference)
     *   - Settings\Company                              (the record IS the company)
     *   - Workflow Group / Manager / WorkflowUser       (cross-company config)
     *   - Workflow TicketTemplate / ProcedureTemplate   (cross-company config)
     *   - Inventory Uom / UomCategory / ProductCategory (cross-company config)
     *   - Accounting Incoterm                           (cross-company config)
     */
    public const COMPANY_SCOPED = [
        'App\Models\Contacts\Contact',
        'App\Models\Workflow\Ticket',
        'App\Models\Workflow\Procedure',
        'App\Models\Employees\Attendance',
        'App\Models\Employees\PlannedDay',
        'App\Models\Employees\Department',
        'App\Models\Employees\Employee',
        'App\Models\Employees\EmployeePosition',
        'App\Models\Employees\EmployeeCertificate',
        'App\Models\Employees\Contract',
        'App\Models\Employees\Job',
        'App\Models\Employees\WorkLocation',
        'App\Models\Employees\ResourceCalendar',
        'App\Models\Accounting\Account',
        'App\Models\Accounting\AccountJournal',
        'App\Models\Accounting\AccountMove',
        'App\Models\Accounting\AccountPayment',
        'App\Models\Accounting\AccountTax',
        'App\Models\Accounting\AccountingAccountGroup',
        'App\Models\Accounting\AccountingPaymentTerm',
        'App\Models\Accounting\AccountingTaxGroup',
        'App\Models\Accounting\CurrencyRate',
        'App\Models\Inventory\InventoryAdjustment',
        'App\Models\Inventory\Location',
        'App\Models\Inventory\Lot',
        'App\Models\Inventory\OperationType',
        'App\Models\Inventory\Picking',
        'App\Models\Inventory\Product',
        'App\Models\Inventory\PutawayRule',
        'App\Models\Inventory\ReorderRule',
        'App\Models\Inventory\Route',
        'App\Models\Inventory\ScrapOrder',
        'App\Models\Inventory\Warehouse',
    ];

    /**
     * Resolve a chatter target record and enforce viewer-level + company-scope
     * authorization for the calling action. Throws via `abort()` on any failure.
     *
     * @param  string  $modelType   Fully-qualified class name from the request.
     * @param  int     $modelId     Primary key of the target record.
     * @param  string  $ability     Policy ability to check on viewer-scoped records ('view' or 'comment').
     */
    public static function authorizeRecordAccess(
        string $modelType,
        int $modelId,
        string $ability,
        CompanyContextService $companyContext,
        Request $request,
    ): void {
        // Viewer-level scope (ticket forUser / procedure visibility).
        $viewerScoped = match ($modelType) {
            'App\Models\Workflow\Ticket'    => Ticket::find($modelId),
            'App\Models\Workflow\Procedure' => Procedure::find($modelId),
            default                         => null,
        };

        if ($viewerScoped !== null) {
            \Illuminate\Support\Facades\Gate::forUser($request->user())->authorize($ability, $viewerScoped);
        }

        if (!in_array($modelType, self::COMPANY_SCOPED, true)) {
            return;
        }

        $record = $viewerScoped ?? (class_exists($modelType) ? $modelType::find($modelId) : null);
        abort_if($record === null, 404);

        self::assertWithinActiveCompanies($record, $companyContext);
    }

    public static function assertWithinActiveCompanies(Model $record, CompanyContextService $companyContext): void
    {
        $companyId = $record->getAttribute('company_id');

        // Records explicitly shared across companies (company_id = null) are allowed.
        if ($companyId === null) {
            return;
        }

        abort_unless(
            in_array((int) $companyId, $companyContext->getActiveCompanyIds(), true),
            403
        );
    }
}
