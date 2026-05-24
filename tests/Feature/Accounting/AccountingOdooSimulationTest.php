<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountMoveLine;
use App\Models\Accounting\AccountTax;
use App\Models\Accounting\CurrencyRate;
use App\Models\Chatter\ChatterMessage;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Uom;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\TestCase;

/**
 * Full simulation + Odoo-parity tests for:
 *
 *  1. Taxes          — exclusive %, inclusive %, fixed, multi-tax, merged tax lines
 *  2. Lock dates     — period lock (bypassable), fiscal year lock (hard), boundary behaviour
 *  3. Multi-currency — FX conversion, rate lookup, fallback, inactive rate ignored
 *  4. Audit controls — chatter logged on create/post/reset/cancel; updated_by set on lock change
 *  5. Full simulation — complete January business month; final trial balance = 0
 */
class AccountingOdooSimulationTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $svc;
    private Company $company;

    /** isAdmin() = true → hasPermission() always true → bypasses period lock */
    private User $admin;

    /** no roles → hasPermission() always false → blocked by period lock */
    private User $plain;

    private Account $receivable;
    private Account $payable;
    private Account $income;
    private Account $expense;
    private Account $cash;
    private Account $equity;
    private Account $taxPayable; // liability_current — used as tax account

    private AccountJournal $salesJournal;
    private AccountJournal $purchaseJournal;
    private AccountJournal $bankJournal;
    private AccountJournal $miscJournal;

    private Product $product;
    private Uom $uom;

    // O2 (Odoo parity): the AR/AP control line on every posted invoice/bill
    // requires a partner. Shared partner for the invoice and bill helpers.
    private Contact $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->svc = app(AccountingService::class);

        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        Auth::login($this->admin);

        // Company with USD base (most tests); FX tests update it to IQD
        $this->company = Company::create([
            'name'     => 'Simulation Co',
            'active'   => true,
            'currency' => 'USD',
        ]);
        $this->admin->companies()->syncWithoutDetaching([$this->company->id]);

        // Plain user has no roles at all
        $this->plain = User::factory()->create(['active' => true]);
        $this->plain->companies()->syncWithoutDetaching([$this->company->id]);

        $this->receivable  = $this->acctByType('asset_receivable');
        $this->payable     = $this->acctByType('liability_payable');
        $this->income      = $this->acctByType('income');
        $this->expense     = $this->acctByType('expense');
        $this->cash        = $this->acctByType('asset_cash');
        $this->equity      = $this->acctByType('equity');
        $this->taxPayable  = $this->acctByType('liability_current');

        $this->salesJournal    = $this->journal('INV');
        $this->purchaseJournal = $this->journal('BILL');
        $this->bankJournal     = $this->journal('BANK');
        $this->miscJournal     = $this->journal('MISC');

        // Use the BANK journal's liquidity account as the canonical cash account.
        // Payment entries credit/debit this account; manual cash entries must match.
        $this->cash = $this->bankJournal->defaultAccount
            ?? $this->acctByType('asset_cash');

        // Use the seeded "Units" UoM and create a real inventory product linked to this company.
        $this->uom = Uom::where('name', 'Units')->where('active', true)->firstOrFail();
        $this->product = Product::create([
            'company_id' => $this->company->id,
            'uom_id'     => $this->uom->id,
            'uom_po_id'  => $this->uom->id,
            'name'       => 'Test Service Product',
            'product_type' => 'service',
            'tracking'   => 'none',
            'active'     => true,
        ]);

        // O2 (Odoo parity): AR/AP lines require a partner. Shared contact.
        $this->partner = Contact::create([
            'company_id'   => $this->company->id,
            'name'         => 'Sim Partner',
            'contact_type' => 'company',
            'active'       => true,
        ]);
    }

    // =========================================================================
    // SECTION 1 — Taxes (Odoo parity)
    // =========================================================================

    /**
     * Odoo: exclusive percent tax is ADDED on top of the net base.
     * 15% of $1,000 = $150 tax → $1,150 total.
     */
    public function test_exclusive_percent_tax_adds_tax_on_top_of_net(): void
    {
        $vat = $this->tax('VAT 15%', 'percent', 15.0, 'sale', false);

        $move = $this->svc->createDocument($this->invoiceHdr(), [
            $this->docItem(['name' => 'Consulting', 'price_unit' => 1000, 'tax_ids' => [$vat->id]]),
        ]);

        // 3 lines: product line, tax line, receivable control
        $this->assertSame(3, $move->lines()->count());

        // Product line: credit 1,000, no tax_line_id, tax pivot populated
        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $move->id, 'account_id' => $this->income->id,
            'debit' => 0, 'credit' => 1000, 'tax_line_id' => null,
        ]);
        $productLine = AccountMoveLine::where('move_id', $move->id)
            ->where('account_id', $this->income->id)->firstOrFail();
        $this->assertCount(1, $productLine->taxes);
        $this->assertSame($vat->id, (int) $productLine->taxes->first()->id);

        // Tax line: credit 150, tax_line_id set, tax_base_amount = net base
        $taxLine = AccountMoveLine::where('move_id', $move->id)
            ->whereNotNull('tax_line_id')->firstOrFail();
        $this->assertSame(150.0, (float) $taxLine->credit);
        $this->assertSame(0.0,   (float) $taxLine->debit);
        $this->assertSame($vat->id, (int) $taxLine->tax_line_id);
        $this->assertSame(1000.0, (float) $taxLine->tax_base_amount);

        // Control line (receivable): debit 1,150
        $this->assertDatabaseHas('account_move_lines', [
            'move_id' => $move->id, 'account_id' => $this->receivable->id,
            'debit' => 1150, 'credit' => 0,
        ]);

        $this->assertSame(1150.0, (float) $move->amount_total);
    }

    /**
     * Odoo: inclusive percent tax is EXTRACTED from the gross price.
     * User enters $1,150 (includes 15% VAT) → net = $1,000, tax = $150, total = $1,150.
     */
    public function test_inclusive_percent_tax_extracts_net_from_gross(): void
    {
        $vat = $this->tax('VAT 15% incl', 'percent', 15.0, 'sale', true);

        $move = $this->svc->createDocument($this->invoiceHdr(), [
            $this->docItem(['name' => 'Software', 'price_unit' => 1150, 'tax_ids' => [$vat->id]]),
        ]);

        $productLine = AccountMoveLine::where('move_id', $move->id)
            ->where('account_id', $this->income->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $productLine->credit, 0.005, 'Net extracted from $1,150 gross at 15% inclusive.');

        $taxLine = AccountMoveLine::where('move_id', $move->id)
            ->whereNotNull('tax_line_id')->firstOrFail();
        $this->assertEqualsWithDelta(150.0, (float) $taxLine->credit, 0.005, 'Tax = gross - net = 150.');

        $controlLine = AccountMoveLine::where('move_id', $move->id)
            ->where('account_id', $this->receivable->id)->firstOrFail();
        $this->assertEqualsWithDelta(1150.0, (float) $controlLine->debit, 0.005, 'Receivable equals gross price.');

        $this->assertEqualsWithDelta(1150.0, (float) $move->amount_total, 0.005);
    }

    /**
     * Odoo: fixed-amount tax is a flat amount regardless of the base.
     * $50 excise on 3 × $100 = $300 base → $50 tax, $350 total.
     */
    public function test_fixed_tax_adds_flat_amount_regardless_of_quantity(): void
    {
        $excise = $this->tax('Excise $50', 'fixed', 50.0, 'purchase', false);

        $move = $this->svc->createDocument($this->billHdr(), [
            $this->docItem(['account_id' => $this->expense->id, 'name' => 'Goods', 'quantity' => 3, 'price_unit' => 100, 'tax_ids' => [$excise->id]]),
        ]);

        $productLine = AccountMoveLine::where('move_id', $move->id)
            ->where('account_id', $this->expense->id)->firstOrFail();
        $this->assertSame(300.0, (float) $productLine->debit);

        $taxLine = AccountMoveLine::where('move_id', $move->id)
            ->whereNotNull('tax_line_id')->firstOrFail();
        $this->assertSame(50.0, (float) $taxLine->debit);

        $this->assertSame(350.0, (float) $move->amount_total);
    }

    /**
     * Odoo: two distinct taxes on one line → two separate tax lines.
     * 15% VAT + 5% service fee on $1,000 → $150 + $50 = $200 taxes, $1,200 total.
     */
    public function test_multiple_exclusive_taxes_emit_separate_lines(): void
    {
        $vat = $this->tax('VAT 15%', 'percent', 15.0, 'sale', false);
        $svc = $this->tax('Service 5%', 'percent', 5.0,  'sale', false);

        $move = $this->svc->createDocument($this->invoiceHdr(), [
            $this->docItem(['name' => 'Item', 'price_unit' => 1000, 'tax_ids' => [$vat->id, $svc->id]]),
        ]);

        $taxLines = AccountMoveLine::where('move_id', $move->id)
            ->whereNotNull('tax_line_id')->get();
        $this->assertCount(2, $taxLines, 'One tax line per tax type.');

        $credits = $taxLines->pluck('credit')->map(fn ($v) => (float) $v)->sort()->values()->all();
        $this->assertSame([50.0, 150.0], $credits);

        $this->assertSame(1200.0, (float) $move->amount_total);
    }

    /**
     * Odoo: same tax on multiple product lines is MERGED into one tax line.
     * Item A $500 + Item B 2×$250 = $1,000 base → single VAT line of $150.
     */
    public function test_same_tax_on_multiple_lines_merges_into_one_tax_line(): void
    {
        $vat = $this->tax('VAT 15%', 'percent', 15.0, 'sale', false);

        $move = $this->svc->createDocument($this->invoiceHdr(), [
            $this->docItem(['name' => 'Item A', 'price_unit' => 500,  'tax_ids' => [$vat->id]]),
            $this->docItem(['name' => 'Item B', 'quantity' => 2, 'price_unit' => 250, 'tax_ids' => [$vat->id]]),
        ]);

        // 2 product + 1 merged tax + 1 control = 4
        $this->assertSame(4, $move->lines()->count());

        $taxLine = AccountMoveLine::where('move_id', $move->id)
            ->whereNotNull('tax_line_id')->firstOrFail();
        $this->assertSame(150.0,  (float) $taxLine->credit);
        $this->assertSame(1000.0, (float) $taxLine->tax_base_amount, 'Merged base = 500 + 500.');

        $this->assertSame(1150.0, (float) $move->amount_total);
    }

    /**
     * Posting an invoice that includes taxes must remain perfectly balanced.
     */
    public function test_invoice_with_tax_stays_balanced_after_posting(): void
    {
        $vat = $this->tax('VAT 15%', 'percent', 15.0, 'sale', false);

        $move = $this->svc->createDocument($this->invoiceHdr(), [
            $this->docItem(['name' => 'A', 'quantity' => 5, 'price_unit' => 200, 'tax_ids' => [$vat->id]]),
        ]);

        $posted = $this->svc->postMove($move);

        $this->assertTrue($this->svc->isBalanced($posted));
        $bal = $this->svc->computeMoveBalance($posted);
        $this->assertSame(0.0, $bal['difference']);
        // 5 × 200 = 1,000 + 15% = 1,150
        $this->assertSame(1150.0, $bal['debit']);
    }

    /**
     * Invoice with no taxes: no tax lines should be emitted.
     */
    public function test_invoice_without_taxes_has_only_product_and_control_lines(): void
    {
        $move = $this->svc->createDocument($this->invoiceHdr(), [
            $this->docItem(['name' => 'No-tax item', 'price_unit' => 500]),
        ]);

        $this->assertSame(2, $move->lines()->count(), 'Only product line + control line.');
        $this->assertSame(0, AccountMoveLine::where('move_id', $move->id)
            ->whereNotNull('tax_line_id')->count());
    }

    // =========================================================================
    // SECTION 2 — Lock dates (Odoo parity)
    // =========================================================================

    /**
     * Odoo period lock: users without the lock-bypass permission cannot post
     * entries on or before the period lock date.
     */
    public function test_period_lock_blocks_posting_without_lock_permission(): void
    {
        $this->company->update(['accounting_period_lock_date' => '2026-01-31']);
        Auth::login($this->plain);

        $move = $this->simpleMove('2026-01-15');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked period/i');
        $this->svc->postMove($move);
    }

    /**
     * Odoo: lock date is inclusive — an entry dated exactly on the lock date is blocked.
     */
    public function test_period_lock_blocks_entry_dated_on_the_lock_date_itself(): void
    {
        $this->company->update(['accounting_period_lock_date' => '2026-01-31']);
        Auth::login($this->plain);

        $move = $this->simpleMove('2026-01-31'); // on the boundary

        $this->expectException(RuntimeException::class);
        $this->svc->postMove($move);
    }

    /**
     * Odoo: users with the `accounting.lock` permission bypass the period lock.
     * The admin role carries that permission via isAdmin().
     */
    public function test_period_lock_allows_user_with_lock_permission_to_bypass(): void
    {
        $this->company->update(['accounting_period_lock_date' => '2026-01-31']);
        Auth::login($this->admin); // isAdmin() → hasPermission('accounting.lock') = true

        $posted = $this->svc->postMove($this->simpleMove('2026-01-15'));
        $this->assertSame('posted', $posted->state);
    }

    /**
     * Entries dated AFTER the period lock date are always allowed for everyone.
     */
    public function test_period_lock_allows_posting_after_the_lock_date(): void
    {
        $this->company->update(['accounting_period_lock_date' => '2026-01-31']);
        Auth::login($this->plain);

        $posted = $this->svc->postMove($this->simpleMove('2026-02-01'));
        $this->assertSame('posted', $posted->state);
    }

    /**
     * Odoo fiscal year lock is a HARD lock — nobody can post, not even an admin.
     */
    public function test_fiscal_year_lock_blocks_posting_for_everyone_including_admin(): void
    {
        $this->company->update(['accounting_fiscal_year_lock_date' => '2025-12-31']);
        Auth::login($this->admin);

        $move = $this->simpleMove('2025-12-15');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked fiscal year/i');
        $this->svc->postMove($move);
    }

    /**
     * Fiscal year lock: entry on the exact lock date is blocked.
     */
    public function test_fiscal_year_lock_blocks_entry_on_the_lock_date(): void
    {
        $this->company->update(['accounting_fiscal_year_lock_date' => '2025-12-31']);
        Auth::login($this->admin);

        $move = $this->simpleMove('2025-12-31');

        $this->expectException(RuntimeException::class);
        $this->svc->postMove($move);
    }

    /**
     * Fiscal year lock: entries AFTER the lock date are always allowed.
     */
    public function test_fiscal_year_lock_allows_entries_after_lock_date(): void
    {
        $this->company->update(['accounting_fiscal_year_lock_date' => '2025-12-31']);
        Auth::login($this->admin);

        $posted = $this->svc->postMove($this->simpleMove('2026-01-01'));
        $this->assertSame('posted', $posted->state);
    }

    /**
     * When both locks are set, fiscal year lock takes precedence even for admin
     * (admin can bypass period lock but never fiscal year lock).
     */
    public function test_fiscal_year_lock_takes_precedence_over_period_lock_for_admin(): void
    {
        $this->company->update([
            'accounting_fiscal_year_lock_date' => '2025-12-31',
            'accounting_period_lock_date'      => '2026-01-31',
        ]);
        Auth::login($this->admin); // bypasses period lock but not fiscal year lock

        $move = $this->simpleMove('2025-06-01');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked fiscal year/i');
        $this->svc->postMove($move);
    }

    /**
     * Resetting a move to draft DOES respect the period lock — same rule as
     * posting: an unprivileged user must not be able to silently remove an
     * entry from financial reports by flipping it to draft (state != posted
     * excludes it from balances and reports). Users with the
     * `accounting.lock` permission bypass — same gate postMove uses.
     *
     * (Mirrors Odoo: the lock is a frozen-history guarantee. Reset-to-draft
     * on a locked-period entry is rejected for non-bypass users; the admin
     * who has lock-bypass can still correct mistakes.)
     */
    public function test_reset_to_draft_respects_lock_dates_for_unprivileged_users(): void
    {
        Auth::login($this->admin);
        $posted = $this->svc->postMove($this->simpleMove('2026-01-10'));

        // Now close January
        $this->company->update(['accounting_period_lock_date' => '2026-01-31']);

        // Plain user: blocked.
        Auth::login($this->plain);
        try {
            $this->svc->resetMoveToDraft($posted);
            $this->fail('Plain user should not be able to reset an entry inside a locked period.');
        } catch (RuntimeException $e) {
            $this->assertMatchesRegularExpression('/locked period/i', $e->getMessage());
        }

        // Admin with accounting.lock: allowed.
        Auth::login($this->admin);
        $reset = $this->svc->resetMoveToDraft($posted->fresh());
        $this->assertSame('draft', $reset->state);
    }

    // =========================================================================
    // SECTION 3 — Multi-currency (Odoo parity)
    // =========================================================================

    /**
     * FX conversion: amount_currency in foreign currency, debit/credit left at zero
     * → service converts using the exchange rate into the base currency.
     */
    public function test_fx_converts_amount_currency_to_base_when_debit_credit_are_zero(): void
    {
        $this->switchToIqd();
        CurrencyRate::create([
            'company_id' => $this->company->id, 'currency' => 'USD',
            'rate' => 1310.0, 'date' => '2026-01-01', 'active' => true,
        ]);
        Auth::login($this->admin);

        $move = $this->svc->createMove($this->miscHdr('2026-01-15'), [
            ['account_id' => $this->cash->id,   'name' => 'USD in',  'debit' => 0, 'credit' => 0, 'currency' => 'USD', 'amount_currency' =>  100],
            ['account_id' => $this->income->id, 'name' => 'USD rev', 'debit' => 0, 'credit' => 0, 'currency' => 'USD', 'amount_currency' => -100],
        ]);

        $cashLine = $move->lines()->where('account_id', $this->cash->id)->firstOrFail();
        $this->assertSame(131000.0, (float) $cashLine->debit,  '100 USD × 1310 = 131,000 IQD');
        $this->assertSame(0.0,     (float) $cashLine->credit);

        $revLine = $move->lines()->where('account_id', $this->income->id)->firstOrFail();
        $this->assertSame(0.0,      (float) $revLine->debit);
        $this->assertSame(131000.0, (float) $revLine->credit);

        $this->assertTrue($this->svc->isBalanced($move));
    }

    /**
     * Odoo rate lookup: picks the most recent ACTIVE rate whose date ≤ entry date.
     * Future-dated rates are excluded. Inactive rates are skipped.
     */
    public function test_fx_rate_lookup_picks_most_recent_rate_on_or_before_entry_date(): void
    {
        $this->company->update(['currency' => 'IQD']);
        CurrencyRate::create(['company_id' => $this->company->id, 'currency' => 'USD', 'rate' => 1300.0, 'date' => '2026-01-01', 'active' => true]);
        CurrencyRate::create(['company_id' => $this->company->id, 'currency' => 'USD', 'rate' => 1320.0, 'date' => '2026-03-01', 'active' => true]);
        CurrencyRate::create(['company_id' => $this->company->id, 'currency' => 'USD', 'rate' => 1350.0, 'date' => '2026-06-01', 'active' => true]);
        Auth::login($this->admin);

        // 2026-03-15 → picks 2026-03-01 (1320), not the future 2026-06-01
        $this->assertSame(1320.0, $this->svc->getExchangeRate($this->company->id, 'USD', Carbon::parse('2026-03-15')));

        // 2026-01-15 → picks 2026-01-01 (1300)
        $this->assertSame(1300.0, $this->svc->getExchangeRate($this->company->id, 'USD', Carbon::parse('2026-01-15')));

        // 2025-12-31 → no rate yet → fallback 1.0
        $this->assertSame(1.0, $this->svc->getExchangeRate($this->company->id, 'USD', Carbon::parse('2025-12-31')));
    }

    /**
     * Inactive rates must be ignored in the lookup.
     */
    public function test_fx_inactive_rates_are_excluded_from_lookup(): void
    {
        $this->company->update(['currency' => 'IQD']);
        CurrencyRate::create(['company_id' => $this->company->id, 'currency' => 'USD', 'rate' => 1310.0, 'date' => '2026-01-01', 'active' => false]); // inactive
        CurrencyRate::create(['company_id' => $this->company->id, 'currency' => 'USD', 'rate' => 1200.0, 'date' => '2025-12-01', 'active' => true]);
        Auth::login($this->admin);

        // Should return 1200 (the most recent active rate on/before 2026-01-15)
        $this->assertSame(1200.0, $this->svc->getExchangeRate($this->company->id, 'USD', Carbon::parse('2026-01-15')));
    }

    /**
     * When no rate exists for a currency, the service returns 1.0 (treated as same currency).
     */
    public function test_fx_falls_back_to_1_when_no_rate_exists(): void
    {
        $this->company->update(['currency' => 'IQD']);
        Auth::login($this->admin);

        $this->assertSame(1.0, $this->svc->getExchangeRate($this->company->id, 'EUR', Carbon::parse('2026-01-01')));
    }

    /**
     * Base currency entries always return rate 1.0 without touching the rates table.
     */
    public function test_base_currency_always_returns_rate_1(): void
    {
        $this->company->update(['currency' => 'IQD']);
        Auth::login($this->admin);

        $this->assertSame(1.0, $this->svc->getExchangeRate($this->company->id, 'IQD', Carbon::parse('2026-01-01')));
    }

    /**
     * If debit or credit are explicitly set (non-zero), FX conversion must NOT override them.
     * Only lines where both debit and credit are zero trigger automatic FX.
     */
    public function test_explicit_debit_credit_are_not_overridden_by_fx_conversion(): void
    {
        $this->switchToIqd();
        CurrencyRate::create(['company_id' => $this->company->id, 'currency' => 'USD', 'rate' => 1310.0, 'date' => '2026-01-01', 'active' => true]);
        Auth::login($this->admin);

        $move = $this->svc->createMove($this->miscHdr('2026-01-15'), [
            ['account_id' => $this->cash->id,   'name' => 'Manual', 'debit' => 50000, 'credit' => 0,     'currency' => 'USD', 'amount_currency' =>  100],
            ['account_id' => $this->income->id, 'name' => 'Manual', 'debit' => 0,     'credit' => 50000, 'currency' => 'USD', 'amount_currency' => -100],
        ]);

        $cashLine = $move->lines()->where('account_id', $this->cash->id)->firstOrFail();
        $this->assertSame(50000.0, (float) $cashLine->debit, 'Explicit debit must not be overridden by FX.');
    }

    // =========================================================================
    // SECTION 4 — Audit controls
    // =========================================================================

    /**
     * Chatter messages must be logged when a move is created, posted,
     * reset to draft, and cancelled.
     */
    public function test_chatter_logs_create_post_reset_and_cancel(): void
    {
        Auth::login($this->admin);
        $move = $this->simpleMove('2026-01-10');
        $moveClass = get_class($move); // ChatterService uses get_class(), not getMorphClass()

        // Create message
        $this->assertDatabaseHas('chatter_messages', [
            'model_type' => $moveClass,
            'model_id'   => $move->id,
        ]);

        // Post → "Entry posted as …"
        $posted = $this->svc->postMove($move);
        $this->assertNotNull(
            ChatterMessage::where('model_type', $moveClass)->where('model_id', $posted->id)
                ->where('body', 'like', '%posted%')->first(),
            'Expected "posted as …" chatter message.'
        );

        // Reset to draft
        $this->svc->resetMoveToDraft($posted);
        $this->assertNotNull(
            ChatterMessage::where('model_type', $moveClass)->where('model_id', $posted->id)
                ->where('body', 'like', '%draft%')->first(),
            'Expected "reset to draft" chatter message.'
        );

        // Cancel
        $move->refresh();
        $this->svc->cancelMove($move);
        $this->assertNotNull(
            ChatterMessage::where('model_type', $moveClass)->where('model_id', $posted->id)
                ->where('body', 'like', '%cancel%')->first(),
            'Expected "cancelled" chatter message.'
        );
    }

    /**
     * AuditableObserver sets updated_by when the company's lock dates are changed.
     * This makes lock-date changes auditable in the company's activity log.
     */
    public function test_lock_date_change_is_auditable_via_observer(): void
    {
        Auth::login($this->admin);

        $this->company->update(['accounting_period_lock_date' => '2026-01-31']);
        $this->company->refresh();

        $this->assertNotNull($this->company->updated_by);
        $this->assertSame($this->admin->id, (int) $this->company->updated_by);
    }

    // =========================================================================
    // SECTION 5 — Full January simulation (end-to-end)
    // =========================================================================

    /**
     * Simulate a complete business month:
     *
     *   T1 — Owner investment:       DR Cash 10,000,000     CR Equity 10,000,000
     *   T2 — Customer invoice (IQD): 500,000 + 15% VAT = 575,000 total; posted
     *   T3 — Vendor bill:            Rent expense 200,000; posted
     *   T4 — Full payment of T2:     DR Cash 575,000        CR Receivable 575,000
     *   T5 — Partial payment of T3:  100,000 of 200,000     (partial payment_state)
     *   T6 — FX entry:               1,000 USD × 1,300 =  1,300,000 IQD revenue
     *
     * Expected final balances:
     *   Cash         = +11,775,000    (10M + 575k − 100k + 1.3M)
     *   Receivable   =          0    (cleared by T4)
     *   Payable      =   −100,000    (200k bill − 100k partial)
     *   Equity       = −10,000,000
     *   Income       =  −1,800,000   (500k service + 1.3M FX)
     *   VAT Payable  =     −75,000   (15% of 500k)
     *   Expense      =    +200,000
     *
     * Trial balance (Σ) = 0
     *
     * Then period-lock January: verify plain user blocked, admin bypasses, Feb allowed.
     */
    public function test_full_month_simulation_trial_balance_is_zero(): void
    {
        Auth::login($this->admin);
        $this->switchToIqd();

        // Exchange rate: 1 USD = 1,300 IQD (effective 2026-01-01)
        CurrencyRate::create([
            'company_id' => $this->company->id, 'currency' => 'USD',
            'rate' => 1300.0, 'date' => '2026-01-01', 'active' => true,
        ]);

        // Sales VAT 15% exclusive
        $vat15 = $this->tax('VAT 15%', 'percent', 15.0, 'sale', false);

        // ── T1: Owner investment ──────────────────────────────────────────
        $this->svc->postMove($this->svc->createMove($this->miscHdr('2026-01-05'), [
            ['account_id' => $this->cash->id,   'name' => 'Investment', 'debit' => 10_000_000, 'credit' => 0],
            ['account_id' => $this->equity->id, 'name' => 'Investment', 'debit' => 0, 'credit' => 10_000_000],
        ]));

        // ── T2: Customer invoice 500,000 IQD + 15% VAT ───────────────────
        $invoice = $this->svc->createDocument(
            array_merge($this->invoiceHdr('2026-01-10'), ['currency' => 'IQD']),
            [$this->docItem(['name' => 'Service', 'price_unit' => 500_000, 'tax_ids' => [$vat15->id]])]
        );
        $this->svc->postMove($invoice);
        $invoice->refresh();

        $this->assertSame(575_000.0, (float) $invoice->amount_total, 'Invoice total = 500k + 15%.');
        $this->assertSame('not_paid', $invoice->payment_state);

        // ── T3: Vendor bill (rent) 200,000 IQD ───────────────────────────
        $bill = $this->svc->createDocument(
            array_merge($this->billHdr('2026-01-15'), ['currency' => 'IQD']),
            [$this->docItem(['account_id' => $this->expense->id, 'name' => 'Rent', 'price_unit' => 200_000])]
        );
        $this->svc->postMove($bill);

        // ── T4: Full payment of invoice ───────────────────────────────────
        $this->svc->registerDocumentPayment($invoice->fresh(), ['amount' => 575_000, 'date' => '2026-01-20']);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_state);
        $this->assertSame(0.0, $this->svc->documentResidual($invoice->fresh()));

        // ── T5: Partial payment of bill (100k of 200k) ────────────────────
        $this->svc->registerDocumentPayment($bill->fresh(), ['amount' => 100_000, 'date' => '2026-01-25']);
        $bill->refresh();
        $this->assertSame('partial', $bill->payment_state);
        $this->assertSame(100_000.0, $this->svc->documentResidual($bill->fresh()));

        // ── T6: FX entry — 1,000 USD receipt at 1,300 IQD/USD ─────────────
        $fxMove = $this->svc->createMove($this->miscHdr('2026-01-20'), [
            ['account_id' => $this->cash->id,   'name' => 'USD receipt', 'debit' => 0, 'credit' => 0, 'currency' => 'USD', 'amount_currency' =>  1000],
            ['account_id' => $this->income->id, 'name' => 'USD revenue', 'debit' => 0, 'credit' => 0, 'currency' => 'USD', 'amount_currency' => -1000],
        ]);
        $this->svc->postMove($fxMove);

        // Verify FX conversion: 1,000 × 1,300 = 1,300,000 IQD
        $fxCashLine = AccountMoveLine::where('move_id', $fxMove->id)
            ->where('account_id', $this->cash->id)->firstOrFail();
        $this->assertSame(1_300_000.0, (float) $fxCashLine->debit);

        // ── Final balances ─────────────────────────────────────────────────
        $cashBal      = $this->svc->getAccountBalance($this->cash);
        $recvBal      = $this->svc->getAccountBalance($this->receivable);
        $payBal       = $this->svc->getAccountBalance($this->payable);
        $equityBal    = $this->svc->getAccountBalance($this->equity);
        $incomeBal    = $this->svc->getAccountBalance($this->income);
        $vatBal       = $this->svc->getAccountBalance($this->taxPayable);
        $expenseBal   = $this->svc->getAccountBalance($this->expense);

        $this->assertSame(11_775_000.0, $cashBal,    'Cash: 10M + 575k − 100k + 1.3M');
        $this->assertSame(0.0,          $recvBal,    'Receivable fully cleared by T4.');
        $this->assertSame(-100_000.0,   $payBal,     'Payable: 200k bill − 100k paid.');
        $this->assertSame(-10_000_000.0,$equityBal,  'Equity: owner contribution.');
        $this->assertSame(-1_800_000.0, $incomeBal,  'Income: 500k service + 1.3M FX.');
        $this->assertSame(-75_000.0,    $vatBal,     'VAT payable: 15% × 500k = 75k.');
        $this->assertSame(200_000.0,    $expenseBal, 'Rent expense.');

        $trial = round($cashBal + $recvBal + $payBal + $equityBal + $incomeBal + $vatBal + $expenseBal, 2);
        $this->assertSame(0.0, $trial, 'Accounting equation: trial balance must be zero.');

        // ── Period lock: close January ─────────────────────────────────────
        $this->company->update(['accounting_period_lock_date' => '2026-01-31']);

        // Plain user cannot post in locked period
        Auth::login($this->plain);
        $lockedMove = $this->simpleMove('2026-01-28');
        try {
            $this->svc->postMove($lockedMove);
            $this->fail('Expected RuntimeException for locked period was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsStringIgnoringCase('locked period', $e->getMessage());
        }

        // Admin bypasses period lock
        Auth::login($this->admin);
        $adminMove = $this->simpleMove('2026-01-28');
        $this->assertSame('posted', $this->svc->postMove($adminMove)->state);

        // Anyone can post in February (after lock date)
        Auth::login($this->plain);
        $febMove = $this->simpleMove('2026-02-01');
        $this->assertSame('posted', $this->svc->postMove($febMove)->state);
    }

    // =========================================================================
    // SECTION 6 — Product / UoM linkage
    // =========================================================================

    /**
     * When a real inventory product (with a UoM) is linked to an invoice line,
     * the product_id and uom_id must be persisted on the account_move_line.
     * The product name is auto-derived when the line label is left blank.
     */
    public function test_product_and_uom_are_stored_on_invoice_line(): void
    {
        Auth::login($this->admin);

        $move = $this->svc->createDocument($this->invoiceHdr(), [
            $this->docItem(['account_id' => $this->income->id, 'quantity' => 2, 'price_unit' => 500]),
        ]);

        $productLine = AccountMoveLine::where('move_id', $move->id)
            ->where('account_id', $this->income->id)->firstOrFail();

        $this->assertSame($this->product->id, (int) $productLine->product_id, 'product_id must be stored on the line.');
        $this->assertSame($this->uom->id,     (int) $productLine->uom_id,     'uom_id must be stored on the line.');
    }

    /**
     * When the item name is omitted but a product is linked,
     * the service auto-fills the line label from the product name.
     */
    public function test_product_name_auto_fills_line_label_when_name_is_blank(): void
    {
        Auth::login($this->admin);

        $move = $this->svc->createDocument($this->invoiceHdr(), [
            $this->docItem(['account_id' => $this->income->id, 'name' => '', 'quantity' => 1, 'price_unit' => 100]),
        ]);

        $productLine = AccountMoveLine::where('move_id', $move->id)
            ->where('account_id', $this->income->id)->firstOrFail();

        $this->assertSame($this->product->name, $productLine->name, 'Line label must be auto-filled from product name.');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an account tax. `$inclusive` controls `price_include` (the price
     * already contains the tax — extract net from gross). It is NOT the same
     * thing as `include_base_amount` (cascading flag for "add this tax to the
     * base for the next sequential tax"); historically this helper wrote into
     * the wrong column, so the inclusive-tax test never actually flagged the
     * tax as inclusive.
     */
    private function tax(string $name, string $type, float $amount, string $use, bool $inclusive): AccountTax
    {
        return AccountTax::create([
            'company_id'          => $this->company->id,
            'name'                => $name,
            'amount_type'         => $type,
            'amount'              => $amount,
            'type_tax_use'        => $use,
            'account_id'          => $this->taxPayable->id,
            'price_include'       => $inclusive,
            'include_base_amount' => false,
            'active'              => true,
        ]);
    }

    private function invoiceHdr(string $date = '2026-01-10'): array
    {
        return [
            'company_id'         => $this->company->id,
            'journal_id'         => $this->salesJournal->id,
            // O2: AR control line needs a partner. Always supply one — tests
            // that build documents without posting still tolerate this safely.
            'partner_id'         => $this->partner->id,
            'control_account_id' => $this->receivable->id,
            'date'               => $date,
            'move_type'          => 'out_invoice',
            'currency'           => $this->company->fresh()->currency,
        ];
    }

    private function billHdr(string $date = '2026-01-10'): array
    {
        return [
            'company_id'         => $this->company->id,
            'journal_id'         => $this->purchaseJournal->id,
            'partner_id'         => $this->partner->id,
            'control_account_id' => $this->payable->id,
            'date'               => $date,
            'move_type'          => 'in_invoice',
            'currency'           => $this->company->fresh()->currency,
        ];
    }

    private function miscHdr(string $date = '2026-01-10'): array
    {
        return [
            'company_id' => $this->company->id,
            'journal_id' => $this->miscJournal->id,
            'date'       => $date,
            'move_type'  => 'entry',
            'currency'   => $this->company->fresh()->currency,
        ];
    }

    /** Balanced two-line move for lock-date and audit tests. */
    private function simpleMove(string $date): AccountMove
    {
        return $this->svc->createMove($this->miscHdr($date), [
            ['account_id' => $this->cash->id,   'name' => 'Dr', 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->income->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 100],
        ]);
    }

    private function acctByType(string $type): Account
    {
        return Account::where('company_id', $this->company->id)
            ->where('account_type', $type)
            ->where('active', true)
            ->orderByRaw('LENGTH(code) desc')
            ->orderBy('code')
            ->firstOrFail();
    }

    private function journal(string $code): AccountJournal
    {
        return AccountJournal::where('company_id', $this->company->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Switch the company's base currency to IQD for the FX-conversion tests.
     *
     * The seeded chart pins every account AND every journal to the company's
     * original currency (USD). After re-basing the company to IQD, we also
     * unpin every account and journal so the test can mix IQD-base entries
     * with USD-foreign entries without tripping the O9 currency-pin guard.
     */
    private function switchToIqd(): void
    {
        $this->company->update(['currency' => 'IQD']);
        AccountJournal::where('company_id', $this->company->id)->update(['currency' => null]);
        Account::where('company_id', $this->company->id)->update(['currency' => null]);
    }

    /**
     * Build a document line item pre-populated with the test product and UoM.
     * Callers may override any field via $overrides.
     */
    private function docItem(array $overrides = []): array
    {
        return array_merge([
            'account_id' => $this->income->id,
            'product_id' => $this->product->id,
            'uom_id'     => $this->uom->id,
            'name'       => $this->product->name,
            'quantity'   => 1,
            'price_unit' => 0,
            'tax_ids'    => [],
        ], $overrides);
    }
}
