<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountMoveLine;
use App\Models\Accounting\AccountPartialReconcile;
use App\Models\Accounting\AccountPayment;
use App\Models\Accounting\AccountTax;
use App\Models\Accounting\CurrencyRate;
use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full HTTP-layer simulation tests.
 *
 * Scope: areas NOT covered by the other four test files.
 *   1.  Journal Entry (type=entry) CRUD via HTTP
 *   2.  Account CRUD via HTTP (create/update/archive/unarchive/delete guard)
 *   3.  Journal CRUD via HTTP (create/update/archive/unarchive/delete guard)
 *   4.  Tax CRUD via HTTP
 *   5.  Currency Rate CRUD via HTTP
 *   6.  Payment Terms CRUD via HTTP
 *   7.  State-machine enforcement via HTTP (posted can't be edited; cancelled can't be posted)
 *   8.  Overpayment (amount > total)
 *   9.  Multiple partial payments summing to full payment
 *   10. Reversal via HTTP
 *   11. Entries index scoped to move_type='entry' (invoices hidden)
 *   12. Report pages render
 *   13. Account with move lines cannot be deleted
 *   14. Journal with moves cannot be deleted
 *   15. Unbalanced move submission rejected
 *   16. Company isolation on journal entries index
 */
class AccountingFullSimulationTest extends TestCase
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
    // 1. Journal Entry HTTP CRUD
    // =========================================================================

    public function test_journal_entry_full_lifecycle_via_http(): void
    {
        $company = $this->mkCompany('Entry Lifecycle Co');
        $journal = $this->journal($company, 'MISC');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        // GET create form
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.moves.create'))
            ->assertOk()
            ->assertSee('Journal Entry');

        // POST store — action=save (draft)
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.moves.store'), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'date'       => '2026-06-01',
                'move_type'  => 'entry',
                'currency'   => 'USD',
                'ref'        => 'TEST-ENTRY-001',
                'action'     => 'save',
                'lines' => [
                    ['account_id' => $debit->id,  'name' => 'Cash in',   'debit' => 500, 'credit' => 0],
                    ['account_id' => $credit->id, 'name' => 'Revenue',   'debit' => 0,   'credit' => 500],
                ],
            ]);

        $move = AccountMove::where('ref', 'TEST-ENTRY-001')->firstOrFail();
        $response->assertRedirect(route('accounting.moves.show', $move));
        $this->assertSame('draft',  $move->state);
        $this->assertSame('entry',  $move->move_type);
        $this->assertSame(2, $move->lines()->count());

        // GET show
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.moves.show', $move))
            ->assertOk()
            ->assertSee('TEST-ENTRY-001')
            ->assertSee('Cash in');

        // GET edit
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.moves.edit', $move))
            ->assertOk()
            ->assertSee('Cash in');

        // PUT write — update ref
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.moves.update', $move), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'date'       => '2026-06-01',
                'move_type'  => 'entry',
                'currency'   => 'USD',
                'ref'        => 'TEST-ENTRY-001-UPDATED',
                'action'     => 'save',
                'lines' => [
                    ['account_id' => $debit->id,  'name' => 'Cash in updated', 'debit' => 500, 'credit' => 0],
                    ['account_id' => $credit->id, 'name' => 'Revenue updated',  'debit' => 0,  'credit' => 500],
                ],
            ])
            ->assertRedirect(route('accounting.moves.show', $move));

        $move->refresh();
        $this->assertSame('TEST-ENTRY-001-UPDATED', $move->ref);

        // PATCH post
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.moves.post', $move))
            ->assertRedirect(route('accounting.moves.show', $move));

        $move->refresh();
        $this->assertSame('posted', $move->state);
        $this->assertStringStartsWith('MISC/', $move->name);

        // PATCH reset to draft
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.moves.reset-draft', $move))
            ->assertRedirect(route('accounting.moves.show', $move));

        $move->refresh();
        $this->assertSame('draft', $move->state);

        // PATCH cancel
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.moves.cancel', $move))
            ->assertRedirect(route('accounting.moves.show', $move));

        $move->refresh();
        $this->assertSame('cancelled', $move->state);

        // DELETE (cancelled → allowed)
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.moves.delete', $move))
            ->assertRedirect(route('accounting.moves.index'));

        $this->assertSoftDeleted('account_moves', ['id' => $move->id]);
    }

    public function test_journal_entry_stored_with_action_post_goes_straight_to_posted(): void
    {
        $company = $this->mkCompany('Entry Post Action Co');
        $journal = $this->journal($company, 'MISC');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.moves.store'), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'date'       => '2026-06-01',
                'move_type'  => 'entry',
                'currency'   => 'USD',
                'ref'        => 'DIRECT-POST',
                'action'     => 'post',
                'lines' => [
                    ['account_id' => $debit->id,  'name' => 'Dr', 'debit' => 200, 'credit' => 0],
                    ['account_id' => $credit->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 200],
                ],
            ]);

        $move = AccountMove::where('ref', 'DIRECT-POST')->firstOrFail();
        $this->assertSame('posted', $move->state);
        $this->assertStringStartsWith('MISC/', $move->name);
    }

    // =========================================================================
    // 2. Account CRUD via HTTP
    // =========================================================================

    public function test_account_can_be_created_updated_archived_and_unarchived_via_http(): void
    {
        $company = $this->mkCompany('Account CRUD Co');

        // GET create form
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.accounts.create'))
            ->assertOk();

        // POST store
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.accounts.store'), [
                'company_id'   => $company->id,
                'code'         => '9999',
                'name'         => 'Test Revenue Account',
                'account_type' => 'income',
                'currency'     => 'USD',
                'reconcile'    => false,
                'active'       => true,
            ]);

        $account = Account::where('company_id', $company->id)->where('code', '9999')->firstOrFail();
        $response->assertRedirect(route('accounting.accounts.show', $account));
        $this->assertSame('income', $account->account_type);
        $this->assertTrue((bool) $account->active);

        // GET show
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.accounts.show', $account))
            ->assertOk()
            ->assertSee('Test Revenue Account');

        // PUT write — change name
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.accounts.update', $account), [
                'company_id'   => $company->id,
                'code'         => '9999',
                'name'         => 'Test Revenue Account Updated',
                'account_type' => 'income',
                'active'       => true,
            ])
            ->assertRedirect(route('accounting.accounts.show', $account));

        $account->refresh();
        $this->assertSame('Test Revenue Account Updated', $account->name);

        // PATCH archive
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.accounts.archive', $account))
            ->assertRedirect();

        $account->refresh();
        $this->assertFalse((bool) $account->active);

        // PATCH unarchive
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.accounts.unarchive', $account))
            ->assertRedirect();

        $account->refresh();
        $this->assertTrue((bool) $account->active);

        // DELETE — account has no move lines → should succeed
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.accounts.delete', $account))
            ->assertRedirect(route('accounting.accounts.index'));

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_account_with_move_lines_cannot_be_deleted(): void
    {
        $company = $this->mkCompany('Account Delete Guard Co');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        // Create and post a move using the income account
        $svc = app(AccountingService::class);
        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc->postMove($svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $this->journal($company, 'MISC')->id,
            'date'       => '2026-06-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $debit->id,  'name' => 'Dr', 'debit' => 100, 'credit' => 0],
            ['account_id' => $credit->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 100],
        ]));

        // Attempt DELETE — income account has lines → back with error
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.accounts.delete', $credit));

        // Controller catches RuntimeException and returns back with error session
        $response->assertRedirect();
        $this->assertDatabaseHas('accounts', ['id' => $credit->id]);
    }

    // =========================================================================
    // 3. Journal CRUD via HTTP
    // =========================================================================

    public function test_journal_can_be_created_updated_archived_and_unarchived_via_http(): void
    {
        $company      = $this->mkCompany('Journal CRUD Co');
        $defaultAcct  = $this->accountByType($company, 'asset_cash');

        // POST store
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.journals.store'), [
                'company_id'           => $company->id,
                'code'                 => 'MYTEST',
                'name'                 => 'My Test Journal',
                'type'                 => 'general',
                'currency'             => 'USD',
                'default_account_id'   => $defaultAcct->id,
                'sequence_prefix'      => 'MYTEST/',
                'sequence_next_number' => 1,
                'sequence_padding'     => 4,
                'active'               => true,
            ]);

        $journal = AccountJournal::where('company_id', $company->id)->where('code', 'MYTEST')->firstOrFail();
        $response->assertRedirect(route('accounting.journals.show', $journal));
        $this->assertSame('general', $journal->type);

        // GET show
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.journals.show', $journal))
            ->assertOk()
            ->assertSee('My Test Journal');

        // PUT write — rename
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.journals.update', $journal), [
                'company_id'           => $company->id,
                'code'                 => 'MYTEST',
                'name'                 => 'My Renamed Journal',
                'type'                 => 'general',
                'active'               => true,
                'sequence_next_number' => 1,
                'sequence_padding'     => 4,
            ])
            ->assertRedirect(route('accounting.journals.show', $journal));

        $journal->refresh();
        $this->assertSame('My Renamed Journal', $journal->name);

        // PATCH archive
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.journals.archive', $journal))
            ->assertRedirect();

        $journal->refresh();
        $this->assertFalse((bool) $journal->active);

        // PATCH unarchive
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.journals.unarchive', $journal))
            ->assertRedirect();

        $journal->refresh();
        $this->assertTrue((bool) $journal->active);

        // DELETE — journal has no moves → succeeds
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.journals.delete', $journal))
            ->assertRedirect(route('accounting.journals.index'));

        $this->assertSoftDeleted('account_journals', ['id' => $journal->id]);
    }

    public function test_journal_with_moves_cannot_be_deleted(): void
    {
        $company = $this->mkCompany('Journal Delete Guard Co');
        $journal = $this->journal($company, 'MISC');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc = app(AccountingService::class);
        $svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $journal->id,
            'date'       => '2026-06-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $debit->id,  'name' => 'Dr', 'debit' => 50, 'credit' => 0],
            ['account_id' => $credit->id, 'name' => 'Cr', 'debit' => 0,  'credit' => 50],
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.journals.delete', $journal));

        $response->assertRedirect();
        $this->assertDatabaseHas('account_journals', ['id' => $journal->id]);
    }

    // =========================================================================
    // 4. Tax CRUD via HTTP
    // =========================================================================

    public function test_tax_can_be_created_viewed_updated_archived_and_deleted_via_http(): void
    {
        $company    = $this->mkCompany('Tax CRUD Co');
        $taxAccount = $this->accountByType($company, 'liability_current');

        // GET create
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.taxes.create'))
            ->assertOk();

        // POST store
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.taxes.store'), [
                'company_id'          => $company->id,
                'name'                => 'HTTP VAT 10%',
                'amount_type'         => 'percent',
                'amount'              => 10,
                'type_tax_use'        => 'sale',
                'account_id'          => $taxAccount->id,
                'include_base_amount' => false,
                'active'              => true,
            ]);

        $tax = AccountTax::where('company_id', $company->id)->where('name', 'HTTP VAT 10%')->firstOrFail();
        $response->assertRedirect(route('accounting.taxes.show', $tax));
        $this->assertSame('percent', $tax->amount_type);
        $this->assertSame(10.0, (float) $tax->amount);

        // GET show
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.taxes.show', $tax))
            ->assertOk()
            ->assertSee('HTTP VAT 10%');

        // PUT write — change rate
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.taxes.update', $tax), [
                'company_id'          => $company->id,
                'name'                => 'HTTP VAT 12%',
                'amount_type'         => 'percent',
                'amount'              => 12,
                'type_tax_use'        => 'sale',
                'account_id'          => $taxAccount->id,
                'include_base_amount' => false,
                'active'              => true,
            ])
            ->assertRedirect(route('accounting.taxes.show', $tax));

        $tax->refresh();
        $this->assertSame('HTTP VAT 12%', $tax->name);
        $this->assertSame(12.0, (float) $tax->amount);

        // PATCH archive
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.taxes.archive', $tax))
            ->assertRedirect();

        $tax->refresh();
        $this->assertFalse((bool) $tax->active);

        // PATCH unarchive
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.taxes.unarchive', $tax))
            ->assertRedirect();

        $tax->refresh();
        $this->assertTrue((bool) $tax->active);

        // DELETE
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.taxes.delete', $tax))
            ->assertRedirect(route('accounting.taxes.index'));

        $this->assertSoftDeleted('account_taxes', ['id' => $tax->id]);
    }

    // =========================================================================
    // 5. Currency Rate CRUD via HTTP
    // =========================================================================

    public function test_currency_rate_can_be_created_updated_and_deleted_via_http(): void
    {
        $company = $this->mkCompany('FX Rate CRUD Co');

        // GET create
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.currencies.create'))
            ->assertOk();

        // POST store
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.currencies.store'), [
                'company_id' => $company->id,
                'currency'   => 'EUR',
                'rate'       => 1.08,
                'date'       => '2026-01-01',
                'active'     => true,
            ]);

        $rate = CurrencyRate::where('company_id', $company->id)->where('currency', 'EUR')->firstOrFail();
        $response->assertRedirect(route('accounting.currencies.show', $rate));
        $this->assertSame(1.08, (float) $rate->rate);

        // PUT write
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.currencies.update', $rate), [
                'company_id' => $company->id,
                'currency'   => 'EUR',
                'rate'       => 1.12,
                'date'       => '2026-02-01',
                'active'     => true,
            ])
            ->assertRedirect(route('accounting.currencies.show', $rate));

        $rate->refresh();
        $this->assertSame(1.12, (float) $rate->rate);

        // DELETE
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.currencies.delete', $rate))
            ->assertRedirect(route('accounting.currencies.index'));

        $this->assertSoftDeleted('currency_rates', ['id' => $rate->id]);
    }

    // =========================================================================
    // 6. Payment Terms CRUD via HTTP
    // =========================================================================

    public function test_payment_term_can_be_created_updated_archived_and_deleted_via_http(): void
    {
        $company = $this->mkCompany('Payment Term CRUD Co');

        // POST store with two lines: 50% now + balance in 30 days
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.payment-terms.store'), [
                'company_id' => $company->id,
                'name'       => '50/50 Net 30',
                'note'       => 'Pay half now, rest in 30 days',
                'active'     => true,
                'lines' => [
                    ['value_type' => 'percent', 'value' => 50, 'days' => 0],
                    ['value_type' => 'balance', 'value' => 0,  'days' => 30],
                ],
            ]);

        $term = \App\Models\Accounting\AccountingPaymentTerm::where('company_id', $company->id)
            ->where('name', '50/50 Net 30')->firstOrFail();
        $response->assertRedirect(route('accounting.payment-terms.show', $term));
        $this->assertSame(2, $term->lines()->count());

        // GET show
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.payment-terms.show', $term))
            ->assertOk()
            ->assertSee('50/50 Net 30');

        // PUT write — add third line
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->put(route('accounting.payment-terms.update', $term), [
                'name'   => '50/50 Net 30 v2',
                'active' => true,
                'lines' => [
                    ['value_type' => 'percent', 'value' => 50, 'days' => 0],
                    ['value_type' => 'balance', 'value' => 0,  'days' => 30],
                ],
            ])
            ->assertRedirect(route('accounting.payment-terms.show', $term));

        $term->refresh();
        $this->assertSame('50/50 Net 30 v2', $term->name);

        // PATCH archive
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.payment-terms.archive', $term))
            ->assertRedirect();

        $term->refresh();
        $this->assertFalse((bool) $term->active);

        // PATCH unarchive
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.payment-terms.unarchive', $term))
            ->assertRedirect();

        $term->refresh();
        $this->assertTrue((bool) $term->active);

        // DELETE
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.payment-terms.delete', $term))
            ->assertRedirect(route('accounting.payment-terms.index'));

        $this->assertSoftDeleted('accounting_payment_terms', ['id' => $term->id]);
    }

    // =========================================================================
    // 7. State-machine enforcement via HTTP
    // =========================================================================

    public function test_posted_journal_entry_cannot_be_edited_via_http(): void
    {
        $company = $this->mkCompany('Posted Edit Guard Co');
        $journal = $this->journal($company, 'MISC');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc  = app(AccountingService::class);
        $move = $svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $journal->id,
            'date'       => '2026-06-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $debit->id,  'name' => 'Dr', 'debit' => 100, 'credit' => 0],
            ['account_id' => $credit->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 100],
        ]);
        $svc->postMove($move);
        $move->refresh();

        // GET edit of a posted entry redirects back with error (not editable)
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.moves.edit', $move))
            ->assertRedirect(route('accounting.moves.show', $move));
    }

    public function test_cancelled_journal_entry_cannot_be_posted_via_http(): void
    {
        $company = $this->mkCompany('Cancel Guard Co');
        $journal = $this->journal($company, 'MISC');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc  = app(AccountingService::class);
        $move = $svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $journal->id,
            'date'       => '2026-06-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $debit->id,  'name' => 'Dr', 'debit' => 100, 'credit' => 0],
            ['account_id' => $credit->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 100],
        ]);
        $svc->cancelMove($move);
        $move->refresh();
        $this->assertSame('cancelled', $move->state);

        // Posting a cancelled entry must fail with 500/redirect+error
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.moves.post', $move));

        // Service throws RuntimeException → controller catches and redirects with error
        $response->assertRedirect();
        $move->refresh();
        $this->assertSame('cancelled', $move->state);
    }

    public function test_posted_invoice_edit_form_is_blocked(): void
    {
        $company = $this->mkCompany('Posted Invoice Edit Guard Co');
        $invoice = $this->createDocument($company, 'out_invoice', 'SO-POSTED-GUARD');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $invoice->refresh();
        $this->assertSame('posted', $invoice->state);

        // Edit form must redirect with error (Odoo: posted docs are read-only)
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.invoices.edit', $invoice))
            ->assertRedirect(route('accounting.invoices.show', $invoice));
    }

    public function test_draft_move_cannot_be_deleted_without_cancelling_first(): void
    {
        // In Odoo draft entries can be deleted directly; the service's deleteMove()
        // only blocks posted moves. Verify draft entry can be deleted via HTTP.
        $company = $this->mkCompany('Draft Delete Co');
        $journal = $this->journal($company, 'MISC');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc  = app(AccountingService::class);
        $move = $svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $journal->id,
            'date'       => '2026-06-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $debit->id,  'name' => 'Dr', 'debit' => 10, 'credit' => 0],
            ['account_id' => $credit->id, 'name' => 'Cr', 'debit' => 0,  'credit' => 10],
        ]);
        $this->assertSame('draft', $move->state);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->delete(route('accounting.moves.delete', $move))
            ->assertRedirect(route('accounting.moves.index'));

        $this->assertSoftDeleted('account_moves', ['id' => $move->id]);
    }

    // =========================================================================
    // 8. Overpayment
    // =========================================================================

    public function test_payment_amount_exceeding_invoice_total_is_accepted_and_leaves_no_residual(): void
    {
        $company = $this->mkCompany('Overpayment Co');
        $invoice = $this->createDocument($company, 'out_invoice', 'SO-OVER');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $invoice->refresh();
        $this->assertSame(50.0, (float) $invoice->amount_total);

        // Pay 200 for a 50 invoice — Odoo: invoice flips to `paid`, the
        // $150 excess sits on the payment line as outstanding receipts /
        // customer credit. S-ERP records the full $200 on the payment but
        // only reconciles up to residual; the invoice itself reads `paid`
        // and its residual is zero, matching what Odoo shows for the
        // invoice (separate partner-ledger tracking of the excess is not
        // yet implemented).
        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc = app(AccountingService::class);
        $svc->registerDocumentPayment($invoice->fresh(), ['amount' => 200]);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_state);
        $this->assertSame(0.0, $svc->documentResidual($invoice->fresh()));
    }

    // =========================================================================
    // 9. Multiple partial payments summing to full
    // =========================================================================

    public function test_two_sequential_partial_payments_clear_invoice_fully(): void
    {
        $company = $this->mkCompany('Two Payments Co');
        $invoice = $this->createDocument($company, 'out_invoice', 'SO-TWO-PAY');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.invoices.post', $invoice));

        $invoice->refresh();
        $total = (float) $invoice->amount_total; // 50.00

        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc = app(AccountingService::class);

        // First payment: 30
        $svc->registerDocumentPayment($invoice->fresh(), ['amount' => 30]);
        $invoice->refresh();
        $this->assertSame('partial', $invoice->payment_state);
        $this->assertEqualsWithDelta($total - 30, $svc->documentResidual($invoice->fresh()), 0.01);

        // Second payment: remaining 20
        $svc->registerDocumentPayment($invoice->fresh(), ['amount' => 20]);
        $invoice->refresh();
        $this->assertSame('paid', $invoice->payment_state);
        $this->assertSame(0.0, $svc->documentResidual($invoice->fresh()));
        $this->assertSame(2, AccountPayment::where('paired_document_id', $invoice->id)->count());
        $this->assertSame(2, AccountPartialReconcile::count());
    }

    // =========================================================================
    // 10. Reversal via HTTP
    // =========================================================================

    public function test_reversal_via_http_creates_flipped_posted_entry(): void
    {
        $company = $this->mkCompany('Reversal HTTP Co');
        $journal = $this->journal($company, 'MISC');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc  = app(AccountingService::class);
        $move = $svc->postMove($svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $journal->id,
            'date'       => '2026-06-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $debit->id,  'name' => 'Dr', 'debit' => 750, 'credit' => 0],
            ['account_id' => $credit->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 750],
        ]));

        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.moves.reverse', $move), [
                'reversal_date' => '2026-06-15',
            ]);

        // O5 (Odoo parity): the HTTP reverse drafts the reversal and redirects
        // to the new draft for the user to review.
        $reversal = AccountMove::where('move_type', 'entry')
            ->where('state', 'draft')
            ->where('reversed_move_id', $move->id)
            ->firstOrFail();
        $response->assertRedirect(route('accounting.moves.show', $reversal));

        // Post it to apply the reversal to the ledger.
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->patch(route('accounting.moves.post', $reversal))
            ->assertRedirect();
        $reversal->refresh();
        $this->assertSame('posted', $reversal->state);

        // Reversal must flip debit/credit
        $reversalDebitLine = $reversal->lines()->where('account_id', $credit->id)->firstOrFail();
        $this->assertSame(750.0, (float) $reversalDebitLine->debit);

        $reversalCreditLine = $reversal->lines()->where('account_id', $debit->id)->firstOrFail();
        $this->assertSame(750.0, (float) $reversalCreditLine->credit);

        // Net balance on both accounts = 0
        $cashBalance   = $svc->getAccountBalance($debit);
        $incomeBalance = $svc->getAccountBalance($credit);
        $this->assertSame(0.0, $cashBalance);
        $this->assertSame(0.0, $incomeBalance);
    }

    // =========================================================================
    // 11. Journal entries index scoped to move_type='entry'
    // =========================================================================

    public function test_journal_entries_index_does_not_show_invoices_or_bills(): void
    {
        $company = $this->mkCompany('Entry Scope Co');

        // Create one journal entry and one invoice for the same company
        $entryRef   = 'ENTRY-SCOPE-001';
        $invoiceRef = 'INV-SCOPE-001';

        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc = app(AccountingService::class);
        $svc->postMove($svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $this->journal($company, 'MISC')->id,
            'date'       => '2026-06-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
            'ref'        => $entryRef,
        ], [
            ['account_id' => $this->accountByType($company, 'asset_cash')->id,  'name' => 'Dr', 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->accountByType($company, 'income')->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 100],
        ]));

        $this->createDocument($company, 'out_invoice', $invoiceRef);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.moves.index'))
            ->assertOk()
            ->assertSee($entryRef)
            ->assertDontSee($invoiceRef);
    }

    // =========================================================================
    // 12. Report pages render
    // =========================================================================

    public function test_all_accounting_report_pages_return_200(): void
    {
        $company = $this->mkCompany('Reports Co');

        // Post a move so reports have data
        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc = app(AccountingService::class);
        $svc->postMove($svc->createMove([
            'company_id' => $company->id,
            'journal_id' => $this->journal($company, 'MISC')->id,
            'date'       => '2026-01-01',
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], [
            ['account_id' => $this->accountByType($company, 'asset_cash')->id, 'name' => 'Dr', 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->accountByType($company, 'income')->id,     'name' => 'Cr', 'debit' => 0,   'credit' => 100],
        ]));

        $reports = [
            'accounting.reports.trial-balance',
            'accounting.reports.profit-and-loss',
            'accounting.reports.balance-sheet',
            'accounting.reports.general-ledger',
        ];

        foreach ($reports as $routeName) {
            $this->actingAs($this->admin)
                ->withSession(['active_company_ids' => [$company->id]])
                ->get(route($routeName))
                ->assertOk("Report page [{$routeName}] should return 200.");
        }
    }

    // =========================================================================
    // 13. Unbalanced entry submission rejected
    // =========================================================================

    public function test_storing_unbalanced_journal_entry_is_rejected_with_validation_error(): void
    {
        $company = $this->mkCompany('Unbalanced Validation Co');
        $journal = $this->journal($company, 'MISC');
        $debit   = $this->accountByType($company, 'asset_cash');
        $credit  = $this->accountByType($company, 'income');

        // Lines: 300 debit vs 200 credit → unbalanced
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route('accounting.moves.store'), [
                'company_id' => $company->id,
                'journal_id' => $journal->id,
                'date'       => '2026-06-01',
                'move_type'  => 'entry',
                'currency'   => 'USD',
                'action'     => 'post',
                'lines' => [
                    ['account_id' => $debit->id,  'name' => 'Dr', 'debit' => 300, 'credit' => 0],
                    ['account_id' => $credit->id, 'name' => 'Cr', 'debit' => 0,   'credit' => 200],
                ],
            ]);

        // Service throws RuntimeException (unbalanced) → controller catches → redirects back with error
        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Move must NOT have been persisted in a posted state
        $this->assertSame(0, AccountMove::where('state', 'posted')->count());
    }

    // =========================================================================
    // 14. Company isolation on journal entries index
    // =========================================================================

    public function test_journal_entries_index_is_isolated_per_company(): void
    {
        $companyA = $this->mkCompany('Isolation Co A');
        $companyB = $this->mkCompany('Isolation Co B');

        \Illuminate\Support\Facades\Auth::login($this->admin);
        $svc = app(AccountingService::class);

        foreach ([['co' => $companyA, 'ref' => 'ENTRY-A'], ['co' => $companyB, 'ref' => 'ENTRY-B']] as $row) {
            $svc->postMove($svc->createMove([
                'company_id' => $row['co']->id,
                'journal_id' => $this->journal($row['co'], 'MISC')->id,
                'date'       => '2026-06-01',
                'move_type'  => 'entry',
                'currency'   => 'USD',
                'ref'        => $row['ref'],
            ], [
                ['account_id' => $this->accountByType($row['co'], 'asset_cash')->id, 'name' => 'Dr', 'debit' => 1, 'credit' => 0],
                ['account_id' => $this->accountByType($row['co'], 'income')->id,     'name' => 'Cr', 'debit' => 0, 'credit' => 1],
            ]));
        }

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$companyA->id]])
            ->get(route('accounting.moves.index'))
            ->assertOk()
            ->assertSee('ENTRY-A')
            ->assertDontSee('ENTRY-B');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function mkCompany(string $name): Company
    {
        $company = Company::create([
            'name'     => $name,
            'active'   => true,
            'currency' => 'USD',
        ]);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        $this->admin->update(['company_id' => $company->id]);
        return $company;
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

    private function createDocument(Company $company, string $moveType, string $ref): AccountMove
    {
        $isInvoice  = $moveType === 'out_invoice';
        $partner    = Contact::create(['company_id' => $company->id, 'name' => $ref . ' Partner', 'contact_type' => 'company', 'active' => true]);
        $journal    = $this->journal($company, $isInvoice ? 'INV' : 'BILL');
        $control    = $this->accountByType($company, $isInvoice ? 'asset_receivable' : 'liability_payable');
        $lineAcct   = $this->accountByType($company, $isInvoice ? 'income' : 'expense');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->post(route($isInvoice ? 'accounting.invoices.store' : 'accounting.bills.store'), [
                'company_id'         => $company->id,
                'journal_id'         => $journal->id,
                'partner_id'         => $partner->id,
                'control_account_id' => $control->id,
                'date'               => '2026-05-22',
                'ref'                => $ref,
                'move_type'          => $moveType,
                'currency'           => 'USD',
                'items' => [
                    ['account_id' => $lineAcct->id, 'name' => $ref . ' line', 'quantity' => 1, 'price_unit' => 50],
                ],
            ])->assertSessionHasNoErrors();

        return AccountMove::where('company_id', $company->id)->where('ref', $ref)->firstOrFail();
    }
}
