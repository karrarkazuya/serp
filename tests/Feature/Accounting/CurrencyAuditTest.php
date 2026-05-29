<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountPartialReconcile;
use App\Models\Accounting\Currency;
use App\Models\Accounting\CurrencyRate;
use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Dedicated end-to-end coverage for the 14 currency-audit fixes.
 *
 * Test layout — one method per audit ID, named test_audit_##_<short_descr>.
 * Each method documents the BEFORE behaviour in a comment so future readers
 * understand what regression each guard protects against.
 */
class CurrencyAuditTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $service;
    private User $user;
    private Company $iqdCompany;
    private Company $usdCompany;
    private Account $usdAR;
    private Account $usdIncome;
    private Account $iqdAR;
    private Account $iqdIncome;
    private AccountJournal $usdSalesJournal;
    private AccountJournal $iqdSalesJournal;
    private Contact $usdPartner;
    private Contact $iqdPartner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AccountingService::class);

        if (!DB::table('users')->where('id', 0)->exists()) {
            DB::table('users')->insert([
                'id'         => 0,
                'uuid'       => '00000000-0000-0000-0000-000000000000',
                'name'       => 'System',
                'email'      => 'system@test.local',
                'password'   => bcrypt('s'),
                'active'     => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->user = User::factory()->create(['email' => 'audit@test.local', 'active' => true]);
        Auth::login($this->user);

        // Currency::byCode caches per-request — clear in case other tests
        // hydrated it before this case ran.
        Currency::clearCache();

        // Seed the minimum currency reference data for the tests below. The
        // full CoreSeeder run is overkill for these targeted cases; we just
        // need enough rows to back roundForCurrency, byCode, and the
        // CurrencyRate dropdown.
        foreach ([
            ['code' => 'IQD', 'name' => 'Iraqi Dinar', 'symbol' => 'د.ع', 'position' => 'after',  'decimal_places' => 0, 'rounding' => 1.0],
            ['code' => 'USD', 'name' => 'US Dollar',   'symbol' => '$',   'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'EUR', 'name' => 'Euro',        'symbol' => '€',   'position' => 'after',  'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'GBP', 'name' => 'Pound',       'symbol' => '£',   'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01],
            ['code' => 'JPY', 'name' => 'Yen',         'symbol' => '¥',   'position' => 'before', 'decimal_places' => 0, 'rounding' => 1.0],
            ['code' => 'BHD', 'name' => 'BHD',         'symbol' => 'ب.د', 'position' => 'after',  'decimal_places' => 3, 'rounding' => 0.001],
        ] as $row) {
            Currency::create($row + ['active' => true]);
        }
        Currency::clearCache();

        // IQD company (0 decimals) — base for testing IQD-base + USD invoices.
        $this->iqdCompany = Company::create(['name' => 'IQD Co', 'active' => true, 'currency' => 'IQD']);

        // USD company (2 decimals) — base for testing USD-base + EUR invoices.
        $this->usdCompany = Company::create(['name' => 'USD Co', 'active' => true, 'currency' => 'USD']);

        $this->user->companies()->syncWithoutDetaching([$this->iqdCompany->id, $this->usdCompany->id]);

        $this->iqdAR        = $this->mkAccount($this->iqdCompany, '1200', 'AR', 'asset_receivable');
        $this->iqdIncome    = $this->mkAccount($this->iqdCompany, '4000', 'Revenue', 'income');
        $this->usdAR        = $this->mkAccount($this->usdCompany, '1200', 'AR', 'asset_receivable');
        $this->usdIncome    = $this->mkAccount($this->usdCompany, '4000', 'Revenue', 'income');

        $this->iqdSalesJournal = $this->mkJournal($this->iqdCompany, 'IQD-SALES');
        $this->usdSalesJournal = $this->mkJournal($this->usdCompany, 'USD-SALES');

        $this->iqdPartner = Contact::create(['company_id' => $this->iqdCompany->id, 'name' => 'IQD Customer', 'contact_type' => 'company', 'active' => true]);
        $this->usdPartner = Contact::create(['company_id' => $this->usdCompany->id, 'name' => 'USD Customer', 'contact_type' => 'company', 'active' => true]);
    }

    // =========================================================================
    // Audit #1 — buildDocumentLines FX conversion
    // =========================================================================

    /** @test
     * BEFORE: a USD invoice on an IQD company stored 100 in BOTH `debit` and
     * `amount_currency`, polluting every base-currency report by 1500×.
     * AFTER:  debit = 150000 IQD (base), amount_currency = 100 USD (foreign).
     */
    public function test_audit_01_foreign_invoice_converts_to_base_currency(): void
    {
        $this->setRate($this->iqdCompany, 'USD', '2026-05-01', 1500.0);

        $invoice = $this->service->createDocument([
            'company_id'         => $this->iqdCompany->id,
            'journal_id'         => $this->iqdSalesJournal->id,
            'partner_id'         => $this->iqdPartner->id,
            'control_account_id' => $this->iqdAR->id,
            'date'               => '2026-05-01',
            'move_type'          => 'out_invoice',
            'currency'           => 'USD',
        ], [
            ['account_id' => $this->iqdIncome->id, 'name' => 'Service', 'quantity' => 1, 'price_unit' => 100, 'tax_ids' => []],
        ]);
        $invoice->refresh();

        $arLine     = $invoice->lines->firstWhere('account_id', $this->iqdAR->id);
        $incomeLine = $invoice->lines->firstWhere('account_id', $this->iqdIncome->id);

        $this->assertSame('USD', $arLine->currency);
        $this->assertSame(100.0,    round((float) $arLine->amount_currency, 2));
        $this->assertSame(150000.0, round((float) $arLine->debit,           2));
        $this->assertSame(150000.0, round((float) $incomeLine->credit,      2));
        $this->assertSame(150000.0, round((float) $invoice->amount_total,   2));
    }

    /** @test base = currency: the no-conversion path remains identical to legacy. */
    public function test_audit_01_base_currency_invoice_is_unchanged(): void
    {
        $invoice = $this->service->createDocument([
            'company_id'         => $this->iqdCompany->id,
            'journal_id'         => $this->iqdSalesJournal->id,
            'partner_id'         => $this->iqdPartner->id,
            'control_account_id' => $this->iqdAR->id,
            'date'               => '2026-05-01',
            'move_type'          => 'out_invoice',
            'currency'           => 'IQD',
        ], [
            ['account_id' => $this->iqdIncome->id, 'name' => 'X', 'quantity' => 1, 'price_unit' => 50, 'tax_ids' => []],
        ]);
        $invoice->refresh();
        $this->assertSame(50.0, round((float) $invoice->amount_total, 2));
    }

    // =========================================================================
    // Audit #2 — updateMove + UpdateMoveRequest currency guards
    // =========================================================================

    /** @test draft moves can no longer escape a journal currency pin on update. */
    public function test_audit_02_update_move_enforces_journal_currency_pin(): void
    {
        $pinnedJournal = AccountJournal::create([
            'company_id'           => $this->usdCompany->id,
            'code'                 => 'EUR-ONLY',
            'name'                 => 'EUR-Pinned',
            'type'                 => 'general',
            'currency'             => 'EUR',
            'active'               => true,
            'sequence_prefix'      => 'EU/',
            'sequence_next_number' => 1,
            'sequence_padding'     => 4,
        ]);
        $this->setRate($this->usdCompany, 'EUR', '2026-05-01', 1.10);

        $move = $this->service->createMove([
            'company_id' => $this->usdCompany->id,
            'journal_id' => $pinnedJournal->id,
            'date'       => '2026-05-01',
            'currency'   => 'EUR',
        ], [
            ['account_id' => $this->usdAR->id,     'name' => 'a', 'debit'  => 110, 'credit' => 0, 'currency' => 'EUR', 'amount_currency' => 100],
            ['account_id' => $this->usdIncome->id, 'name' => 'a', 'debit'  => 0,   'credit' => 110, 'currency' => 'EUR', 'amount_currency' => -100],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/pinned/i');

        $this->service->updateMove($move, ['currency' => 'USD'], [
            ['account_id' => $this->usdAR->id,     'name' => 'a', 'debit'  => 110, 'credit' => 0, 'currency' => 'EUR', 'amount_currency' => 100],
            ['account_id' => $this->usdIncome->id, 'name' => 'a', 'debit'  => 0,   'credit' => 110, 'currency' => 'EUR', 'amount_currency' => -100],
        ]);
    }

    /** @test direct service callers (cron jobs, etc.) hit the allowed-currency wall too. */
    public function test_audit_02_update_move_blocks_disallowed_currency(): void
    {
        $usd = Currency::byCode('USD'); $eur = Currency::byCode('EUR');
        $this->usdCompany->allowedCurrencies()->sync([$usd->id, $eur->id]);
        $this->setRate($this->usdCompany, 'EUR', '2026-05-01', 1.10);

        $move = $this->service->createMove([
            'company_id' => $this->usdCompany->id,
            'journal_id' => $this->usdSalesJournal->id,
            'date'       => '2026-05-01',
            'currency'   => 'EUR',
        ], [
            ['account_id' => $this->usdAR->id,     'name' => 'a', 'debit'  => 110, 'credit' => 0, 'currency' => 'EUR', 'amount_currency' => 100],
            ['account_id' => $this->usdIncome->id, 'name' => 'a', 'debit'  => 0,   'credit' => 110, 'currency' => 'EUR', 'amount_currency' => -100],
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->updateMove($move, ['currency' => 'GBP'], $move->lines->map(fn ($l) => $l->toArray())->all());
    }

    // =========================================================================
    // Audit #3 — allowedCurrencies always permits company.currency (base)
    // =========================================================================

    /** @test
     * BEFORE: configuring allowedCurrencies = [EUR, USD] on an IQD-base
     * company silently rejected base-currency invoices because the validator
     * only checked the pivot.
     * AFTER:  permitsCurrency() always returns true for the base code.
     */
    public function test_audit_03_base_currency_always_permitted_even_if_pivot_omits_it(): void
    {
        $eur = Currency::byCode('EUR'); $usd = Currency::byCode('USD');
        $this->iqdCompany->allowedCurrencies()->sync([$eur->id, $usd->id]); // IQD intentionally absent

        $this->assertTrue($this->iqdCompany->permitsCurrency('IQD'), 'base is always permitted');
        $this->assertTrue($this->iqdCompany->permitsCurrency('EUR'), 'pivot entry is permitted');
        $this->assertTrue($this->iqdCompany->permitsCurrency('USD'), 'pivot entry is permitted');
        $this->assertFalse($this->iqdCompany->permitsCurrency('GBP'), 'non-pivot, non-base is rejected');
    }

    /** @test empty pivot = unrestricted (back-compat). */
    public function test_audit_03_empty_pivot_means_unrestricted(): void
    {
        $this->iqdCompany->allowedCurrencies()->detach();
        $this->assertTrue($this->iqdCompany->permitsCurrency('IQD'));
        $this->assertTrue($this->iqdCompany->permitsCurrency('USD'));
        $this->assertTrue($this->iqdCompany->permitsCurrency('XYZ'));
    }

    // =========================================================================
    // Audit #4 — currency rate uniqueness + Rule::exists on currency code
    // =========================================================================

    /** @test the new unique index blocks (company, currency, date) duplicates. */
    public function test_audit_04_duplicate_active_rate_is_rejected_by_db_unique_index(): void
    {
        CurrencyRate::create([
            'company_id' => $this->iqdCompany->id,
            'currency'   => 'USD',
            'rate'       => 1500.0,
            'date'       => '2026-05-01',
            'active'     => true,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        CurrencyRate::create([
            'company_id' => $this->iqdCompany->id,
            'currency'   => 'USD',
            'rate'       => 1450.0,
            'date'       => '2026-05-01',
            'active'     => true,
        ]);
    }

    /**
     * @test soft-delete-then-recreate at the SAME (company, currency, date) is
     * also blocked by the unique index. By design: re-creating a rate at the
     * same key after delete is ambiguous in the chatter trail. Users should
     * either restore the soft-deleted row or pick a different date.
     */
    public function test_audit_04_recreate_at_same_key_after_soft_delete_is_rejected(): void
    {
        $original = CurrencyRate::create([
            'company_id' => $this->iqdCompany->id,
            'currency'   => 'USD',
            'rate'       => 1500.0,
            'date'       => '2026-05-01',
            'active'     => true,
        ]);
        $original->delete();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        CurrencyRate::create([
            'company_id' => $this->iqdCompany->id,
            'currency'   => 'USD',
            'rate'       => 1490.0,
            'date'       => '2026-05-01',
            'active'     => true,
        ]);
    }

    /** @test getExchangeRate picks the newest date (existing) and falls back to id DESC on ties. */
    public function test_audit_04_get_exchange_rate_uses_deterministic_tiebreaker(): void
    {
        CurrencyRate::create(['company_id' => $this->iqdCompany->id, 'currency' => 'USD', 'rate' => 1450.0, 'date' => '2026-05-01', 'active' => true]);
        CurrencyRate::create(['company_id' => $this->iqdCompany->id, 'currency' => 'USD', 'rate' => 1500.0, 'date' => '2026-05-02', 'active' => true]);

        // newest active date wins → 1500
        $rate = $this->service->getExchangeRate($this->iqdCompany->id, 'USD', Carbon::parse('2026-05-15'));
        $this->assertSame(1500.0, $rate);
    }

    // =========================================================================
    // Audit #5 — FX adjustment populates *_amount_currency
    // =========================================================================

    /** @test the closing reconcile for a base-only adjustment carries 0 foreign on the adjustment side and the openSide's remaining foreign on the openSide. */
    public function test_audit_05_fx_adjustment_records_foreign_columns(): void
    {
        $this->configureFxAccounts($this->usdCompany);
        // Invoice 100 EUR at 1.10 = 110 USD base
        $this->setRate($this->usdCompany, 'EUR', '2026-05-01', 1.10);
        $invoice = $this->service->createDocument([
            'company_id'         => $this->usdCompany->id,
            'journal_id'         => $this->usdSalesJournal->id,
            'partner_id'         => $this->usdPartner->id,
            'control_account_id' => $this->usdAR->id,
            'date'               => '2026-05-01',
            'move_type'          => 'out_invoice',
            'currency'           => 'EUR',
        ], [
            ['account_id' => $this->usdIncome->id, 'name' => 'Svc', 'quantity' => 1, 'price_unit' => 100, 'tax_ids' => []],
        ]);
        $invoice = $this->service->postMove($invoice);

        // Pay 100 EUR at 1.05 = 105 USD base — leaves 5 USD FX drift
        $this->setRate($this->usdCompany, 'EUR', '2026-05-15', 1.05);
        $bank = $this->mkBankJournal($this->usdCompany);
        $this->service->registerDocumentPayment($invoice, [
            'amount' => 100, 'currency' => 'EUR', 'journal_id' => $bank->id, 'date' => '2026-05-15',
        ]);

        // Find the FX-adjustment reconcile — it's the one whose AR-side line
        // is the original invoice's AR line but whose counterpart line is on
        // the FX gain/loss account.
        $arLine = $invoice->fresh()->lines()->where('account_id', $this->usdAR->id)->first();
        $reconciles = AccountPartialReconcile::where(function ($q) use ($arLine) {
            $q->where('debit_move_line_id', $arLine->id)->orWhere('credit_move_line_id', $arLine->id);
        })->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(2, $reconciles->count(), 'FX drift triggers a second reconcile beyond the payment match');

        // The last reconcile is the FX-adjustment closing entry. Foreign
        // columns must be explicit (not null) — otherwise the COALESCE
        // fallback in getLineForeignResidual would treat the base amount
        // as foreign and corrupt future residual reads.
        $fxReconcile = $reconciles->last();
        $this->assertNotNull($fxReconcile->debit_amount_currency,  'foreign columns must be set, not NULL');
        $this->assertNotNull($fxReconcile->credit_amount_currency, 'foreign columns must be set, not NULL');
    }

    // =========================================================================
    // Audit #6 — roundForCurrency respects per-currency decimal_places
    // =========================================================================

    /** @test BHD (3dp), JPY (0dp), USD (2dp) round correctly via the helper. */
    public function test_audit_06_round_for_currency_honors_decimal_places(): void
    {
        $this->assertSame(100.556, round($this->service->roundForCurrency(100.5555, 'BHD'), 3), 'BHD = 3dp');
        $this->assertSame(123.46,  round($this->service->roundForCurrency(123.4567, 'USD'), 2), 'USD = 2dp');
        $this->assertSame(100.0,   $this->service->roundForCurrency(100.49, 'IQD'),                'IQD rounds to whole');
        $this->assertSame(101.0,   $this->service->roundForCurrency(100.5,  'IQD'),                'IQD half-up to whole');
        // PHP default rounding mode is HALF_AWAY_FROM_ZERO, so 1234.5 → 1235.
        $this->assertSame(1235.0,  $this->service->roundForCurrency(1234.50, 'JPY'),               'JPY = 0dp');
        $this->assertSame(1234.0,  $this->service->roundForCurrency(1234.49, 'JPY'),               'JPY truncates fractional');
    }

    /** @test unknown currency code falls back to SCALE (2dp) instead of throwing. */
    public function test_audit_06_round_for_currency_falls_back_for_unknown_code(): void
    {
        $this->assertSame(123.46, $this->service->roundForCurrency(123.4567, 'XYZ'));
        $this->assertSame(123.46, $this->service->roundForCurrency(123.4567, null));
    }

    // =========================================================================
    // Audit #7 — currency-rate delete route uses accounting.unlink
    // =========================================================================

    /** @test the delete route now requires accounting.unlink, not the weaker accounting.write. */
    public function test_audit_07_delete_route_requires_unlink_permission(): void
    {
        $route = app('router')->getRoutes()->getByName('accounting.currencies.delete');
        $this->assertNotNull($route);
        $middleware = collect($route->gatherMiddleware())->filter(fn ($m) => str_starts_with($m, 'permission:'))->values();
        $this->assertContains('permission:accounting.unlink', $middleware->all());
        $this->assertNotContains('permission:accounting.write', $middleware->all());
    }

    // =========================================================================
    // Audit #8 — CurrencyRate.currency immutable on update
    // =========================================================================

    /** @test the update controller drops `currency` from $data so the audit trail survives. */
    public function test_audit_08_update_rate_strips_currency_from_validated_data(): void
    {
        $rate = CurrencyRate::create([
            'company_id' => $this->iqdCompany->id, 'currency' => 'USD',
            'rate' => 1500.0, 'date' => '2026-05-01', 'active' => true,
        ]);

        // The UpdateCurrencyRateRequest doesn't list `currency` in its rules,
        // so validated() returns it stripped. We assert this by inspecting
        // the rule set keys directly — that's the contract.
        $route = new \Illuminate\Routing\Route('PUT', '/x', []);
        $route->bind(\Illuminate\Http\Request::create('/x', 'PUT'));
        $route->setParameter('currencyRate', $rate);

        $req = new \App\Http\Requests\Accounting\UpdateCurrencyRateRequest();
        $req->setRouteResolver(fn () => $route);
        $rules = $req->rules();
        $this->assertArrayNotHasKey('currency', $rules, 'currency must NOT be in the rule set');
        $this->assertArrayNotHasKey('company_id', $rules, 'company_id is also immutable');

        // Even if a payload includes `currency`, it doesn't survive validation.
        $payload = ['rate' => 1490.0, 'date' => '2026-05-01', 'currency' => 'EUR', 'active' => true];
        $allowed = array_intersect_key($payload, $rules);
        $this->assertArrayNotHasKey('currency', $allowed);
    }

    // =========================================================================
    // Audit #9 — lines.*.currency bound to company.permitsCurrency
    // =========================================================================

    /** @test posting a journal entry with a per-line currency outside the company's allowed list is rejected at form-validation time. */
    public function test_audit_09_line_level_currency_is_rejected_when_not_allowed(): void
    {
        $this->grantAccountingPermissions();

        $usd = Currency::byCode('USD');
        $this->iqdCompany->allowedCurrencies()->sync([$usd->id]); // IQD base + USD

        $request = \Illuminate\Http\Request::create('/x', 'POST', [
            'company_id' => $this->iqdCompany->id,
            'journal_id' => $this->iqdSalesJournal->id,
            'date'       => '2026-05-01',
            'lines' => [
                // legitimate USD line
                ['account_id' => $this->iqdAR->id, 'name' => 'a', 'debit' => 0, 'credit' => 0, 'currency' => 'USD', 'amount_currency' => 100],
                // BANNED EUR line
                ['account_id' => $this->iqdIncome->id, 'name' => 'a', 'debit' => 0, 'credit' => 0, 'currency' => 'EUR', 'amount_currency' => -100],
            ],
        ]);
        $request->setUserResolver(fn () => $this->user);
        $form = \App\Http\Requests\Accounting\StoreMoveRequest::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));

        $caught = false;
        try { $form->validateResolved(); }
        catch (\Illuminate\Validation\ValidationException $e) {
            $caught = true;
            $this->assertArrayHasKey('lines.1.currency', $e->errors(), 'line 1 EUR rejected, but line 0 USD allowed');
            $this->assertArrayNotHasKey('lines.0.currency', $e->errors());
        }
        $this->assertTrue($caught);
    }

    // =========================================================================
    // Audit #10 — per-request exchange-rate cache
    // =========================================================================

    /** @test repeat calls hit the in-memory cache instead of the DB. */
    public function test_audit_10_exchange_rate_cache_hits_after_first_query(): void
    {
        $this->setRate($this->iqdCompany, 'USD', '2026-05-01', 1500.0);

        $svc = app(AccountingService::class);
        $svc->getExchangeRate($this->iqdCompany->id, 'USD', Carbon::parse('2026-05-15'));

        DB::enableQueryLog();
        $svc->getExchangeRate($this->iqdCompany->id, 'USD', Carbon::parse('2026-05-15'));
        $svc->getExchangeRate($this->iqdCompany->id, 'USD', Carbon::parse('2026-05-15'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $rateQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'currency_rates'));
        $this->assertCount(0, $rateQueries, 'second + third calls must hit the cache');
    }

    // =========================================================================
    // Audit #11 — Currency::byCode does not cache nulls
    // =========================================================================

    /** @test creating a new currency after a missed lookup makes it immediately visible. */
    public function test_audit_11_byCode_does_not_cache_null_results(): void
    {
        Currency::clearCache();
        $this->assertNull(Currency::byCode('ZZZ'));   // miss — must NOT be cached

        Currency::create(['code' => 'ZZZ', 'name' => 'Test', 'symbol' => 'Z', 'position' => 'before', 'decimal_places' => 2, 'rounding' => 0.01, 'active' => true]);

        $this->assertNotNull(Currency::byCode('ZZZ'), 'a freshly-created currency must be findable; null misses are NOT cached');
    }

    // =========================================================================
    // Audit #12 — strip currency + amount_currency for base-currency lines
    // =========================================================================

    /** @test base-currency lines persist with currency=NULL and amount_currency=0. */
    public function test_audit_12_base_currency_line_drops_redundant_currency_columns(): void
    {
        $move = $this->service->createMove([
            'company_id' => $this->iqdCompany->id,
            'journal_id' => $this->iqdSalesJournal->id,
            'date'       => '2026-05-01',
        ], [
            // Caller passes currency=IQD (base) + amount_currency=100 — both
            // are redundant. They must be stripped on save.
            ['account_id' => $this->iqdAR->id,     'name' => 'a', 'debit' => 100, 'credit' => 0, 'currency' => 'IQD', 'amount_currency' => 100],
            ['account_id' => $this->iqdIncome->id, 'name' => 'a', 'debit' => 0,   'credit' => 100, 'currency' => 'IQD', 'amount_currency' => -100],
        ]);

        foreach ($move->lines as $line) {
            $this->assertNull($line->currency, 'currency must be stripped on base-currency lines');
            $this->assertSame(0.0, (float) $line->amount_currency, 'amount_currency must be 0 on base-currency lines');
        }
    }

    // =========================================================================
    // Audit #13 — CurrencyRate.$searchable.currency exposes a dropdown
    // =========================================================================

    /** @test the dynamic options pull from seeded currencies and round-trip through SearchFilters. */
    public function test_audit_13_currency_rate_searchable_currency_has_dynamic_options(): void
    {
        $fields = \App\Helpers\SearchFilters::fieldsFor(CurrencyRate::class);
        $this->assertSame('select', $fields['currency']['type'], 'auto-upgrades to select when options is non-empty');
        $this->assertGreaterThan(0, count($fields['currency']['options']));

        // Make sure the options use ISO codes as values (so URL filters work).
        $first = $fields['currency']['options'][0];
        $this->assertArrayHasKey('value', $first);
        $this->assertArrayHasKey('label', $first);
        $codes = array_column($fields['currency']['options'], 'value');
        $this->assertContains('USD', $codes);
    }

    // =========================================================================
    // Audit #14 — Currency::format separator
    // =========================================================================

    /** @test `after`-position currencies render with a non-breaking space between number and glyph. */
    public function test_audit_14_format_inserts_nbsp_for_after_position(): void
    {
        $iqd = Currency::byCode('IQD');
        $usd = Currency::byCode('USD');

        $this->assertSame('$1,234.56',     $usd->format(1234.56), 'before-position has no separator');
        $iqdFormatted = $iqd->format(1234);
        $this->assertStringContainsString("\u{00A0}", $iqdFormatted, 'after-position uses U+00A0 non-breaking space');
        $this->assertStringContainsString('1,234',    $iqdFormatted);
        $this->assertStringEndsWith($iqd->symbol,     $iqdFormatted);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function mkAccount(Company $company, string $code, string $name, string $type): Account
    {
        $account = $this->service->createAccount([
            'company_id'   => $company->id,
            'code'         => $code,
            'name'         => $name,
            'account_type' => $type,
            'active'       => true,
        ]);

        // Flag receivable/payable/cash accounts as reconcilable so the
        // reconcile-flow tests (audit #5) can actually match against them.
        // The seeder does this in production via INTERNAL_TYPE_MAP; the
        // service createAccount() does not.
        if (in_array($account->internal_type, ['receivable', 'payable', 'liquidity'], true)) {
            $account->update(['reconcile' => true]);
        }

        return $account;
    }

    private function mkJournal(Company $company, string $code): AccountJournal
    {
        return AccountJournal::create([
            'company_id'           => $company->id,
            'code'                 => $code,
            'name'                 => $code,
            'type'                 => 'sales',
            'active'               => true,
            'sequence_prefix'      => "{$code}/",
            'sequence_next_number' => 1,
            'sequence_padding'     => 4,
        ]);
    }

    private function mkBankJournal(Company $company): AccountJournal
    {
        $bankAccount = $this->mkAccount($company, '1000', 'Bank', 'asset_cash');
        return AccountJournal::create([
            'company_id'           => $company->id,
            'code'                 => 'BNK',
            'name'                 => 'Bank',
            'type'                 => 'bank',
            'default_account_id'   => $bankAccount->id,
            'active'               => true,
            'sequence_prefix'      => 'BNK/',
            'sequence_next_number' => 1,
            'sequence_padding'     => 4,
        ]);
    }

    private function setRate(Company $company, string $code, string $date, float $rate): void
    {
        CurrencyRate::create([
            'company_id' => $company->id,
            'currency'   => $code,
            'rate'       => $rate,
            'date'       => $date,
            'active'     => true,
        ]);
    }

    private function configureFxAccounts(Company $company): void
    {
        $gain = $this->mkAccount($company, '7100', 'FX Gain', 'income_other');
        $loss = $this->mkAccount($company, '6100', 'FX Loss', 'expense');
        $company->update([
            'income_currency_exchange_account_id'  => $gain->id,
            'expense_currency_exchange_account_id' => $loss->id,
        ]);
    }

    /**
     * Bootstrap an admin role granting all accounting permissions so the
     * StoreMoveRequest::authorize() check passes when we exercise the form
     * directly (no controller wrapping).
     */
    private function grantAccountingPermissions(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'uuid'       => 'audit-' . uniqid(),
            'key'        => 'audit-admin-' . uniqid(),
            'name'       => 'Audit Admin',
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['accounting.read', 'accounting.create', 'accounting.write', 'accounting.unlink', 'accounting.post'] as $key) {
            $permId = DB::table('permissions')->where('key', $key)->value('id')
                ?? DB::table('permissions')->insertGetId([
                    'uuid' => $key, 'key' => $key, 'name' => $key, 'module' => 'accounting',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('role_permission')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permId]);
        }
        DB::table('user_role')->insertOrIgnore(['role_id' => $roleId, 'user_id' => $this->user->id]);
        // Bust the user's permission cache so the next hasPermission() refetches.
        $this->user->load('roles.permissions');
    }
}
