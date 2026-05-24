<?php

namespace App\Http\Controllers\Api\Chatter;

use App\Http\Controllers\Controller;
use App\Models\Chatter\ChatterMessage;
use App\Models\Workflow\Ticket;
use App\Models\Workflow\Procedure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatterController extends Controller
{
    /**
     * Maps allowed model_type values to the permission required to read/write them.
     * Any model_type not in this list is rejected with 403.
     */
    private const ALLOWED_TYPES = [
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

    private const WRITE_PERMISSION = [
        // Contacts
        'App\Models\Contacts\Contact'                    => 'contacts.write',
        // Settings
        'App\Models\Settings\Company'                    => 'companies.write',
        // Users
        'App\Models\User'                                => 'users.write',
        // Workflow
        'App\Models\Workflow\Ticket'                     => 'workflow.tickets.write',
        'App\Models\Workflow\Procedure'                  => 'workflow.procedures.write',
        'App\Models\Workflow\TicketTemplate'             => 'workflow.config.write',
        'App\Models\Workflow\ProcedureTemplate'          => 'workflow.config.write',
        'App\Models\Workflow\Group'                      => 'workflow.config.write',
        'App\Models\Workflow\Manager'                    => 'workflow.config.write',
        'App\Models\Workflow\WorkflowUser'               => 'workflow.config.write',
        // Employees
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
        // Accounting
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
        // Inventory
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

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id'   => 'required|integer',
        ]);

        $modelType = $request->model_type;
        abort_unless(array_key_exists($modelType, self::ALLOWED_TYPES), 403);
        abort_unless($request->user()->hasPermission(self::ALLOWED_TYPES[$modelType]), 403);

        $this->authorizeRecordAccess($modelType, (int) $request->model_id, 'view');

        $messages = ChatterMessage::where('model_type', $modelType)
            ->where('model_id', $request->model_id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'model_type'   => 'required|string',
            'model_id'     => 'required|integer',
            'body'         => 'required|string|max:5000',
            'message_type' => 'in:log,comment,system',
        ]);

        $modelType = $request->model_type;
        abort_unless(array_key_exists($modelType, self::WRITE_PERMISSION), 403);
        abort_unless($request->user()->hasPermission(self::WRITE_PERMISSION[$modelType]), 403);

        $this->authorizeRecordAccess($modelType, (int) $request->model_id, 'comment');

        $message = ChatterMessage::create([
            'model_type'   => $modelType,
            'model_id'     => $request->model_id,
            'user_id'      => auth()->id(),
            'message_type' => $request->input('message_type', 'comment'),
            'body'         => $request->body,
        ]);

        return response()->json(['message' => 'Message added.', 'data' => $message->load('user')], 201);
    }

    /**
     * For record types that enforce viewer-level access (tickets, procedures),
     * verify the authenticated user can actually see/act on that specific record.
     * Other types (contacts, companies, users, config) are permission-only.
     */
    private function authorizeRecordAccess(string $modelType, int $modelId, string $ability): void
    {
        $record = match ($modelType) {
            'App\Models\Workflow\Ticket'    => Ticket::findOrFail($modelId),
            'App\Models\Workflow\Procedure' => Procedure::findOrFail($modelId),
            default                         => null,
        };

        if ($record !== null) {
            $this->authorize($ability, $record);
        }
    }
}
