<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountJournal;
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

    public function read(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = AccountPayment::query()->with(['journal', 'partner', 'pairedDocument']);

        empty($activeCompanyIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('company_id', $activeCompanyIds);

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountPayment::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['journal', 'partner', 'pairedDocument'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.payments.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'date', defaultDirection: 'desc');
        $query->orderByDesc('id');

        $payments = $query->paginate(40)->withQueryString();

        return view('accounting.payments.index', compact('payments'));
    }

    public function show(AccountPayment $payment)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        $payment->load(['company', 'journal', 'partner', 'pairedDocument', 'move.lines.account', 'destinationAccount']);

        return view('accounting.payments.show', compact('payment'));
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.create'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        $paymentMethods = AccountPayment::PAYMENT_METHODS;

        return view('accounting.payments.create', compact('defaultCompanyId', 'paymentMethods'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.create'), 403);

        $data = $request->validate([
            'journal_id'             => ['required', 'integer', 'exists:account_journals,id'],
            'payment_type'           => ['required', 'in:inbound,outbound'],
            'partner_id'             => ['nullable', 'integer', 'exists:contacts,id'],
            'date'                   => ['required', 'date'],
            'amount'                 => ['required', 'numeric', 'gt:0'],
            'currency'               => ['nullable', 'string', 'max:10'],
            'memo'                   => ['nullable', 'string', 'max:255'],
            'payment_method'         => ['nullable', 'string', 'max:64'],
            'bank_reference'         => ['nullable', 'string', 'max:255'],
            'cheque_number'          => ['nullable', 'string', 'max:255'],
            'destination_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ]);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $journal = AccountJournal::findOrFail($data['journal_id']);
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);
        abort_unless(in_array($journal->type, ['bank', 'cash']), 422);

        if (!empty($data['destination_account_id'])) {
            $destAccount = \App\Models\Accounting\Account::findOrFail($data['destination_account_id']);
            abort_unless(in_array($destAccount->company_id, $activeCompanyIds), 403);
        }

        try {
            $payment = DB::transaction(fn () => $this->accounting->createStandalonePayment($data));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', 'Payment saved as draft.');
    }

    public function confirm(Request $request, AccountPayment $payment)
    {
        abort_unless(auth()->user()->hasPermission('accounting.write'), 403);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        try {
            DB::transaction(fn () => $this->accounting->confirmPayment($payment));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', 'Payment confirmed.');
    }

    public function resetDraft(Request $request, AccountPayment $payment)
    {
        abort_unless(auth()->user()->hasPermission('accounting.write'), 403);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        try {
            DB::transaction(fn () => $this->accounting->resetPaymentToDraft($payment));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', 'Payment reset to draft.');
    }

    public function cancel(Request $request, AccountPayment $payment)
    {
        abort_unless(auth()->user()->hasPermission('accounting.write'), 403);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        try {
            DB::transaction(fn () => $this->accounting->cancelPayment($payment));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', 'Payment cancelled.');
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
