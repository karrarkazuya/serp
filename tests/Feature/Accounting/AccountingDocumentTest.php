<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountPartialReconcile;
use App\Models\Accounting\AccountPayment;
use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_customer_invoice_creates_balanced_receivable_and_income_lines_then_posts(): void
    {
        $company = $this->createCompany('Invoice Co');
        $partner = $this->createPartner($company, 'Baghdad Customer');
        $journal = $this->journal($company, 'INV');
        $receivable = $this->accountByType($company, 'asset_receivable');
        $income = $this->accountByType($company, 'income');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.invoices.create'))
            ->assertOk()
            ->assertSee('Customer Invoice')
            ->assertSee('Invoice Lines');

        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.invoices.store'), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'partner_id' => $partner->id,
                'control_account_id' => $receivable->id,
                'date' => '2026-05-22',
                'ref' => 'SO-001',
                'move_type' => 'out_invoice',
                'currency' => 'USD',
                'items' => [
                    ['account_id' => $income->id, 'name' => 'Consulting revenue', 'quantity' => 2, 'price_unit' => 150],
                    ['account_id' => null, 'name' => '', 'quantity' => '', 'price_unit' => ''],
                ],
            ]);

        $invoice = AccountMove::where('move_type', 'out_invoice')->firstOrFail();

        $response->assertRedirect(route('accounting.invoices.show', $invoice));
        $this->assertSame('draft', $invoice->state);
        $this->assertEquals(300.00, (float) $invoice->amount_total);
        $this->assertSame(2, $invoice->lines()->count());

        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $invoice->id,
            'account_id' => $income->id,
            'name' => 'Consulting revenue',
            'debit' => 0,
            'credit' => 300,
        ]);

        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $invoice->id,
            'account_id' => $receivable->id,
            'name' => 'Customer balance',
            'debit' => 300,
            'credit' => 0,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Consulting revenue')
            ->assertSee('Customer balance');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('Consulting revenue');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice))
            ->assertRedirect(route('accounting.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('posted', $invoice->state);
        $this->assertStringStartsWith('INV/2026/', $invoice->name);
    }

    public function test_vendor_bill_creates_balanced_expense_and_payable_lines_then_posts(): void
    {
        $company = $this->createCompany('Bill Co');
        $partner = $this->createPartner($company, 'Basra Vendor');
        $journal = $this->journal($company, 'BILL');
        $payable = $this->accountByType($company, 'liability_payable');
        $expense = $this->accountByType($company, 'expense');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.bills.create'))
            ->assertOk()
            ->assertSee('Vendor Bill')
            ->assertSee('Bill Lines');

        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.bills.store'), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'partner_id' => $partner->id,
                'control_account_id' => $payable->id,
                'date' => '2026-05-22',
                'ref' => 'BILL-77',
                'move_type' => 'in_invoice',
                'currency' => 'USD',
                'action' => 'post',
                'items' => [
                    ['account_id' => $expense->id, 'name' => 'Office supplies', 'quantity' => 3, 'price_unit' => 40],
                ],
            ]);

        $bill = AccountMove::where('move_type', 'in_invoice')->firstOrFail();

        $response->assertRedirect(route('accounting.bills.show', $bill));
        $this->assertSame('posted', $bill->state);
        $this->assertStringStartsWith('BILL/2026/', $bill->name);
        $this->assertEquals(120.00, (float) $bill->amount_total);

        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $bill->id,
            'account_id' => $expense->id,
            'name' => 'Office supplies',
            'debit' => 120,
            'credit' => 0,
            'state' => 'posted',
        ]);

        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $bill->id,
            'account_id' => $payable->id,
            'name' => 'Vendor balance',
            'debit' => 0,
            'credit' => 120,
            'state' => 'posted',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.bills.show', $bill))
            ->assertOk()
            ->assertSee('Office supplies')
            ->assertSee('Vendor balance');
    }

    public function test_invoice_and_bill_indexes_are_scoped_by_document_type_and_company(): void
    {
        $visibleCompany = $this->createCompany('Visible Document Co');
        $hiddenCompany = $this->createCompany('Hidden Document Co');

        $invoice = $this->createDocument($visibleCompany, 'out_invoice', 'Visible Invoice');
        $bill = $this->createDocument($visibleCompany, 'in_invoice', 'Visible Bill');
        $hiddenInvoice = $this->createDocument($hiddenCompany, 'out_invoice', 'Hidden Invoice');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$visibleCompany->id]])
            ->get(route('accounting.invoices.index'))
            ->assertOk()
            ->assertSee($invoice->ref)
            ->assertDontSee($bill->ref)
            ->assertDontSee($hiddenInvoice->ref);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$visibleCompany->id]])
            ->get(route('accounting.bills.index'))
            ->assertOk()
            ->assertSee($bill->ref)
            ->assertDontSee($invoice->ref);
    }

    public function test_customer_invoice_accepts_more_than_the_initial_visible_line_count(): void
    {
        $company = $this->createCompany('Large Invoice Co');
        $partner = $this->createPartner($company, 'Large Customer');
        $journal = $this->journal($company, 'INV');
        $receivable = $this->accountByType($company, 'asset_receivable');
        $income = $this->accountByType($company, 'income');

        $items = [];
        for ($i = 1; $i <= 12; $i++) {
            $items[] = [
                'account_id' => $income->id,
                'name' => "Invoice line {$i}",
                'quantity' => 1,
                'price_unit' => 10,
            ];
        }

        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.invoices.store'), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'partner_id' => $partner->id,
                'control_account_id' => $receivable->id,
                'date' => '2026-05-22',
                'ref' => 'SO-MANY-LINES',
                'move_type' => 'out_invoice',
                'currency' => 'USD',
                'items' => $items,
            ]);

        $invoice = AccountMove::where('ref', 'SO-MANY-LINES')->firstOrFail();

        $response->assertRedirect(route('accounting.invoices.show', $invoice));
        $this->assertEquals(120.00, (float) $invoice->amount_total);
        $this->assertSame(13, $invoice->lines()->count());
        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $invoice->id,
            'name' => 'Invoice line 12',
            'credit' => 10,
        ]);
    }

    public function test_posted_invoice_can_be_paid_by_payment_move_reconciliation_printed_and_credited(): void
    {
        $company = $this->createCompany('Paid Invoice Co');
        $invoice = $this->createDocument($company, 'out_invoice', 'SO-PAID');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice))
            ->assertRedirect(route('accounting.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('posted', $invoice->state);
        $this->assertSame('not_paid', $invoice->payment_state);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.pay', $invoice))
            ->assertRedirect(route('accounting.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_state);
        $this->assertSame(1, AccountPayment::where('paired_document_id', $invoice->id)->count());
        $this->assertSame(1, AccountPartialReconcile::count());

        $payment = AccountPayment::where('paired_document_id', $invoice->id)->firstOrFail();
        $paymentMove = $payment->move()->with('lines.account')->firstOrFail();
        $bank = AccountJournal::where('company_id', $company->id)->where('code', 'BANK')->firstOrFail()->defaultAccount;
        $receivable = $this->accountByType($company, 'asset_receivable');

        $this->assertSame('inbound', $payment->payment_type);
        $this->assertEquals(50.00, (float) $payment->amount);
        $this->assertEquals(50.00, (float) $paymentMove->amount_total);
        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $paymentMove->id,
            'account_id' => $bank->id,
            'debit' => 50,
            'credit' => 0,
            'state' => 'posted',
        ]);
        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $paymentMove->id,
            'account_id' => $receivable->id,
            'debit' => 0,
            'credit' => 50,
            'state' => 'posted',
        ]);
        $this->assertDatabaseHas('account_partial_reconciles', [
            'company_id' => $company->id,
            'amount' => 50,
        ]);
        $this->assertEquals(0.00, app(AccountingService::class)->documentResidual($invoice));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.invoices.print', $invoice))
            ->assertOk()
            ->assertSee($invoice->name)
            ->assertSee('PAID')
            ->assertSee('SO-PAID line');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.invoices.credit-note', $invoice))
            ->assertRedirect();

        $creditNote = AccountMove::where('reversed_move_id', $invoice->id)->firstOrFail();

        // O5 (Odoo parity): credit notes are created in DRAFT — the user
        // reviews the proposed reversal lines and then posts it explicitly.
        $this->assertSame('out_refund', $creditNote->move_type);
        $this->assertSame('draft', $creditNote->state);

        // Now post the draft credit note via its post endpoint.
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.credit-notes.post', $creditNote))
            ->assertRedirect();
        $creditNote->refresh();

        $this->assertSame('posted', $creditNote->state);
        $this->assertSame('not_paid', $creditNote->payment_state);
        $this->assertEquals((float) $invoice->amount_total, (float) $creditNote->amount_total);
        $this->assertEquals(50.00, app(AccountingService::class)->documentResidual($creditNote));
        $this->assertSame(1, AccountPartialReconcile::count(), 'Paid invoice credit note should not create a second reconciliation automatically.');
        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $creditNote->id,
            'account_id' => $receivable->id,
            'debit' => 0,
            'credit' => 50,
            'state' => 'posted',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.payments.index'))
            ->assertOk()
            ->assertSee('Payments')
            ->assertSee($payment->memo);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.payments.show', $payment))
            ->assertOk()
            ->assertSee('Inbound Payment')
            ->assertSee($payment->memo);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.credit-notes.index'))
            ->assertOk()
            ->assertSee('Credit Notes')
            ->assertSee($creditNote->ref);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.credit-notes.show', $creditNote))
            ->assertOk()
            ->assertSee('Credit Note')
            ->assertSee($creditNote->ref);
    }

    public function test_open_invoice_credit_note_posts_and_reconciles_full_refund_values(): void
    {
        $company = $this->createCompany('Credit Note Values Co');
        $invoice = $this->createDocument($company, 'out_invoice', 'SO-CREDIT-VALUES');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice))
            ->assertRedirect(route('accounting.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('not_paid', $invoice->payment_state);
        $this->assertEquals(50.00, app(AccountingService::class)->documentResidual($invoice));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.invoices.credit-note', $invoice))
            ->assertRedirect();

        $creditNote = AccountMove::where('reversed_move_id', $invoice->id)->firstOrFail();
        $receivable = $this->accountByType($company, 'asset_receivable');
        $income = $this->accountByType($company, 'income');

        // O5 (Odoo parity): credit note is drafted, not posted. Auto-reconcile
        // with the original happens at post time.
        $this->assertSame('draft', $creditNote->state);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.credit-notes.post', $creditNote))
            ->assertRedirect();
        $creditNote->refresh();
        $invoice->refresh();

        $this->assertSame('posted', $creditNote->state);
        $this->assertStringStartsWith('INV/2026/', $creditNote->name);
        // O6 (Odoo parity): an invoice fully cancelled by a credit note flips
        // to `reversed`, not `paid`. The credit note itself just reads `paid`
        // (its residual is zero — nothing else points back at it).
        $this->assertSame('reversed', $invoice->payment_state);
        $this->assertSame('paid', $creditNote->payment_state);
        $this->assertEquals(0.00, app(AccountingService::class)->documentResidual($invoice));
        $this->assertEquals(0.00, app(AccountingService::class)->documentResidual($creditNote));
        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $creditNote->id,
            'account_id' => $income->id,
            'name' => 'Credit: SO-CREDIT-VALUES line',
            'debit' => 50,
            'credit' => 0,
            'state' => 'posted',
        ]);
        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $creditNote->id,
            'account_id' => $receivable->id,
            'name' => 'Credit: Customer balance',
            'debit' => 0,
            'credit' => 50,
            'state' => 'posted',
        ]);
        $this->assertDatabaseHas('account_partial_reconciles', [
            'company_id' => $company->id,
            'amount' => 50,
        ]);
    }

    public function test_vendor_bill_credit_note_creates_refund_view(): void
    {
        $company = $this->createCompany('Refund View Co');
        $bill = $this->createDocument($company, 'in_invoice', 'BILL-REFUND');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.post', $bill))
            ->assertRedirect(route('accounting.bills.show', $bill));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.bills.credit-note', $bill))
            ->assertRedirect();

        $refund = AccountMove::where('reversed_move_id', $bill->id)->firstOrFail();

        // O5 (Odoo parity): vendor refund is drafted, not posted.
        $this->assertSame('in_refund', $refund->move_type);
        $this->assertSame('draft', $refund->state);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.refunds.post', $refund))
            ->assertRedirect();
        $refund->refresh();
        $bill->refresh();

        $this->assertSame('posted', $refund->state);
        // O6 (Odoo parity): the bill being reversed flips to `reversed`; the
        // refund itself just reads `paid` (nothing points back at it).
        $this->assertSame('paid', $refund->payment_state);
        $this->assertSame('reversed', $bill->payment_state);
        $this->assertEquals(0.00, app(AccountingService::class)->documentResidual($bill));
        $this->assertEquals(0.00, app(AccountingService::class)->documentResidual($refund));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.refunds.index'))
            ->assertOk()
            ->assertSee('Refunds')
            ->assertSee($refund->ref);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.refunds.show', $refund))
            ->assertOk()
            ->assertSee('Refund')
            ->assertSee($refund->ref);
    }

    public function test_partial_payment_reconciles_part_of_invoice_and_leaves_residual(): void
    {
        $company = $this->createCompany('Partial Payment Co');
        $invoice = $this->createDocument($company, 'out_invoice', 'SO-PARTIAL');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice))
            ->assertRedirect(route('accounting.invoices.show', $invoice));

        app(AccountingService::class)->registerDocumentPayment($invoice->fresh(), ['amount' => 20]);

        $invoice->refresh();

        $this->assertSame('partial', $invoice->payment_state);
        $this->assertEquals(30.00, app(AccountingService::class)->documentResidual($invoice));
        $this->assertSame(1, AccountPayment::where('paired_document_id', $invoice->id)->count());
        $this->assertSame(1, AccountPartialReconcile::count());
    }

    public function test_invoice_can_only_be_deleted_after_it_is_cancelled(): void
    {
        $company = $this->createCompany('Delete Invoice Co');
        $invoice = $this->createDocument($company, 'out_invoice', 'SO-DELETE');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.invoices.delete', $invoice))
            ->assertForbidden();

        $this->assertDatabaseHas('account_moves', ['id' => $invoice->id, 'state' => 'draft']);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.cancel', $invoice))
            ->assertRedirect(route('accounting.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->state);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.invoices.delete', $invoice))
            ->assertRedirect(route('accounting.invoices.index'));

        $this->assertSoftDeleted('account_moves', ['id' => $invoice->id]);
    }

    public function test_user_without_accounting_permission_cannot_access_invoice_pages(): void
    {
        $company = $this->createCompany('Forbidden Invoice Co');
        $user = User::where('email', 'user@example.com')->firstOrFail();
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->update(['company_id' => $company->id]);

        $this->actingAs($user)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.invoices.index'))
            ->assertForbidden();
    }

    private function createCompany(string $name): Company
    {
        $company = Company::create([
            'name' => $name,
            'active' => true,
            'currency' => 'USD',
        ]);

        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        $this->admin->update(['company_id' => $company->id]);

        return $company;
    }

    private function createPartner(Company $company, string $name): Contact
    {
        return Contact::create([
            'company_id' => $company->id,
            'name' => $name,
            'contact_type' => 'company',
            'active' => true,
        ]);
    }

    private function createDocument(Company $company, string $moveType, string $ref): AccountMove
    {
        $isInvoice = $moveType === 'out_invoice';
        $partner = $this->createPartner($company, $ref . ' Partner');
        $journal = $this->journal($company, $isInvoice ? 'INV' : 'BILL');
        $control = $this->accountByType($company, $isInvoice ? 'asset_receivable' : 'liability_payable');
        $lineAccount = $this->accountByType($company, $isInvoice ? 'income' : 'expense');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route($isInvoice ? 'accounting.invoices.store' : 'accounting.bills.store'), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'partner_id' => $partner->id,
                'control_account_id' => $control->id,
                'date' => '2026-05-22',
                'ref' => $ref,
                'move_type' => $moveType,
                'currency' => 'USD',
                'items' => [
                    ['account_id' => $lineAccount->id, 'name' => $ref . ' line', 'quantity' => 1, 'price_unit' => 50],
                ],
            ])->assertSessionHasNoErrors();

        return AccountMove::where('company_id', $company->id)->where('ref', $ref)->firstOrFail();
    }

    private function journal(Company $company, string $code): AccountJournal
    {
        return AccountJournal::where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }

    private function accountByType(Company $company, string $type): Account
    {
        return Account::where('company_id', $company->id)
            ->where('account_type', $type)
            ->where('active', true)
            ->orderByRaw('LENGTH(code) desc')
            ->orderBy('code')
            ->firstOrFail();
    }
}
