<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountMoveLine;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;

class AccountMoveLineController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        abort_unless($request->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $query = AccountMoveLine::query()
            ->with(['move', 'journal', 'account', 'partner', 'company']);

        if (empty($activeCompanyIds)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->forCompanies($activeCompanyIds);
        }

        SearchFilters::apply($query, $request);

        $stateFilter = $request->query('state');
        if ($stateFilter && in_array($stateFilter, ['draft', 'posted', 'cancelled'], true)) {
            $query->where('state', $stateFilter);
        }

        foreach (['journal_id', 'account_id', 'partner_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, (int) $request->query($field));
            }
        }

        $totalsQuery = clone $query;
        $totalDebit = round((float) $totalsQuery->sum('debit'), 2);
        $totalCredit = round((float) (clone $query)->sum('credit'), 2);
        $totalBalance = round($totalDebit - $totalCredit, 2);

        SortsTable::apply($query, $request, defaultColumn: 'date', defaultDirection: 'desc');
        $query->orderByDesc('id');

        $items = $query->paginate(80)->withQueryString();

        return view('accounting.items.index', compact('items', 'totalDebit', 'totalCredit', 'totalBalance'));
    }
}
