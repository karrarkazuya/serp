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
        $this->authorize('viewAny', AccountPayment::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        // forCompanies() is fail-closed (see AccountPayment::scopeForCompanies).
        $query = AccountPayment::query()
            ->with(['journal', 'partner', 'pairedDocument'])
            ->forCompanies($activeCompanyIds);

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
        $this->authorize('view', $payment);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        $payment->load(['company', 'journal', 'partner', 'pairedDocument', 'move.lines.account', 'destinationAccount']);

        return view('accounting.payments.show', compact('payment'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', AccountPayment::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        $paymentMethods = AccountPayment::PAYMENT_METHODS;

        return view('accounting.payments.create', compact('defaultCompanyId', 'paymentMethods'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', AccountPayment::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // Step 1: bind the journal first so every other FK can be scoped to its
        // company_id at the validation layer (instead of a post-validate
        // findOrFail + abort_unless that's fragile under refactor).
        $journalId = (int) $request->input('journal_id');
        $journal   = AccountJournal::find($journalId);
        abort_unless($journal && in_array($journal->company_id, $activeCompanyIds, true), 403);
        abort_unless(in_array($journal->type, ['bank', 'cash'], true), 422, 'Payment journal must be of type bank or cash.');

        // Step 2: scope every downstream FK to the journal's company. Without
        // these, a user with two active companies (A+B) could pick `journal_id`
        // = A's bank journal and `destination_account_id` = B's receivable
        // account — the standalone-payment service would then write an
        // account_move stamped `company_id = A` whose lines touch a Company-B
        // account. Cross-tenant ledger pollution.
        $journalCompanyId   = (int) $journal->company_id;
        $accountInJournalCo = \Illuminate\Validation\Rule::exists('accounts', 'id')
            ->where(fn ($q) => $q->where('company_id', $journalCompanyId)->where('active', true));
        $partnerInActiveCo  = \Illuminate\Validation\Rule::exists('contacts', 'id')
            ->where(function ($q) use ($activeCompanyIds) {
                empty($activeCompanyIds)
                    ? $q->whereRaw('1 = 0')
                    : $q->whereIn('company_id', $activeCompanyIds);
            });

        $data = $request->validate([
            'journal_id'             => ['required', 'integer', \Illuminate\Validation\Rule::in([$journalId])],
            'payment_type'           => ['required', 'in:inbound,outbound'],
            'partner_id'             => ['nullable', 'integer', $partnerInActiveCo],
            'date'                   => ['required', 'date'],
            'amount'                 => ['required', 'numeric', 'gt:0'],
            'currency'               => ['nullable', 'string', 'max:10'],
            'memo'                   => ['nullable', 'string', 'max:255'],
            'payment_method'         => ['nullable', 'string', 'max:64', \Illuminate\Validation\Rule::in(array_keys(AccountPayment::PAYMENT_METHODS))],
            'bank_reference'         => ['nullable', 'string', 'max:255'],
            'cheque_number'          => ['nullable', 'string', 'max:255'],
            'destination_account_id' => ['nullable', 'integer', $accountInJournalCo],
        ]);

        try {
            $payment = DB::transaction(fn () => $this->accounting->createStandalonePayment($data));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', __('accounting.payment_saved'));
    }

    public function confirm(Request $request, AccountPayment $payment)
    {
        // 'post' policy is now gated on accounting.post — confirming a payment
        // calls AccountingService::confirmPayment() which posts the underlying
        // account_move. Without this tightening, any accounting.write holder
        // could effectively post journal entries by funnelling them through
        // the payment confirm flow, bypassing the accounting.post permission
        // separation that gates direct AccountMove posting.
        $this->authorize('post', $payment);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        try {
            DB::transaction(fn () => $this->accounting->confirmPayment($payment));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', __('accounting.payment_confirmed'));
    }

    public function resetDraft(Request $request, AccountPayment $payment)
    {
        // Resetting cancels the underlying posted move, so this is also a
        // posting-class operation — gate on accounting.post via the policy.
        $this->authorize('post', $payment);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        try {
            DB::transaction(fn () => $this->accounting->resetPaymentToDraft($payment));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', __('accounting.payment_reset'));
    }

    public function cancel(Request $request, AccountPayment $payment)
    {
        // Cancelling cancels the underlying posted move — posting-class operation.
        $this->authorize('post', $payment);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        try {
            DB::transaction(fn () => $this->accounting->cancelPayment($payment));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.show', $payment)->with('success', __('accounting.payment_cancelled'));
    }

    public function unlink(Request $_request, AccountPayment $payment)
    {
        $this->authorize('delete', $payment);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);
        abort_unless($payment->isDraft(), 403);

        try {
            DB::transaction(fn () => $payment->delete());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.payments.index')->with('success', __('accounting.payment_deleted'));
    }

    public function addComment(Request $request, AccountPayment $payment)
    {
        $this->authorize('comment', $payment);
        abort_unless(in_array($payment->company_id, $this->companyContext->getActiveCompanyIds()), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $payment->logComment($request->body));

        return back()->with('success', __('accounting.comment_added'));
    }
}
