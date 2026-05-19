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

        return view('dashboard', compact('modules', 'allowedCompanies', 'activeCompanyIds', 'companyLabel'));
    }
}
