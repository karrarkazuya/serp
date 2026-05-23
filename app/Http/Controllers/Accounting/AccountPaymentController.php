<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountPayment;
use App\Services\Accounting\AccountingService;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountPaymentController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly AccountingService $accounting,
    ) {}

    public function create(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.create'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('accounting.payments.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.create'), 403);

        $data = $request->validate([
            'journal_id'   => ['required', 'integer', 'exists:account_journals,id'],
            'payment_type' => ['required', 'in:inbound,outbound'],
            'partner_id'   => ['nullable', 'integer', 'exists:contacts,id'],
            'date'         => ['required', 'date'],
            'amount'       => ['required', 'numeric', 'gt:0'],
            'currency'     => ['nullable', 'string', 'max:10'],
            'memo'         => ['nullable', 'string', 'max:255'],
        ]);

        // Gate: journal must belong to an active company
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $journal = \App\Models\Accounting\AccountJournal::findOrFail($data['journal_id']);
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);
        abort_unless(in_array($journal->type, ['bank', 'cash']), 422);

        try {
            $payment = DB::transaction(fn () => $this->accounting->createStandalonePayment($data));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', 'Payment registered.');
    }

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

    public function addComment(Request $request, AccountPayment $payment)
    {
        $this->authorize('comment', $payment);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $payment->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
