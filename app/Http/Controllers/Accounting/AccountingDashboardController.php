<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountMoveLine;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;

class AccountingDashboardController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function index(Request $_request)
    {
        abort_unless($this->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        // Laravel's whereIn(...) compiles to "WHERE 0 = 1" when the array is
        // empty — i.e., fail-closed by default — so a user with no active
        // companies gets zero counts on every metric below.

        $accountsCount = Account::query()
            ->whereIn('company_id', $activeCompanyIds)
            ->where('active', true)
            ->count();

        $journals = AccountJournal::query()
            ->whereIn('company_id', $activeCompanyIds)
            ->where('active', true)
            ->orderBy('type')
            ->orderBy('code')
            ->get();

        $draftCount = AccountMove::query()
            ->whereIn('company_id', $activeCompanyIds)
            ->where('state', 'draft')
            ->count();

        $invoiceCount = AccountMove::query()
            ->whereIn('company_id', $activeCompanyIds)
            ->where('move_type', 'out_invoice')
            ->count();

        $billCount = AccountMove::query()
            ->whereIn('company_id', $activeCompanyIds)
            ->where('move_type', 'in_invoice')
            ->count();

        $postedCount = AccountMove::query()
            ->whereIn('company_id', $activeCompanyIds)
            ->where('state', 'posted')
            ->count();

        $journalItemsCount = AccountMoveLine::query()
            ->whereIn('company_id', $activeCompanyIds)
            ->count();

        $recentMoves = AccountMove::query()
            ->whereIn('company_id', $activeCompanyIds)
            ->with(['journal', 'partner'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('accounting.dashboard', compact(
            'accountsCount', 'journals', 'draftCount', 'invoiceCount', 'billCount', 'postedCount', 'journalItemsCount', 'recentMoves'
        ));
    }

    private function user()
    {
        return auth()->user();
    }
}
