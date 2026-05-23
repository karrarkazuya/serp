<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreDocumentRequest;
use App\Http\Requests\Accounting\UpdateDocumentRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountTax;
use App\Services\Accounting\AccountingService;
use Illuminate\Validation\Rule;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountDocumentController extends Controller
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function invoices(Request $request)
    {
        return $this->read($request, 'out_invoice');
    }

    public function bills(Request $request)
    {
        return $this->read($request, 'in_invoice');
    }

    public function creditNotes(Request $request)
    {
        return $this->read($request, 'out_refund');
    }

    public function refunds(Request $request)
    {
        return $this->read($request, 'in_refund');
    }

    public function createInvoice(Request $request)
    {
        return $this->create($request, 'out_invoice');
    }

    public function createBill(Request $request)
    {
        return $this->create($request, 'in_invoice');
    }

    public function storeInvoice(StoreDocumentRequest $request)
    {
        return $this->store($request, 'out_invoice');
    }

    public function storeBill(StoreDocumentRequest $request)
    {
        return $this->store($request, 'in_invoice');
    }

    public function showInvoice(AccountMove $invoice)
    {
        return $this->show($invoice, 'out_invoice');
    }

    public function showBill(AccountMove $bill)
    {
        return $this->show($bill, 'in_invoice');
    }

    public function showCreditNote(AccountMove $creditNote)
    {
        return $this->show($creditNote, 'out_refund');
    }

    public function showRefund(AccountMove $refund)
    {
        return $this->show($refund, 'in_refund');
    }

    public function editInvoice(AccountMove $invoice)
    {
        return $this->edit($invoice, 'out_invoice');
    }

    public function editBill(AccountMove $bill)
    {
        return $this->edit($bill, 'in_invoice');
    }

    public function updateInvoice(UpdateDocumentRequest $request, AccountMove $invoice)
    {
        return $this->write($request, $invoice, 'out_invoice');
    }

    public function updateBill(UpdateDocumentRequest $request, AccountMove $bill)
    {
        return $this->write($request, $bill, 'in_invoice');
    }

    public function postInvoice(AccountMove $invoice)
    {
        return $this->post($invoice, 'out_invoice');
    }

    public function postBill(AccountMove $bill)
    {
        return $this->post($bill, 'in_invoice');
    }

    public function postCreditNote(AccountMove $creditNote)
    {
        return $this->post($creditNote, 'out_refund');
    }

    public function postRefund(AccountMove $refund)
    {
        return $this->post($refund, 'in_refund');
    }

    public function resetInvoice(AccountMove $invoice)
    {
        return $this->resetToDraft($invoice, 'out_invoice');
    }

    public function resetBill(AccountMove $bill)
    {
        return $this->resetToDraft($bill, 'in_invoice');
    }

    public function resetCreditNote(AccountMove $creditNote)
    {
        return $this->resetToDraft($creditNote, 'out_refund');
    }

    public function resetRefund(AccountMove $refund)
    {
        return $this->resetToDraft($refund, 'in_refund');
    }

    public function cancelInvoice(AccountMove $invoice)
    {
        return $this->cancel($invoice, 'out_invoice');
    }

    public function cancelBill(AccountMove $bill)
    {
        return $this->cancel($bill, 'in_invoice');
    }

    public function cancelCreditNote(AccountMove $creditNote)
    {
        return $this->cancel($creditNote, 'out_refund');
    }

    public function cancelRefund(AccountMove $refund)
    {
        return $this->cancel($refund, 'in_refund');
    }

    public function payInvoice(Request $request, AccountMove $invoice)
    {
        return $this->markPaid($request, $invoice, 'out_invoice');
    }

    public function payBill(Request $request, AccountMove $bill)
    {
        return $this->markPaid($request, $bill, 'in_invoice');
    }

    public function payCreditNote(Request $request, AccountMove $creditNote)
    {
        return $this->markPaid($request, $creditNote, 'out_refund');
    }

    public function payRefund(Request $request, AccountMove $refund)
    {
        return $this->markPaid($request, $refund, 'in_refund');
    }

    public function creditInvoice(AccountMove $invoice)
    {
        return $this->creditNote($invoice, 'out_invoice');
    }

    public function creditBill(AccountMove $bill)
    {
        return $this->creditNote($bill, 'in_invoice');
    }

    public function printInvoice(AccountMove $invoice)
    {
        return $this->print($invoice, 'out_invoice');
    }

    public function printBill(AccountMove $bill)
    {
        return $this->print($bill, 'in_invoice');
    }

    public function printCreditNote(AccountMove $creditNote)
    {
        return $this->print($creditNote, 'out_refund');
    }

    public function printRefund(AccountMove $refund)
    {
        return $this->print($refund, 'in_refund');
    }

    public function deleteInvoice(AccountMove $invoice)
    {
        return $this->unlink($invoice, 'out_invoice');
    }

    public function deleteBill(AccountMove $bill)
    {
        return $this->unlink($bill, 'in_invoice');
    }

    public function deleteCreditNote(AccountMove $creditNote)
    {
        return $this->unlink($creditNote, 'out_refund');
    }

    public function deleteRefund(AccountMove $refund)
    {
        return $this->unlink($refund, 'in_refund');
    }

    public function commentInvoice(Request $request, AccountMove $invoice)
    {
        return $this->addComment($request, $invoice, 'out_invoice');
    }

    public function commentBill(Request $request, AccountMove $bill)
    {
        return $this->addComment($request, $bill, 'in_invoice');
    }

    public function commentCreditNote(Request $request, AccountMove $creditNote)
    {
        return $this->addComment($request, $creditNote, 'out_refund');
    }

    public function commentRefund(Request $request, AccountMove $refund)
    {
        return $this->addComment($request, $refund, 'in_refund');
    }

    private function read(Request $request, string $moveType)
    {
        $this->authorize('viewAny', AccountMove::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = AccountMove::query()
            ->with(['journal', 'partner'])
            ->where('move_type', $moveType);

        empty($activeCompanyIds)
            ? $query->whereRaw('1 = 0')
            : $query->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        $stateFilter = $request->query('state');
        if ($stateFilter && in_array($stateFilter, ['draft', 'posted', 'cancelled'], true)) {
            $query->where('state', $stateFilter);
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountMove::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['journal', 'partner'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                $config  = $this->config($moveType);
                return view('accounting.documents.index', compact('groups', 'config'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'date', defaultDirection: 'desc');
        $query->orderByDesc('id');

        $documents = $query->paginate(40)->withQueryString();
        $config = $this->config($moveType);

        return view('accounting.documents.index', compact('documents', 'config'));
    }

    private function create(Request $_request, string $moveType)
    {
        $this->authorize('create', AccountMove::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        $config = $this->config($moveType);
        $defaults = $this->defaults($defaultCompanyId, $moveType);
        $availableTaxes = $this->taxesForCompany($defaults['company_id'] ?? $defaultCompanyId, $moveType);

        return view('accounting.documents.create', compact('config', 'defaultCompanyId', 'defaults', 'availableTaxes'));
    }

    private function store(StoreDocumentRequest $request, string $moveType)
    {
        $data = $request->validated();
        abort_unless($data['move_type'] === $moveType, 404);

        $items = $data['items'];
        $action = $data['action'] ?? 'save';
        unset($data['items'], $data['action']);

        try {
            $document = DB::transaction(function () use ($data, $items, $action) {
                $created = $this->accounting->createDocument($data, $items);
                if ($action === 'post') {
                    abort_unless(auth()->user()->hasPermission('accounting.post'), 403);
                    $created = $this->accounting->postMove($created);
                }
                return $created;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($this->config($moveType)['routes']['show'], $document)
            ->with('success', $document->isPosted() ? $this->config($moveType)['posted_message'] : $this->config($moveType)['saved_message']);
    }

    private function show(AccountMove $document, string $moveType)
    {
        $this->authorize('view', $document);
        $this->assertDocumentAccess($document, $moveType);

        $document->load(['journal', 'partner', 'company', 'paymentTerm', 'incoterm', 'payments.journal', 'lines.account', 'lines.partner', 'lines.taxes', 'lines.taxLine', 'creator', 'updater', 'poster', 'reversedMove', 'reversal']);
        $balance = $this->accounting->computeMoveBalance($document);
        $config = $this->config($moveType);
        $controlLine = $this->controlLine($document);
        $documentLines = $this->documentLines($document);
        $residual = $this->accounting->documentResidual($document);

        // Bank and cash journals for the Register Payment form
        $paymentJournals = AccountJournal::where('company_id', $document->company_id)
            ->whereIn('type', ['bank', 'cash'])
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type']);

        return view('accounting.documents.show', compact('document', 'config', 'balance', 'controlLine', 'documentLines', 'residual', 'paymentJournals'));
    }

    private function edit(AccountMove $document, string $moveType)
    {
        $this->authorize('update', $document);
        $this->assertDocumentAccess($document, $moveType);

        if (!$document->isDraft()) {
            return redirect()->route($this->config($moveType)['routes']['show'], $document)
                ->with('error', 'Only draft documents can be edited.');
        }

        $document->load(['journal', 'partner', 'company', 'paymentTerm', 'incoterm', 'lines.account', 'lines.taxes']);
        $config = $this->config($moveType);
        $defaultCompanyId = $document->company_id;
        $defaults = ['journal_id' => $document->journal_id, 'control_account_id' => $this->controlLine($document)?->account_id];
        $availableTaxes = $this->taxesForCompany($document->company_id, $moveType);

        return view('accounting.documents.edit', compact('document', 'config', 'defaultCompanyId', 'defaults', 'availableTaxes'));
    }

    private function write(UpdateDocumentRequest $request, AccountMove $document, string $moveType)
    {
        $this->authorize('update', $document);
        $this->assertDocumentAccess($document, $moveType);

        $data = $request->validated();
        abort_unless($data['move_type'] === $moveType, 404);

        $items = $data['items'];
        $action = $data['action'] ?? 'save';
        unset($data['items'], $data['action']);

        try {
            $document = DB::transaction(function () use ($document, $data, $items, $action) {
                $updated = $this->accounting->updateDocument($document, $data, $items);
                if ($action === 'post') {
                    abort_unless(auth()->user()->hasPermission('accounting.post'), 403);
                    $updated = $this->accounting->postMove($updated);
                }
                return $updated;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($this->config($moveType)['routes']['show'], $document)
            ->with('success', $document->isPosted() ? $this->config($moveType)['posted_message'] : $this->config($moveType)['updated_message']);
    }

    private function post(AccountMove $document, string $moveType)
    {
        $this->authorize('post', $document);
        $this->assertDocumentAccess($document, $moveType);

        try {
            DB::transaction(fn () => $this->accounting->postMove($document));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route($this->config($moveType)['routes']['show'], $document)->with('success', $this->config($moveType)['posted_message']);
    }

    private function resetToDraft(AccountMove $document, string $moveType)
    {
        $this->authorize('post', $document);
        $this->assertDocumentAccess($document, $moveType);

        DB::transaction(fn () => $this->accounting->resetMoveToDraft($document));

        return redirect()->route($this->config($moveType)['routes']['show'], $document)->with('success', 'Document reset to draft.');
    }

    private function cancel(AccountMove $document, string $moveType)
    {
        $this->authorize('post', $document);
        $this->assertDocumentAccess($document, $moveType);

        DB::transaction(fn () => $this->accounting->cancelMove($document));

        return redirect()->route($this->config($moveType)['routes']['show'], $document)->with('success', 'Document cancelled.');
    }

    private function markPaid(Request $request, AccountMove $document, string $moveType)
    {
        $this->authorize('post', $document);
        $this->assertDocumentAccess($document, $moveType);

        $data = $request->validate([
            'amount'     => ['nullable', 'numeric', 'gt:0'],
            'date'       => ['nullable', 'date'],
            'journal_id' => ['nullable', 'integer', Rule::exists('account_journals', 'id')->where('company_id', $document->company_id)],
            'memo'       => ['nullable', 'string', 'max:255'],
        ]);

        // Remove nulls so the service falls back to its own defaults
        $data = array_filter($data, fn ($v) => $v !== null && $v !== '');

        try {
            DB::transaction(fn () => $this->accounting->registerDocumentPayment($document, $data));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route($this->config($moveType)['routes']['show'], $document)->with('success', 'Payment registered.');
    }

    private function creditNote(AccountMove $document, string $moveType)
    {
        $this->authorize('post', $document);
        $this->assertDocumentAccess($document, $moveType);

        try {
            $creditNote = DB::transaction(fn () => $this->accounting->createCreditNote($document));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $route = $creditNote->move_type === 'out_refund'
            ? 'accounting.credit-notes.show'
            : 'accounting.refunds.show';

        return redirect()->route($route, $creditNote)->with('success', 'Credit note created.');
    }

    private function print(AccountMove $document, string $moveType)
    {
        $this->authorize('view', $document);
        $this->assertDocumentAccess($document, $moveType);

        $document->load(['journal', 'partner', 'company', 'lines.account', 'lines.partner', 'lines.taxLine']);
        $config = $this->config($moveType);
        $controlLine = $this->controlLine($document);
        $documentLines = $this->documentLines($document);

        return view('accounting.documents.print', compact('document', 'config', 'controlLine', 'documentLines'));
    }

    private function unlink(AccountMove $document, string $moveType)
    {
        $this->authorize('delete', $document);
        $this->assertDocumentAccess($document, $moveType);

        try {
            DB::transaction(fn () => $this->accounting->deleteMove($document));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route($this->config($moveType)['routes']['index'])->with('success', 'Document deleted.');
    }

    private function addComment(Request $request, AccountMove $document, string $moveType)
    {
        $this->authorize('comment', $document);
        $this->assertDocumentAccess($document, $moveType);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $document->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    private function assertDocumentAccess(AccountMove $document, string $moveType): void
    {
        abort_unless($document->move_type === $moveType, 404);
        abort_unless(in_array($document->company_id, $this->companyContext->getActiveCompanyIds()), 403);
    }

    private function defaults(?int $companyId, string $moveType): array
    {
        if (!$companyId) {
            return ['journal_id' => null, 'control_account_id' => null];
        }

        $journalCode = in_array($moveType, ['out_invoice', 'out_refund'], true) ? 'INV' : 'BILL';
        $controlType = in_array($moveType, ['out_invoice', 'out_refund'], true) ? 'asset_receivable' : 'liability_payable';

        return [
            'journal_id' => AccountJournal::where('company_id', $companyId)->where('code', $journalCode)->value('id'),
            'control_account_id' => Account::where('company_id', $companyId)->where('account_type', $controlType)->orderBy('code')->value('id'),
        ];
    }

    private function controlLine(AccountMove $document)
    {
        $debitControlTypes = ['out_invoice', 'in_refund'];

        return $document->lines
            ->filter(fn ($line) => in_array($document->move_type, $debitControlTypes, true) ? (float) $line->debit > 0 : (float) $line->credit > 0)
            ->sortByDesc(fn ($line) => max((float) $line->debit, (float) $line->credit))
            ->first();
    }

    private function documentLines(AccountMove $document)
    {
        $controlLine = $this->controlLine($document);

        return $document->lines
            ->reject(fn ($line) => ($controlLine && $line->id === $controlLine->id) || $line->tax_line_id)
            ->values();
    }

    private function taxesForCompany(?int $companyId, string $moveType): array
    {
        if (!$companyId) {
            return [];
        }

        $taxScope = in_array($moveType, ['out_invoice', 'out_refund'], true) ? 'sale' : 'purchase';

        return AccountTax::where('company_id', $companyId)
            ->whereIn('type_tax_use', [$taxScope, 'none'])
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'amount_type', 'amount'])
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->display_name])
            ->all();
    }

    private function config(string $moveType): array
    {
        return match ($moveType) {
            'out_invoice' => [
                'move_type' => 'out_invoice',
                'title' => 'Invoices',
                'singular' => 'Invoice',
                'partner_label' => 'Customer',
                'partner_table' => 'contacts_customers',
                'line_account_label' => 'Income Account',
                'control_account_label' => 'Receivable Account',
                'routes' => [
                    'index' => 'accounting.invoices.index',
                    'create' => 'accounting.invoices.create',
                    'store' => 'accounting.invoices.store',
                    'show' => 'accounting.invoices.show',
                    'edit' => 'accounting.invoices.edit',
                    'update' => 'accounting.invoices.update',
                    'post' => 'accounting.invoices.post',
                    'pay' => 'accounting.invoices.pay',
                    'credit' => 'accounting.invoices.credit-note',
                    'print' => 'accounting.invoices.print',
                    'reset' => 'accounting.invoices.reset-draft',
                    'cancel' => 'accounting.invoices.cancel',
                    'delete' => 'accounting.invoices.delete',
                    'comment' => 'accounting.invoices.comment',
                ],
                'saved_message' => 'Invoice saved as draft.',
                'updated_message' => 'Invoice updated.',
                'posted_message' => 'Invoice posted.',
            ],
            'out_refund' => [
                'move_type' => 'out_refund',
                'title' => 'Credit Notes',
                'singular' => 'Credit Note',
                'partner_label' => 'Customer',
                'partner_table' => 'contacts_customers',
                'line_account_label' => 'Income Account',
                'control_account_label' => 'Receivable Account',
                'routes' => [
                    'index' => 'accounting.credit-notes.index',
                    'show' => 'accounting.credit-notes.show',
                    'post' => 'accounting.credit-notes.post',
                    'pay' => 'accounting.credit-notes.pay',
                    'credit' => null,
                    'print' => 'accounting.credit-notes.print',
                    'reset' => 'accounting.credit-notes.reset-draft',
                    'cancel' => 'accounting.credit-notes.cancel',
                    'delete' => 'accounting.credit-notes.delete',
                    'comment' => 'accounting.credit-notes.comment',
                ],
                'saved_message' => 'Credit note saved as draft.',
                'updated_message' => 'Credit note updated.',
                'posted_message' => 'Credit note posted.',
            ],
            'in_refund' => [
                'move_type' => 'in_refund',
                'title' => 'Refunds',
                'singular' => 'Refund',
                'partner_label' => 'Vendor',
                'partner_table' => 'contacts_vendors',
                'line_account_label' => 'Expense Account',
                'control_account_label' => 'Payable Account',
                'routes' => [
                    'index' => 'accounting.refunds.index',
                    'show' => 'accounting.refunds.show',
                    'post' => 'accounting.refunds.post',
                    'pay' => 'accounting.refunds.pay',
                    'credit' => null,
                    'print' => 'accounting.refunds.print',
                    'reset' => 'accounting.refunds.reset-draft',
                    'cancel' => 'accounting.refunds.cancel',
                    'delete' => 'accounting.refunds.delete',
                    'comment' => 'accounting.refunds.comment',
                ],
                'saved_message' => 'Refund saved as draft.',
                'updated_message' => 'Refund updated.',
                'posted_message' => 'Refund posted.',
            ],
            default => [
                'move_type' => 'in_invoice',
                'title' => 'Bills',
                'singular' => 'Bill',
                'partner_label' => 'Vendor',
                'partner_table' => 'contacts_vendors',
                'line_account_label' => 'Expense Account',
                'control_account_label' => 'Payable Account',
                'routes' => [
                    'index' => 'accounting.bills.index',
                    'create' => 'accounting.bills.create',
                    'store' => 'accounting.bills.store',
                    'show' => 'accounting.bills.show',
                    'edit' => 'accounting.bills.edit',
                    'update' => 'accounting.bills.update',
                    'post' => 'accounting.bills.post',
                    'pay' => 'accounting.bills.pay',
                    'credit' => 'accounting.bills.credit-note',
                    'print' => 'accounting.bills.print',
                    'reset' => 'accounting.bills.reset-draft',
                    'cancel' => 'accounting.bills.cancel',
                    'delete' => 'accounting.bills.delete',
                    'comment' => 'accounting.bills.comment',
                ],
                'saved_message' => 'Bill saved as draft.',
                'updated_message' => 'Bill updated.',
                'posted_message' => 'Bill posted.',
            ],
        };
    }
}
