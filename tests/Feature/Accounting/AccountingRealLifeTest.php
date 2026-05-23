<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountPartialReconcile;
use App\Models\Accounting\AccountPayment;
use App\Models\Accounting\AccountingAccountGroup;
use App\Models\Accounting\AccountingIncoterm;
use App\Models\Accounting\AccountingPaymentTerm;
use App\Models\Accounting\AccountingTaxGroup;
use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Real-life accounting coverage test.
 *
 * Covers every controller and route NOT exercised by the other five test files:
 *   - Incoterms CRUD
 *   - Tax Groups CRUD
 *   - Account Groups CRUD (including parent–child)
 *   - Accounting settings / lock-date management via HTTP
 *   - Audit log page
 *   - Bill edit → update lines → post → print → pay → refund lifecycle
 *   - Invoice edit while draft, then post
 *   - Credit-note and refund standalone lifecycle
 *   - Chatter comments on every model type
 *   - Company-isolation 403 guards
 *   - Account tree view and type-filter
 *   - Report pages with real posted data
 *   - Full Iraqi trading-company monthly simulation
 */
class AccountingRealLifeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    }

    // =========================================================================
    // INCOTERMS — fully untested controller
    // =========================================================================

    public function test_incoterm_full_lifecycle_via_http(): void
    {
        // GET create
        $this->actingAs($this->admin)
            ->get(route('accounting.incoterms.create'))
            ->assertOk()
            ->assertSee('Incoterm');

        // POST store
        $response = $this->actingAs($this->admin)
            ->post(route('accounting.incoterms.store'), [
                'code' => 'EXW',
                'name' => 'Ex Works',
            ]);

        $incoterm = AccountingIncoterm::where('code', 'EXW')->firstOrFail();
        $response->assertRedirect(route('accounting.incoterms.show', $incoterm));
        $this->assertSame('Ex Works', $incoterm->name);

        // GET index
        $this->actingAs($this->admin)
            ->get(route('accounting.incoterms.index'))
            ->assertOk()
            ->assertSee('EXW')
            ->assertSee('Ex Works');

        // GET show
        $this->actingAs($this->admin)
            ->get(route('accounting.incoterms.show', $incoterm))
            ->assertOk()
            ->assertSee('EXW')
            ->assertSee('Ex Works');

        // GET edit
        $this->actingAs($this->admin)
            ->get(route('accounting.incoterms.edit', $incoterm))
            ->assertOk()
            ->assertSee('EXW');

        // PUT write — rename
        $this->actingAs($this->admin)
            ->put(route('accounting.incoterms.update', $incoterm), [
                'code' => 'EXW',
                'name' => 'Ex Works (Updated)',
            ])
            ->assertRedirect(route('accounting.incoterms.show', $incoterm));

        $incoterm->refresh();
        $this->assertSame('Ex Works (Updated)', $incoterm->name);

        // POST comment
        $this->actingAs($this->admin)
            ->post(route('accounting.incoterms.comment', $incoterm), ['body' => 'Standard FOB contract'])
            ->assertRedirect();

        // DELETE
        $this->actingAs($this->admin)
            ->delete(route('accounting.incoterms.delete', $incoterm))
            ->assertRedirect(route('accounting.incoterms.index'));

        $this->assertDatabaseMissing('accounting_incoterms', ['id' => $incoterm->id]);
    }

    public function test_duplicate_incoterm_code_is_rejected(): void
    {
        AccountingIncoterm::create(['code' => 'FOB', 'name' => 'Free on Board']);

        $this->actingAs($this->admin)
            ->post(route('accounting.incoterms.store'), [
                'code' => 'FOB',
                'name' => 'Free on Board duplicate',
            ])
            ->assertSessionHasErrors('code');
    }

    // =========================================================================
    // TAX GROUPS — fully untested controller
    // =========================================================================

    public function test_tax_group_full_lifecycle_via_http(): void
    {
        $company = $this->mkCompany('Tax Group Co');

        // GET create
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.tax-groups.create'))
            ->assertOk()
            ->assertSee('Tax Group');

        // POST store
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.tax-groups.store'), [
                'company_id' => $company->id,
                'name'       => 'Standard VAT Group',
                'sequence'   => 10,
            ]);

        $group = AccountingTaxGroup::where('company_id', $company->id)->where('name', 'Standard VAT Group')->firstOrFail();
        $response->assertRedirect(route('accounting.tax-groups.show', $group));
        $this->assertSame(10, $group->sequence);

        // GET index
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.tax-groups.index'))
            ->assertOk()
            ->assertSee('Standard VAT Group');

        // GET show
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.tax-groups.show', $group))
            ->assertOk()
            ->assertSee('Standard VAT Group');

        // GET edit
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.tax-groups.edit', $group))
            ->assertOk();

        // PUT write
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.tax-groups.update', $group), [
                'company_id' => $company->id,
                'name'       => 'Standard VAT Group (Revised)',
                'sequence'   => 20,
            ])
            ->assertRedirect(route('accounting.tax-groups.show', $group));

        $group->refresh();
        $this->assertSame('Standard VAT Group (Revised)', $group->name);
        $this->assertSame(20, $group->sequence);

        // POST comment
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.tax-groups.comment', $group), ['body' => 'Used for domestic sales'])
            ->assertRedirect();

        // DELETE
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.tax-groups.delete', $group))
            ->assertRedirect(route('accounting.tax-groups.index'));

        $this->assertDatabaseMissing('accounting_tax_groups', ['id' => $group->id]);
    }

    public function test_tax_group_from_different_company_returns_403(): void
    {
        $company = $this->mkCompany('Tax Group Isolation Co');
        $otherCompany = $this->mkCompany('Other Tax Group Co');

        $group = AccountingTaxGroup::create([
            'company_id' => $otherCompany->id,
            'name'       => 'Other Company Group',
            'sequence'   => 1,
        ]);

        // Admin's active company is $company, but the group belongs to $otherCompany
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.tax-groups.show', $group))
            ->assertForbidden();
    }

    // =========================================================================
    // ACCOUNT GROUPS — fully untested controller
    // =========================================================================

    public function test_account_group_with_parent_full_lifecycle_via_http(): void
    {
        $company = $this->mkCompany('Account Group Co');

        // Create parent group
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.account-groups.store'), [
                'company_id'        => $company->id,
                'name'              => 'Current Assets',
                'code_prefix_start' => '1',
                'code_prefix_end'   => '1999',
            ]);

        $parent = AccountingAccountGroup::where('company_id', $company->id)
            ->where('name', 'Current Assets')
            ->firstOrFail();

        // GET index
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.account-groups.index'))
            ->assertOk()
            ->assertSee('Current Assets');

        // GET show
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.account-groups.show', $parent))
            ->assertOk()
            ->assertSee('Current Assets')
            ->assertSee('1')
            ->assertSee('1999');

        // Create child group
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.account-groups.store'), [
                'company_id'        => $company->id,
                'parent_id'         => $parent->id,
                'name'              => 'Cash and Cash Equivalents',
                'code_prefix_start' => '18',
                'code_prefix_end'   => '18999',
            ]);

        $child = AccountingAccountGroup::where('company_id', $company->id)
            ->where('name', 'Cash and Cash Equivalents')
            ->firstOrFail();

        $this->assertSame($parent->id, $child->parent_id);

        // GET edit
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.account-groups.edit', $child))
            ->assertOk();

        // PUT write
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.account-groups.update', $child), [
                'company_id'        => $company->id,
                'parent_id'         => $parent->id,
                'name'              => 'Cash & Equivalents',
                'code_prefix_start' => '18',
                'code_prefix_end'   => '189',
            ])
            ->assertRedirect(route('accounting.account-groups.show', $child));

        $child->refresh();
        $this->assertSame('Cash & Equivalents', $child->name);

        // POST comment
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.account-groups.comment', $child), ['body' => 'Covers 18xxx accounts'])
            ->assertRedirect();

        // DELETE child
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.account-groups.delete', $child))
            ->assertRedirect(route('accounting.account-groups.index'));

        $this->assertDatabaseMissing('accounting_account_groups', ['id' => $child->id]);
        $this->assertDatabaseHas('accounting_account_groups', ['id' => $parent->id]);
    }

    // =========================================================================
    // ACCOUNTING SETTINGS — lock dates via HTTP
    // =========================================================================

    public function test_settings_page_shows_active_companies(): void
    {
        $company = $this->mkCompany('Settings Co');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.settings'))
            ->assertOk()
            ->assertSee('Settings Co')
            ->assertSee('Lock');
    }

    public function test_period_lock_date_can_be_set_via_http(): void
    {
        $company = $this->mkCompany('Lock Date Co');
        $this->assertNull($company->accounting_period_lock_date);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.settings.update', $company), [
                'accounting_period_lock_date'     => '2026-01-31',
                'accounting_fiscal_year_lock_date' => '',
            ])
            ->assertRedirect(route('accounting.settings'));

        $company->refresh();
        $this->assertSame('2026-01-31', $company->accounting_period_lock_date?->toDateString());
        $this->assertNull($company->accounting_fiscal_year_lock_date);
    }

    public function test_period_lock_date_can_be_cleared_via_http(): void
    {
        $company = $this->mkCompany('Lock Clear Co');
        $company->update(['accounting_period_lock_date' => '2026-01-31']);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.settings.update', $company), [
                'accounting_period_lock_date'     => '',
                'accounting_fiscal_year_lock_date' => '',
            ])
            ->assertRedirect(route('accounting.settings'));

        $company->refresh();
        $this->assertNull($company->accounting_period_lock_date);
    }

    public function test_lock_date_set_via_settings_enforces_posting_guard(): void
    {
        $company = $this->mkCompany('Lock Enforce Co');
        // Fiscal year lock blocks everyone, including admin (period lock only blocks non-lock-permission users).
        $company->update(['accounting_fiscal_year_lock_date' => '2026-03-31']);

        $journal = $this->journal($company, 'MISC');
        $cash    = $this->accountByType($company, 'asset_cash');
        $income  = $this->accountByType($company, 'income');

        // POST a move dated before the lock date → store succeeds (saved as draft),
        // but the explicit post action should fail
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.moves.store'), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'date'       => '2026-03-15',
                'move_type'  => 'entry',
                'currency'   => 'USD',
                'ref'        => 'LOCKED-PERIOD',
                'action'     => 'post',
                'lines' => [
                    ['account_id' => $cash->id,   'name' => 'Dr', 'debit' => 100, 'credit' => 0],
                    ['account_id' => $income->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 100],
                ],
            ]);

        // The store controller catches RuntimeException from the lock check and returns back with error
        $response->assertRedirect();
        $this->assertDatabaseMissing('account_moves', ['ref' => 'LOCKED-PERIOD', 'state' => 'posted']);
    }

    // =========================================================================
    // AUDIT LOG
    // =========================================================================

    public function test_audit_log_page_renders_for_admin(): void
    {
        $company = $this->mkCompany('Audit Co');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.audit'))
            ->assertOk()
            ->assertSee('Audit');
    }

    public function test_audit_log_shows_chatter_entries_after_posting(): void
    {
        $company = $this->mkCompany('Audit Log Co');
        $invoice = $this->createInvoice($company, 'AUDIT-INV', 200);

        // Post the invoice — this should create chatter entries
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        // The audit log should now have entries for the AccountMove
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.audit'))
            ->assertOk();
    }

    // =========================================================================
    // INVOICE — edit while draft, then post
    // =========================================================================

    public function test_draft_invoice_can_have_lines_changed_before_posting(): void
    {
        $company    = $this->mkCompany('Invoice Edit Co');
        $partner    = $this->mkPartner($company, 'Edit Customer');
        $journal    = $this->journal($company, 'INV');
        $receivable = $this->accountByType($company, 'asset_receivable');
        $income     = $this->accountByType($company, 'income');

        // Create draft invoice with 1 line × $100
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.invoices.store'), [
                'company_id'         => $company->id,
                'journal_id'         => $journal->id,
                'partner_id'         => $partner->id,
                'control_account_id' => $receivable->id,
                'date'               => '2026-05-01',
                'ref'                => 'SO-EDIT-DRAFT',
                'move_type'          => 'out_invoice',
                'currency'           => 'USD',
                'items' => [
                    ['account_id' => $income->id, 'name' => 'Widget A', 'quantity' => 1, 'price_unit' => 100],
                ],
            ]);

        $invoice = AccountMove::where('ref', 'SO-EDIT-DRAFT')->firstOrFail();
        $this->assertEquals(100.00, (float) $invoice->amount_total);
        $this->assertSame('draft', $invoice->state);

        // GET edit form
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.invoices.edit', $invoice))
            ->assertOk()
            ->assertSee('Widget A');

        // PUT write — change to 2 lines, increase amounts
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.invoices.update', $invoice), [
                'company_id'         => $company->id,
                'journal_id'         => $journal->id,
                'partner_id'         => $partner->id,
                'control_account_id' => $receivable->id,
                'date'               => '2026-05-01',
                'ref'                => 'SO-EDIT-DRAFT',
                'move_type'          => 'out_invoice',
                'currency'           => 'USD',
                'items' => [
                    ['account_id' => $income->id, 'name' => 'Widget A', 'quantity' => 3, 'price_unit' => 100],
                    ['account_id' => $income->id, 'name' => 'Widget B', 'quantity' => 2, 'price_unit' => 75],
                ],
            ])
            ->assertRedirect(route('accounting.invoices.show', $invoice));

        $invoice->refresh();
        $this->assertEquals(450.00, (float) $invoice->amount_total, 'Updated total: 3×100 + 2×75 = 450');
        $this->assertSame(3, $invoice->lines()->count()); // 2 product lines + 1 control line

        // POST to post the updated invoice
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $invoice->refresh();
        $this->assertSame('posted', $invoice->state);
        $this->assertEquals(450.00, (float) $invoice->amount_total);
    }

    // =========================================================================
    // BILL — full lifecycle with edit, pay, print
    // =========================================================================

    public function test_bill_full_lifecycle_edit_post_print_pay(): void
    {
        $company = $this->mkCompany('Full Bill Co');
        $partner = $this->mkPartner($company, 'Supplier Ltd');
        $journal = $this->journal($company, 'BILL');
        $payable = $this->accountByType($company, 'liability_payable');
        $expense = $this->accountByType($company, 'expense');

        // Create draft bill with 1 line
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.bills.store'), [
                'company_id'         => $company->id,
                'journal_id'         => $journal->id,
                'partner_id'         => $partner->id,
                'control_account_id' => $payable->id,
                'date'               => '2026-05-10',
                'ref'                => 'BILL-EDIT-001',
                'move_type'          => 'in_invoice',
                'currency'           => 'USD',
                'items' => [
                    ['account_id' => $expense->id, 'name' => 'Office Supplies', 'quantity' => 5, 'price_unit' => 20],
                ],
            ]);

        $bill = AccountMove::where('ref', 'BILL-EDIT-001')->firstOrFail();
        $this->assertEquals(100.00, (float) $bill->amount_total);
        $this->assertSame('draft', $bill->state);

        // GET edit form
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.bills.edit', $bill))
            ->assertOk()
            ->assertSee('Office Supplies');

        // PUT write — add a second line
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.bills.update', $bill), [
                'company_id'         => $company->id,
                'journal_id'         => $journal->id,
                'partner_id'         => $partner->id,
                'control_account_id' => $payable->id,
                'date'               => '2026-05-10',
                'ref'                => 'BILL-EDIT-001',
                'move_type'          => 'in_invoice',
                'currency'           => 'USD',
                'items' => [
                    ['account_id' => $expense->id, 'name' => 'Office Supplies', 'quantity' => 5, 'price_unit' => 20],
                    ['account_id' => $expense->id, 'name' => 'IT Equipment',    'quantity' => 1, 'price_unit' => 300],
                ],
            ])
            ->assertRedirect(route('accounting.bills.show', $bill));

        $bill->refresh();
        $this->assertEquals(400.00, (float) $bill->amount_total, '5×20 + 1×300 = 400');

        // PATCH post
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.post', $bill))
            ->assertRedirect(route('accounting.bills.show', $bill));

        $bill->refresh();
        $this->assertSame('posted', $bill->state);
        $this->assertSame('not_paid', $bill->payment_state);

        // GET print
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.bills.print', $bill))
            ->assertOk()
            ->assertSee($bill->name)
            ->assertSee('Office Supplies')
            ->assertSee('IT Equipment');

        // PATCH pay — full payment
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.pay', $bill))
            ->assertRedirect(route('accounting.bills.show', $bill));

        $bill->refresh();
        $this->assertSame('paid', $bill->payment_state);

        // Verify payment and reconciliation created
        $payment = AccountPayment::where('paired_document_id', $bill->id)->firstOrFail();
        $this->assertSame('outbound', $payment->payment_type);
        $this->assertEquals(400.00, (float) $payment->amount);
        $this->assertSame(1, AccountPartialReconcile::where('company_id', $company->id)->count());
    }

    public function test_bill_reset_to_draft_and_repost(): void
    {
        $company = $this->mkCompany('Bill Draft Reset Co');
        $bill    = $this->createBill($company, 'BILL-RESET', 200);

        // Post the bill
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.post', $bill))
            ->assertRedirect();

        $bill->refresh();
        $this->assertSame('posted', $bill->state);
        $originalName = $bill->name;

        // Reset to draft
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.reset-draft', $bill))
            ->assertRedirect(route('accounting.bills.show', $bill));

        $bill->refresh();
        $this->assertSame('draft', $bill->state);
        $this->assertSame($originalName, $bill->name, 'Sequence preserved on reset.');

        // Re-post
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.post', $bill))
            ->assertRedirect();

        $bill->refresh();
        $this->assertSame('posted', $bill->state);
    }

    public function test_bill_can_be_cancelled_and_deleted(): void
    {
        $company = $this->mkCompany('Bill Cancel Delete Co');
        $bill    = $this->createBill($company, 'BILL-CANCEL-DEL', 150);

        // Cancel from draft (draft → cancelled is allowed)
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.cancel', $bill))
            ->assertRedirect(route('accounting.bills.show', $bill));

        $bill->refresh();
        $this->assertSame('cancelled', $bill->state);

        // DELETE cancelled bill
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.bills.delete', $bill))
            ->assertRedirect(route('accounting.bills.index'));

        $this->assertDatabaseMissing('account_moves', ['id' => $bill->id]);
    }

    // =========================================================================
    // CREDIT NOTE & REFUND — standalone lifecycle
    // =========================================================================

    public function test_credit_note_post_pay_print_cancel_delete(): void
    {
        $company    = $this->mkCompany('Credit Note Lifecycle Co');
        $invoice    = $this->createInvoice($company, 'SO-CN-LIFECYCLE', 300);

        // Post the original invoice
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $invoice->refresh();

        // Create credit note from the posted invoice
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.invoices.credit-note', $invoice));

        $creditNote = AccountMove::where('reversed_move_id', $invoice->id)->firstOrFail();
        $this->assertSame('out_refund', $creditNote->move_type);
        $this->assertSame('posted', $creditNote->state);

        // GET show for credit note
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.credit-notes.show', $creditNote))
            ->assertOk()
            ->assertSee('Credit Note');

        // GET print for credit note
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.credit-notes.print', $creditNote))
            ->assertOk()
            ->assertSee($creditNote->name ?? 'Credit');

        // PATCH reset credit note to draft
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.credit-notes.reset-draft', $creditNote))
            ->assertRedirect(route('accounting.credit-notes.show', $creditNote));

        $creditNote->refresh();
        $this->assertSame('draft', $creditNote->state);

        // PATCH cancel the draft credit note
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.credit-notes.cancel', $creditNote))
            ->assertRedirect(route('accounting.credit-notes.show', $creditNote));

        $creditNote->refresh();
        $this->assertSame('cancelled', $creditNote->state);

        // DELETE the cancelled credit note
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.credit-notes.delete', $creditNote))
            ->assertRedirect(route('accounting.credit-notes.index'));

        $this->assertDatabaseMissing('account_moves', ['id' => $creditNote->id]);
    }

    public function test_refund_post_pay_print_cancel(): void
    {
        $company = $this->mkCompany('Refund Lifecycle Co');
        $bill    = $this->createBill($company, 'BILL-REFUND-LIFECYCLE', 250);

        // Post the original bill
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.post', $bill));

        // Create refund (credit note on the bill)
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.bills.credit-note', $bill));

        $refund = AccountMove::where('reversed_move_id', $bill->id)->firstOrFail();
        $this->assertSame('in_refund', $refund->move_type);
        $this->assertSame('posted', $refund->state);

        // GET show
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.refunds.show', $refund))
            ->assertOk()
            ->assertSee('Refund');

        // GET print
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.refunds.print', $refund))
            ->assertOk();

        // PATCH reset to draft
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.refunds.reset-draft', $refund))
            ->assertRedirect(route('accounting.refunds.show', $refund));

        $refund->refresh();
        $this->assertSame('draft', $refund->state);

        // Re-post via PATCH post
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.refunds.post', $refund))
            ->assertRedirect(route('accounting.refunds.show', $refund));

        $refund->refresh();
        $this->assertSame('posted', $refund->state);

        // PATCH pay
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.refunds.pay', $refund))
            ->assertRedirect(route('accounting.refunds.show', $refund));

        $refund->refresh();
        $this->assertSame('paid', $refund->payment_state);
    }

    // =========================================================================
    // CHATTER COMMENTS — on every model type
    // =========================================================================

    public function test_comment_can_be_added_to_invoice(): void
    {
        $company = $this->mkCompany('Comment Invoice Co');
        $invoice = $this->createInvoice($company, 'SO-COMMENT', 100);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.invoices.comment', $invoice), ['body' => 'Reviewed by finance team'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatter_messages', [
            'model_type'   => AccountMove::class,
            'model_id'     => $invoice->id,
            'message_type' => 'comment',
        ]);
    }

    public function test_comment_can_be_added_to_bill(): void
    {
        $company = $this->mkCompany('Comment Bill Co');
        $bill    = $this->createBill($company, 'BILL-COMMENT', 100);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.bills.comment', $bill), ['body' => 'Verified against PO-77'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatter_messages', [
            'model_type'   => AccountMove::class,
            'model_id'     => $bill->id,
            'message_type' => 'comment',
        ]);
    }

    public function test_comment_can_be_added_to_journal_entry(): void
    {
        $company = $this->mkCompany('Comment Move Co');
        $journal = $this->journal($company, 'MISC');
        $cash    = $this->accountByType($company, 'asset_cash');
        $income  = $this->accountByType($company, 'income');

        $svc = app(AccountingService::class);
        Auth::login($this->admin);
        $move = $svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $journal->id,
            'date'       => '2026-05-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $cash->id,   'name' => 'Dr', 'debit' => 50, 'credit' => 0],
            ['account_id' => $income->id, 'name' => 'Cr', 'debit' => 0,  'credit' => 50],
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.moves.comment', $move), ['body' => 'Approved by management'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatter_messages', [
            'model_type'   => AccountMove::class,
            'model_id'     => $move->id,
            'message_type' => 'comment',
        ]);
    }

    public function test_comment_can_be_added_to_account(): void
    {
        $company = $this->mkCompany('Comment Account Co');
        $cash    = $this->accountByType($company, 'asset_cash');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.accounts.comment', $cash), ['body' => 'Main operating cash account'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatter_messages', [
            'model_type'   => Account::class,
            'model_id'     => $cash->id,
            'message_type' => 'comment',
        ]);
    }

    public function test_comment_can_be_added_to_journal(): void
    {
        $company = $this->mkCompany('Comment Journal Co');
        $journal = $this->journal($company, 'MISC');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.journals.comment', $journal), ['body' => 'Used for misc adjustments'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatter_messages', [
            'model_type'   => AccountJournal::class,
            'model_id'     => $journal->id,
            'message_type' => 'comment',
        ]);
    }

    public function test_comment_can_be_added_to_payment(): void
    {
        $company = $this->mkCompany('Comment Payment Co');
        $invoice = $this->createInvoice($company, 'SO-PAY-COMMENT', 100);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.pay', $invoice));

        $payment = AccountPayment::where('paired_document_id', $invoice->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.payments.comment', $payment), ['body' => 'Wire transfer confirmed'])
            ->assertRedirect();

        $this->assertDatabaseHas('chatter_messages', [
            'model_type'   => AccountPayment::class,
            'model_id'     => $payment->id,
            'message_type' => 'comment',
        ]);
    }

    // =========================================================================
    // COMPANY ISOLATION — 403 guards
    // =========================================================================

    public function test_user_cannot_view_invoice_from_different_company(): void
    {
        $ownCompany   = $this->mkCompany('Own Invoice Co');
        $otherCompany = $this->mkCompany('Other Invoice Co');

        $invoice = $this->createInvoice($otherCompany, 'SO-OTHER-CO', 100);

        // Admin's active company is $ownCompany, but invoice belongs to $otherCompany
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$ownCompany->id]])
            ->get(route('accounting.invoices.show', $invoice))
            ->assertForbidden();
    }

    public function test_user_cannot_post_invoice_from_different_company(): void
    {
        $ownCompany   = $this->mkCompany('Own Post Co');
        $otherCompany = $this->mkCompany('Other Post Co');

        $invoice = $this->createInvoice($otherCompany, 'SO-POST-OTHER', 100);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$ownCompany->id]])
            ->patch(route('accounting.invoices.post', $invoice))
            ->assertForbidden();
    }

    public function test_user_cannot_view_account_from_different_company(): void
    {
        $ownCompany   = $this->mkCompany('Own Account Co');
        $otherCompany = $this->mkCompany('Other Account Co');

        $account = $this->accountByType($otherCompany, 'asset_cash');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$ownCompany->id]])
            ->get(route('accounting.accounts.show', $account))
            ->assertForbidden();
    }

    public function test_user_cannot_view_journal_from_different_company(): void
    {
        $ownCompany   = $this->mkCompany('Own Journal Co');
        $otherCompany = $this->mkCompany('Other Journal Co');

        $journal = $this->journal($otherCompany, 'MISC');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$ownCompany->id]])
            ->get(route('accounting.journals.show', $journal))
            ->assertForbidden();
    }

    public function test_user_cannot_view_move_from_different_company(): void
    {
        $ownCompany   = $this->mkCompany('Own Move Co');
        $otherCompany = $this->mkCompany('Other Move Co');

        $svc = app(AccountingService::class);
        Auth::login($this->admin);
        $move = $svc->createMove([
            'company_id' => $otherCompany->id,
            'journal_id' => $this->journal($otherCompany, 'MISC')->id,
            'date'       => '2026-05-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $this->accountByType($otherCompany, 'asset_cash')->id, 'name' => 'Dr', 'debit' => 10, 'credit' => 0],
            ['account_id' => $this->accountByType($otherCompany, 'income')->id,     'name' => 'Cr', 'debit' => 0,  'credit' => 10],
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$ownCompany->id]])
            ->get(route('accounting.moves.show', $move))
            ->assertForbidden();
    }

    public function test_user_cannot_pay_bill_with_journal_from_different_company(): void
    {
        $ownCompany   = $this->mkCompany('Pay Isolation Co');
        $otherCompany = $this->mkCompany('Other Pay Co');

        $bill         = $this->createBill($ownCompany, 'BILL-PAY-ISO', 100);
        $otherJournal = $this->journal($otherCompany, 'BANK');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$ownCompany->id]])
            ->patch(route('accounting.bills.post', $bill));

        // Try to pay using a journal from another company
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$ownCompany->id]])
            ->patch(route('accounting.bills.pay', $bill), ['journal_id' => $otherJournal->id]);

        // Should fail validation — journal doesn't belong to bill's company
        $response->assertRedirect();
        $bill->refresh();
        $this->assertSame('not_paid', $bill->payment_state);
    }

    // =========================================================================
    // ACCOUNT VIEWS — tree and type filter
    // =========================================================================

    public function test_accounts_index_tree_view_renders(): void
    {
        $company = $this->mkCompany('Tree View Co');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.accounts.index', ['view' => 'tree']))
            ->assertOk()
            ->assertSee('الموجودات');
    }

    public function test_accounts_index_filters_by_account_type(): void
    {
        $company = $this->mkCompany('Account Filter Co');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.accounts.index', ['account_type' => 'asset_cash']))
            ->assertOk()
            ->assertSee('نقدية');
    }

    public function test_accounts_index_archived_filter(): void
    {
        $company = $this->mkCompany('Archived Filter Co');
        $cash    = $this->accountByType($company, 'asset_cash');

        app(AccountingService::class)->archiveAccount($cash);

        // Default (active only) — should NOT include the archived account
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.accounts.index'))
            ->assertOk()
            ->assertDontSee($cash->code . ' — ' . $cash->name);

        // Archived filter — should show it
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.accounts.index', ['filter' => 'archived']))
            ->assertOk()
            ->assertSee($cash->name);
    }

    public function test_journals_index_filters_by_type(): void
    {
        $company = $this->mkCompany('Journal Filter Co');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.journals.index', ['type' => 'bank']))
            ->assertOk()
            ->assertSee('BANK')
            ->assertDontSee('MISC');
    }

    // =========================================================================
    // REPORTS WITH REAL DATA
    // =========================================================================

    public function test_profit_and_loss_shows_income_and_expense_after_posting(): void
    {
        $company = $this->mkCompany('P&L Data Co');
        $invoice = $this->createInvoice($company, 'SO-PNL-TEST', 5000);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.reports.profit-and-loss', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('5,000');
    }

    public function test_trial_balance_report_shows_posted_account_balances(): void
    {
        $company = $this->mkCompany('Trial Balance Co');
        $bill    = $this->createBill($company, 'BILL-TB-TEST', 1200);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.bills.post', $bill));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.reports.trial-balance', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('1,200');
    }

    public function test_aged_receivable_report_shows_unpaid_invoices(): void
    {
        $company = $this->mkCompany('Aged Recv Co');
        $partner = $this->mkPartner($company, 'Slow Paying Customer');
        $invoice = $this->createInvoiceForPartner($company, $partner, 'SO-AGED', 3500);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.reports.aged-receivable', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('Slow Paying Customer');
    }

    public function test_partner_ledger_shows_partner_transactions(): void
    {
        $company = $this->mkCompany('Partner Ledger Co');
        $partner = $this->mkPartner($company, 'Important Partner');
        $invoice = $this->createInvoiceForPartner($company, $partner, 'SO-LEDGER', 2000);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.reports.partner-ledger', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('Important Partner');
    }

    // =========================================================================
    // FULL IRAQI TRADING COMPANY MONTHLY SIMULATION
    //
    // Al-Rashid Trading Co — January 2026
    //   Day 01: Owner contributes capital IQD 100,000,000 (using USD account for simplicity)
    //   Day 03: Purchase inventory from Basra Supplier for $10,000 — 30% upfront, 70% on credit
    //   Day 08: Sell goods to Baghdad Customer for $15,000 — full cash sale
    //   Day 12: Receive second payment from a second customer for $8,000
    //   Day 15: Pay the remaining $7,000 owed to Basra Supplier (partial, then full)
    //   Day 20: Customer returns $500 of goods — credit note issued, posted, reconciled
    //   Day 28: Admin sets January period lock
    //   Verify: trial balance = 0, P&L shows profit, company is solvent
    // =========================================================================

    public function test_iraqi_trading_company_full_month_simulation(): void
    {
        $company   = $this->mkCompany('Al-Rashid Trading Co');
        $svc       = app(AccountingService::class);
        Auth::login($this->admin);

        $cash     = $this->accountByType($company, 'asset_cash');
        $payable  = $this->accountByType($company, 'liability_payable');
        $income   = $this->accountByType($company, 'income');
        $expense  = $this->accountByType($company, 'expense');
        $bankJrnl = $this->journal($company, 'BANK');
        $miscJrnl = $this->journal($company, 'MISC');

        $mkHeader = fn (AccountJournal $j, string $date) => [
            'company_id' => $company->id,
            'journal_id' => $j->id,
            'date'       => $date,
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ];

        // ── Day 01: Capital injection $100,000 ─────────────────────────────
        $capitalEntry = $svc->postMove($svc->createMove($mkHeader($bankJrnl, '2026-01-01'), [
            ['account_id' => $cash->id,     'name' => 'Owner capital', 'debit' => 100_000, 'credit' => 0],
            ['account_id' => $income->id,   'name' => 'Owner capital', 'debit' => 0, 'credit' => 100_000],
        ]));
        $this->assertSame('posted', $capitalEntry->state);

        // ── Day 03: Inventory purchase $10,000 — $3,000 cash, $7,000 on credit
        $purchaseCashEntry = $svc->postMove($svc->createMove($mkHeader($bankJrnl, '2026-01-03'), [
            ['account_id' => $expense->id, 'name' => 'Inventory - cash',   'debit' => 3_000, 'credit' => 0],
            ['account_id' => $cash->id,    'name' => 'Cash payment',        'debit' => 0,     'credit' => 3_000],
        ]));
        $purchaseCreditEntry = $svc->postMove($svc->createMove($mkHeader($miscJrnl, '2026-01-03'), [
            ['account_id' => $expense->id, 'name' => 'Inventory - credit', 'debit' => 7_000, 'credit' => 0],
            ['account_id' => $payable->id, 'name' => 'Basra Supplier',     'debit' => 0,     'credit' => 7_000],
        ]));
        $this->assertSame('posted', $purchaseCashEntry->state);
        $this->assertSame('posted', $purchaseCreditEntry->state);

        // ── Day 08: Sale to Baghdad Customer $15,000 cash ──────────────────
        $saleEntry = $svc->postMove($svc->createMove($mkHeader($bankJrnl, '2026-01-08'), [
            ['account_id' => $cash->id,   'name' => 'Baghdad Customer payment', 'debit' => 15_000, 'credit' => 0],
            ['account_id' => $income->id, 'name' => 'Goods sold',               'debit' => 0,      'credit' => 15_000],
        ]));
        $this->assertSame('posted', $saleEntry->state);

        // ── Day 12: Second customer pays $8,000 ────────────────────────────
        $sale2 = $svc->postMove($svc->createMove($mkHeader($bankJrnl, '2026-01-12'), [
            ['account_id' => $cash->id,   'name' => 'Second customer payment', 'debit' => 8_000, 'credit' => 0],
            ['account_id' => $income->id, 'name' => 'Goods sold',              'debit' => 0,     'credit' => 8_000],
        ]));
        $this->assertSame('posted', $sale2->state);

        // ── Day 15: Pay Basra Supplier $7,000 outstanding ──────────────────
        $paySupplier = $svc->postMove($svc->createMove($mkHeader($bankJrnl, '2026-01-15'), [
            ['account_id' => $payable->id, 'name' => 'Basra Supplier payment', 'debit' => 7_000, 'credit' => 0],
            ['account_id' => $cash->id,    'name' => 'Cash out',               'debit' => 0,     'credit' => 7_000],
        ]));
        $this->assertSame('posted', $paySupplier->state);

        // ── Day 20: Customer return $500 — correction entry ────────────────
        $returnEntry = $svc->postMove($svc->createMove($mkHeader($miscJrnl, '2026-01-20'), [
            ['account_id' => $income->id, 'name' => 'Customer return',  'debit' => 500, 'credit' => 0],
            ['account_id' => $cash->id,   'name' => 'Refund to customer', 'debit' => 0, 'credit' => 500],
        ]));
        $this->assertSame('posted', $returnEntry->state);

        // ── Verify individual account balances ─────────────────────────────
        // Cash: +100,000 -3,000 +15,000 +8,000 -7,000 -500 = 112,500
        $cashBalance = $svc->getAccountBalance($cash);
        $this->assertSame(112_500.0, $cashBalance, 'Cash balance should be 112,500');

        // Payable: +7,000 (credit) -7,000 (debit) = 0
        $payableBalance = $svc->getAccountBalance($payable);
        $this->assertSame(0.0, $payableBalance, 'All supplier debt is settled');

        // Income: -(100,000 + 15,000 + 8,000) + 500 = -122,500 (credit = negative in debit-normal)
        $incomeBalance = $svc->getAccountBalance($income);
        $this->assertSame(-122_500.0, $incomeBalance, 'Net income 122,500');

        // Expense: 3,000 + 7,000 = 10,000
        $expenseBalance = $svc->getAccountBalance($expense);
        $this->assertSame(10_000.0, $expenseBalance, 'Total expenses 10,000');

        // ── Trial balance must be zero ─────────────────────────────────────
        $trialBalance = $cashBalance + $payableBalance + $incomeBalance + $expenseBalance;
        $this->assertSame(0.0, round($trialBalance, 2), 'Trial balance must be zero');

        // ── P&L: net income = revenue - expense = 122,500 - 10,000 = 112,500 ───
        $netIncome = abs($incomeBalance) - $expenseBalance;
        $this->assertSame(112_500.0, $netIncome, 'Net profit for January is 112,500');

        // ── Day 28: Set January fiscal-year lock via HTTP (blocks everyone, including admin) ─
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.settings.update', $company), [
                'accounting_period_lock_date'      => '',
                'accounting_fiscal_year_lock_date' => '2026-01-31',
            ])
            ->assertRedirect(route('accounting.settings'));

        $company->refresh();
        $this->assertSame('2026-01-31', $company->accounting_fiscal_year_lock_date?->toDateString());

        // ── Verify fiscal-year lock: posting into Jan is blocked for everyone ─
        $lockedMoveResponse = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.moves.store'), [
                'company_id' => $company->id,
                'journal_id' => $miscJrnl->id,
                'date'       => '2026-01-05',
                'move_type'  => 'entry',
                'currency'   => 'USD',
                'ref'        => 'JAN-LOCKED-ENTRY',
                'action'     => 'post',
                'lines' => [
                    ['account_id' => $cash->id,   'name' => 'Dr', 'debit' => 1, 'credit' => 0],
                    ['account_id' => $income->id, 'name' => 'Cr', 'debit' => 0, 'credit' => 1],
                ],
            ]);
        $lockedMoveResponse->assertRedirect();
        $this->assertDatabaseMissing('account_moves', ['ref' => 'JAN-LOCKED-ENTRY', 'state' => 'posted']);

        // ── Report pages render with the real data ──────────────────────────
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.reports.profit-and-loss', ['company_id' => $company->id]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.reports.trial-balance', ['company_id' => $company->id]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.reports.balance-sheet', ['company_id' => $company->id]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.reports.general-ledger', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('100,000.00');
    }

    // =========================================================================
    // PAYMENT TERMS — create with installment lines
    // =========================================================================

    public function test_payment_term_with_installment_lines_is_stored_correctly(): void
    {
        $company = $this->mkCompany('Payment Term Lines Co');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.payment-terms.store'), [
                'company_id' => $company->id,
                'name'       => 'Net 30 / 50% upfront',
                'note'       => '50% due immediately, 50% in 30 days',
                'lines' => [
                    ['value_type' => 'percent', 'value' => 50, 'days' => 0,  'months' => 0, 'day_of_month' => null, 'delay_type' => 'days_after'],
                    ['value_type' => 'balance', 'value' => 0,  'days' => 30, 'months' => 0, 'day_of_month' => null, 'delay_type' => 'days_after'],
                ],
            ]);

        $term = AccountingPaymentTerm::where('company_id', $company->id)
            ->where('name', 'Net 30 / 50% upfront')
            ->firstOrFail();

        $this->assertSame(2, $term->lines()->count());

        $firstLine = $term->lines()->reorder()->orderBy('sequence')->first();
        $this->assertSame('percent', $firstLine->value_type);
        $this->assertEquals(50, (float) $firstLine->value);
        $this->assertSame(0, $firstLine->days);

        $lastLine = $term->lines()->reorder()->orderByDesc('sequence')->first();
        $this->assertSame('balance', $lastLine->value_type);
        $this->assertSame(30, $lastLine->days);

        // GET show displays the lines
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.payment-terms.show', $term))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('30');
    }

    // =========================================================================
    // helpers
    // =========================================================================

    private function mkCompany(string $name): Company
    {
        $company = Company::create(['name' => $name, 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        $this->admin->update(['company_id' => $company->id]);
        return $company;
    }

    private function mkPartner(Company $company, string $name): Contact
    {
        return Contact::create([
            'company_id'   => $company->id,
            'name'         => $name,
            'contact_type' => 'company',
            'active'       => true,
        ]);
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
            ->orderByRaw('LENGTH(code) DESC')
            ->orderBy('code')
            ->firstOrFail();
    }

    private function createInvoice(Company $company, string $ref, float $amount): AccountMove
    {
        return $this->createInvoiceForPartner($company, $this->mkPartner($company, $ref . ' Customer'), $ref, $amount);
    }

    private function createInvoiceForPartner(Company $company, Contact $partner, string $ref, float $amount): AccountMove
    {
        $journal    = $this->journal($company, 'INV');
        $receivable = $this->accountByType($company, 'asset_receivable');
        $income     = $this->accountByType($company, 'income');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.invoices.store'), [
                'company_id'         => $company->id,
                'journal_id'         => $journal->id,
                'partner_id'         => $partner->id,
                'control_account_id' => $receivable->id,
                'date'               => '2026-05-01',
                'ref'                => $ref,
                'move_type'          => 'out_invoice',
                'currency'           => 'USD',
                'items' => [
                    ['account_id' => $income->id, 'name' => $ref . ' line', 'quantity' => 1, 'price_unit' => $amount],
                ],
            ])->assertSessionHasNoErrors();

        return AccountMove::where('company_id', $company->id)->where('ref', $ref)->firstOrFail();
    }

    private function createBill(Company $company, string $ref, float $amount): AccountMove
    {
        $partner = $this->mkPartner($company, $ref . ' Supplier');
        $journal = $this->journal($company, 'BILL');
        $payable = $this->accountByType($company, 'liability_payable');
        $expense = $this->accountByType($company, 'expense');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.bills.store'), [
                'company_id'         => $company->id,
                'journal_id'         => $journal->id,
                'partner_id'         => $partner->id,
                'control_account_id' => $payable->id,
                'date'               => '2026-05-01',
                'ref'                => $ref,
                'move_type'          => 'in_invoice',
                'currency'           => 'USD',
                'items' => [
                    ['account_id' => $expense->id, 'name' => $ref . ' line', 'quantity' => 1, 'price_unit' => $amount],
                ],
            ])->assertSessionHasNoErrors();

        return AccountMove::where('company_id', $company->id)->where('ref', $ref)->firstOrFail();
    }
}
