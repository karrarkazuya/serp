<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountPayment;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;

class AccountPaymentController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = AccountPayment::query()->with(['journal', 'partner', 'pairedDocument', 'move']);

        empty($activeCompanyIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('company_id', $activeCompanyIds);

        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request, defaultColumn: 'date', defaultDirection: 'desc');
        $query->orderByDesc('id');

        $payments = $query->paginate(40)->withQueryString();

        return view('accounting.payments.index', compact('payments'));
    }

    public function show(AccountPayment $payment)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        $payment->load(['company', 'journal', 'partner', 'pairedDocument', 'move.lines.account']);

        return view('accounting.payments.show', compact('payment'));
    }
}
