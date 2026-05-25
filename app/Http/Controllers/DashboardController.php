<?php

namespace App\Http\Controllers;

use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, CompanyContextService $companyContext)
    {
        $user = $request->user();

        $modules = collect([
            [
                'key'         => 'employees',
                'label'       => 'Employees',
                'description' => 'Staff, departments & HR',
                'route'       => 'employees.index',
                'permission'  => 'employees.read',
                'color'       => 'purple',
                'icon'        => 'employees',
            ],
            [
                'key'         => 'contacts',
                'label'       => 'Contacts',
                'description' => 'Customers, suppliers & partners',
                'route'       => 'contacts.index',
                'permission'  => 'contacts.read',
                'color'       => 'purple',
                'icon'        => 'contacts',
            ],
            [
                'key'         => 'workflow',
                'label'       => 'Workflow',
                'description' => 'Tickets, procedures & tasks',
                'route'       => 'workflow.dashboard',
                'permission'  => 'workflow.tickets.read',
                'color'       => 'blue',
                'icon'        => 'workflow',
            ],
            [
                'key'         => 'inventory',
                'label'       => 'Inventory',
                'description' => 'Products, warehouses & stock',
                'route'       => 'inventory.dashboard',
                'permission'  => 'inventory.read',
                'color'       => 'orange',
                'icon'        => 'inventory',
            ],
            [
                'key'         => 'accounting',
                'label'       => 'Accounting',
                'description' => 'Chart of accounts, journals & entries',
                'route'       => 'accounting.dashboard',
                'permission'  => 'accounting.read',
                'color'       => 'green',
                'icon'        => 'accounting',
            ],
            [
                'key'         => 'settings',
                'label'       => 'Settings',
                'description' => 'Users, roles & configuration',
                'route'       => 'settings.index',
                'permission'  => 'settings.read',
                'color'       => 'slate',
                'icon'        => 'settings',
            ],
        ])->filter(fn ($m) => $user->hasPermission($m['permission']));

        $allowedCompanies = $user->companies()->where('active', true)->orderBy('name')->get();
        $activeCompanyIds = $companyContext->getActiveCompanyIds();
        $companyLabel = $companyContext->getLabel();

        // Self-service widget: only present if the user can see their own
        // requests + approve requests assigned to them.
        $selfRequestsWidget = null;
        if ($user->hasPermission('attendance.self.request')) {
            $myEmployee = \App\Models\Employees\Employee::where('user_id', $user->id)->first();
            $selfRequestsWidget = [
                'my_pending'       => \App\Models\Employees\EmployeeRequest::query()
                    ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
                    ->where('state', \App\Models\Employees\EmployeeRequest::STATE_PENDING)
                    ->count(),
                'awaiting_my_action' => \App\Models\Employees\EmployeeRequest::query()
                    ->where('manager_status', \App\Models\Employees\EmployeeRequest::STATE_PENDING)
                    ->where('state', \App\Models\Employees\EmployeeRequest::STATE_PENDING)
                    ->whereHas('employee.attendanceManager', fn ($q) => $q->where('user_id', $user->id))
                    ->count(),
                'balance' => $myEmployee
                    ? \App\Models\Employees\EmployeeBalance::firstOrCreate(['employee_id' => $myEmployee->id])
                    : null,
            ];
        }

        return view('dashboard', compact('modules', 'allowedCompanies', 'activeCompanyIds', 'companyLabel', 'selfRequestsWidget'));
    }
}
