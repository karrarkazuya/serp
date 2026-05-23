<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end accounting math:
 *   - run real bookkeeping scenarios with concrete dollar amounts
 *   - verify every account balance individually
 *   - prove the accounting equation Σ(debit balances) = Σ(credit balances) holds
 *   - check 3+ line entries
 *   - check decimal/cent arithmetic across many entries
 *   - check reversal cleanly nets a balance back to zero
 *   - check per-journal sequence independence under interleaving
 *   - check as-of-date balances ignore future-dated postings
 */
class AccountingScenariosTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $service;
    private Company $company;
    private User $user;

    private Account $cash;
    private Account $inventory;
    private Account $receivable;
    private Account $payable;
    private Account $tax;
    private Account $equity;
    private Account $revenue;
    private Account $cogs;
    private Account $rent;

    private AccountJournal $cashJournal;
    private AccountJournal $salesJournal;
    private AccountJournal $miscJournal;

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
                'password'   => bcrypt('system'),
                'active'     => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->user = User::factory()->create([
            'name'   => 'Bookkeeper',
            'email'  => 'book@test.local',
            'active' => true,
        ]);
        Auth::login($this->user);

        $this->company = Company::create(['name' => 'Acme Co', 'active' => true]);

        $this->cash       = $this->mkAccount('1000', 'Cash',                'asset_cash');
        $this->inventory  = $this->mkAccount('1100', 'Inventory',           'asset_current');
        $this->receivable = $this->mkAccount('1200', 'Accounts Receivable', 'asset_receivable');
        $this->payable    = $this->mkAccount('2000', 'Accounts Payable',    'liability_payable');
        $this->tax        = $this->mkAccount('2100', 'Sales Tax Payable',   'liability_current');
        $this->equity     = $this->mkAccount('3000', 'Owner Equity',        'equity');
        $this->revenue    = $this->mkAccount('4000', 'Revenue',             'income');
        $this->cogs       = $this->mkAccount('5000', 'Cost of Goods Sold',  'expense_direct_cost');
        $this->rent       = $this->mkAccount('5100', 'Rent Expense',        'expense');

        $this->cashJournal  = $this->mkJournal('BANK', 'Bank',          'bank',     'BNK/');
        $this->salesJournal = $this->mkJournal('INV',  'Sales Journal', 'sales',    'INV/');
        $this->miscJournal  = $this->mkJournal('MISC', 'Miscellaneous', 'general',  'MISC/');
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 1 — full small business week
    // ─────────────────────────────────────────────────────────────────────

    public function test_full_small_business_scenario_balances_each_account_correctly(): void
    {
        // 1. Owner contributes $10,000 cash
        $this->postSimple($this->cashJournal, $this->cash, $this->equity, 10_000, 'Owner contribution');

        // 2. Buy inventory $3,000 cash
        $this->postSimple($this->cashJournal, $this->inventory, $this->cash, 3_000, 'Inventory purchase');

        // 3. Sell goods for $5,000 cash
        $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 5_000, 'Cash sale');

        // 4. Recognise COGS $3,000
        $this->postSimple($this->miscJournal, $this->cogs, $this->inventory, 3_000, 'COGS');

        // 5. Pay rent $500
        $this->postSimple($this->cashJournal, $this->rent, $this->cash, 500, 'Monthly rent');

        // Expected balances (debit positive, credit negative)
        $expected = [
            'cash'      => 11_500.00,   // +10000 -3000 +5000 -500
            'inventory' => 0.00,        // +3000 -3000
            'equity'    => -10_000.00,  // credit
            'revenue'   => -5_000.00,   // credit
            'cogs'      => 3_000.00,    // debit
            'rent'      => 500.00,      // debit
        ];

        $this->assertSame($expected['cash'],      $this->service->getAccountBalance($this->cash));
        $this->assertSame($expected['inventory'], $this->service->getAccountBalance($this->inventory));
        $this->assertSame($expected['equity'],    $this->service->getAccountBalance($this->equity));
        $this->assertSame($expected['revenue'],   $this->service->getAccountBalance($this->revenue));
        $this->assertSame($expected['cogs'],      $this->service->getAccountBalance($this->cogs));
        $this->assertSame($expected['rent'],      $this->service->getAccountBalance($this->rent));

        // The accounting equation: sum of all account balances must be zero.
        $sum = array_sum($expected);
        $this->assertSame(0.00, $sum, 'Manually computed expectations themselves must balance.');

        $live = $this->trialBalanceSum();
        $this->assertSame(0.00, $live, 'Live trial balance (sum of all account balances) must be zero.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 2 — 3+ line entry (purchase with sales tax)
    // ─────────────────────────────────────────────────────────────────────

    public function test_three_line_entry_with_tax_balances(): void
    {
        // Buy $100 of inventory + $10 sales tax = $110 payable
        $move = $this->service->createMove(
            $this->header($this->miscJournal),
            [
                ['account_id' => $this->inventory->id, 'name' => 'Goods',    'debit' => 100, 'credit' => 0],
                ['account_id' => $this->tax->id,       'name' => 'Sales tax', 'debit' => 10,  'credit' => 0],
                ['account_id' => $this->payable->id,   'name' => 'Vendor',    'debit' => 0,   'credit' => 110],
            ]
        );

        $bal = $this->service->computeMoveBalance($move);
        $this->assertSame(110.00, $bal['debit']);
        $this->assertSame(110.00, $bal['credit']);
        $this->assertSame(0.00,   $bal['difference']);

        $this->service->postMove($move);

        $this->assertSame(100.00,  $this->service->getAccountBalance($this->inventory));
        $this->assertSame(10.00,   $this->service->getAccountBalance($this->tax));
        $this->assertSame(-110.00, $this->service->getAccountBalance($this->payable));
        $this->assertSame(0.00,    $this->trialBalanceSum());
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 3 — decimal/cents arithmetic across multiple entries
    // ─────────────────────────────────────────────────────────────────────

    public function test_decimal_amounts_accumulate_correctly(): void
    {
        $amounts = [123.45, 1.05, 100.10, 0.99, 50.51, 7.77];
        // expected cash balance: 283.87
        $expected = round(array_sum($amounts), 2);
        $this->assertSame(283.87, $expected, 'Sanity check on expected sum.');

        foreach ($amounts as $i => $amount) {
            $this->postSimple($this->salesJournal, $this->cash, $this->revenue, $amount, "Sale {$i}");
        }

        $this->assertSame(283.87,  $this->service->getAccountBalance($this->cash));
        $this->assertSame(-283.87, $this->service->getAccountBalance($this->revenue));
        $this->assertSame(0.00,    $this->trialBalanceSum());
    }

    public function test_sub_cent_inputs_are_rounded_consistently(): void
    {
        // Three entries with sub-cent noise that should each round to two decimals.
        $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 10.005, 'Round up');   // 10.01
        $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 20.004, 'Round down'); // 20.00
        $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 30.006, 'Round up');   // 30.01

        // Service rounds via SCALE=2 in syncLines: 10.01 + 20.00 + 30.01 = 60.02
        $this->assertSame(60.02,  $this->service->getAccountBalance($this->cash));
        $this->assertSame(-60.02, $this->service->getAccountBalance($this->revenue));
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 4 — reversal returns balance to zero
    // ─────────────────────────────────────────────────────────────────────

    public function test_posted_then_reversed_entry_nets_to_zero(): void
    {
        $original = $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 750, 'Will be reversed');

        $this->assertSame(750.00,  $this->service->getAccountBalance($this->cash));
        $this->assertSame(-750.00, $this->service->getAccountBalance($this->revenue));

        $reversalDraft = $this->service->reverseMove($original);
        $reversalPosted = $this->service->postMove($reversalDraft);

        $this->assertSame('posted', $reversalPosted->state);
        $this->assertSame(0.00, $this->service->getAccountBalance($this->cash));
        $this->assertSame(0.00, $this->service->getAccountBalance($this->revenue));
        $this->assertSame(0.00, $this->trialBalanceSum());
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 5 — interleaved journals keep independent sequences
    // ─────────────────────────────────────────────────────────────────────

    public function test_multiple_journals_have_independent_sequences_when_interleaved(): void
    {
        $year = date('Y');

        $a1 = $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 100, 'sale 1');
        $b1 = $this->postSimple($this->miscJournal,  $this->rent, $this->cash,    50,  'rent 1');
        $a2 = $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 200, 'sale 2');
        $c1 = $this->postSimple($this->cashJournal,  $this->cash, $this->equity,  500, 'deposit 1');
        $b2 = $this->postSimple($this->miscJournal,  $this->rent, $this->cash,    60,  'rent 2');
        $a3 = $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 300, 'sale 3');

        $this->assertSame("INV/{$year}/0001",  $a1->name);
        $this->assertSame("INV/{$year}/0002",  $a2->name);
        $this->assertSame("INV/{$year}/0003",  $a3->name);
        $this->assertSame("MISC/{$year}/0001", $b1->name);
        $this->assertSame("MISC/{$year}/0002", $b2->name);
        $this->assertSame("BNK/{$year}/0001",  $c1->name);

        // Balances must still be correct after the interleaving.
        $expectedCash    = 100 - 50 + 200 + 500 - 60 + 300; // 990
        $expectedRevenue = -(100 + 200 + 300);              // -600
        $expectedRent    = 50 + 60;                          // 110
        $expectedEquity  = -500;                             // -500

        $this->assertSame((float) $expectedCash,    $this->service->getAccountBalance($this->cash));
        $this->assertSame((float) $expectedRevenue, $this->service->getAccountBalance($this->revenue));
        $this->assertSame((float) $expectedRent,    $this->service->getAccountBalance($this->rent));
        $this->assertSame((float) $expectedEquity,  $this->service->getAccountBalance($this->equity));
        $this->assertSame(0.00, $this->trialBalanceSum());
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 6 — many random entries — trial balance always zero
    // ─────────────────────────────────────────────────────────────────────

    public function test_trial_balance_stays_zero_across_many_random_entries(): void
    {
        $pairs = [
            [$this->cash,       $this->revenue],
            [$this->inventory,  $this->payable],
            [$this->receivable, $this->revenue],
            [$this->rent,       $this->cash],
            [$this->cogs,       $this->inventory],
            [$this->cash,       $this->equity],
        ];

        mt_srand(424242);
        for ($i = 0; $i < 25; $i++) {
            [$dr, $cr] = $pairs[$i % count($pairs)];
            $amount = round(mt_rand(100, 999_999) / 100, 2);
            $this->postSimple($this->miscJournal, $dr, $cr, $amount, "rand-{$i}");
        }

        $this->assertSame(0.00, $this->trialBalanceSum(),
            'After 25 random balanced entries the trial balance must still be exactly zero.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 7 — as-of-date balances exclude future postings
    // ─────────────────────────────────────────────────────────────────────

    public function test_account_balance_as_of_date_excludes_future_entries(): void
    {
        $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 100, 'Jan', Carbon::parse('2026-01-15'));
        $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 200, 'Feb', Carbon::parse('2026-02-15'));
        $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 400, 'Mar', Carbon::parse('2026-03-15'));

        $this->assertSame(100.00, $this->service->getAccountBalance($this->cash, Carbon::parse('2026-01-31')));
        $this->assertSame(300.00, $this->service->getAccountBalance($this->cash, Carbon::parse('2026-02-28')));
        $this->assertSame(700.00, $this->service->getAccountBalance($this->cash, Carbon::parse('2026-12-31')));
        $this->assertSame(700.00, $this->service->getAccountBalance($this->cash));
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 8 — draft entries never affect balances
    // ─────────────────────────────────────────────────────────────────────

    public function test_draft_entries_are_excluded_from_balances(): void
    {
        // 1 posted
        $this->postSimple($this->salesJournal, $this->cash, $this->revenue, 100, 'Posted');

        // 5 draft entries — should be ignored entirely
        for ($i = 0; $i < 5; $i++) {
            $this->service->createMove(
                $this->header($this->salesJournal),
                [
                    ['account_id' => $this->cash->id,    'name' => "Draft {$i}", 'debit' => 9_999, 'credit' => 0],
                    ['account_id' => $this->revenue->id, 'name' => "Draft {$i}", 'debit' => 0,     'credit' => 9_999],
                ]
            );
        }

        $this->assertSame(100.00,  $this->service->getAccountBalance($this->cash));
        $this->assertSame(-100.00, $this->service->getAccountBalance($this->revenue));
        $this->assertSame(0.00,    $this->trialBalanceSum());
    }

    // ─────────────────────────────────────────────────────────────────────
    // SCENARIO 9 — every posted move balances internally too
    // ─────────────────────────────────────────────────────────────────────

    public function test_each_posted_move_balances_at_the_line_level(): void
    {
        $entries = [
            [$this->cash,       $this->revenue,   500.00],
            [$this->inventory,  $this->payable,    75.55],
            [$this->receivable, $this->revenue,   333.33],
            [$this->rent,       $this->cash,       25.00],
        ];

        $posted = [];
        foreach ($entries as $i => [$dr, $cr, $amt]) {
            $posted[] = $this->postSimple($this->miscJournal, $dr, $cr, $amt, "entry-{$i}");
        }

        foreach ($posted as $move) {
            $bal = $this->service->computeMoveBalance($move);
            $this->assertSame($bal['debit'], $bal['credit'],
                "Move {$move->name} debit and credit must be equal.");
            $this->assertSame(0.00, $bal['difference']);
            $this->assertTrue($this->service->isBalanced($move));
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // helpers
    // ─────────────────────────────────────────────────────────────────────

    private function mkAccount(string $code, string $name, string $type): Account
    {
        return $this->service->createAccount([
            'company_id'   => $this->company->id,
            'code'         => $code,
            'name'         => $name,
            'account_type' => $type,
            'active'       => true,
        ]);
    }

    private function mkJournal(string $code, string $name, string $type, string $prefix): AccountJournal
    {
        $journal = AccountJournal::where('company_id', $this->company->id)
            ->where('code', $code)
            ->firstOrFail();

        $journal->update([
            'name'                 => $name,
            'type'                 => $type,
            'sequence_prefix'      => $prefix,
            'sequence_next_number' => 1,
            'sequence_padding'     => 4,
        ]);

        return $journal->fresh();
    }

    private function header(AccountJournal $journal, ?Carbon $date = null): array
    {
        return [
            'company_id' => $this->company->id,
            'journal_id' => $journal->id,
            'date'       => ($date ?? Carbon::today())->toDateString(),
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ];
    }

    private function postSimple(
        AccountJournal $journal,
        Account $debitAccount,
        Account $creditAccount,
        float $amount,
        string $label,
        ?Carbon $date = null
    ): AccountMove {
        $move = $this->service->createMove(
            $this->header($journal, $date),
            [
                ['account_id' => $debitAccount->id,  'name' => $label, 'debit' => $amount, 'credit' => 0],
                ['account_id' => $creditAccount->id, 'name' => $label, 'debit' => 0,       'credit' => $amount],
            ]
        );
        return $this->service->postMove($move);
    }

    /**
     * Sum of every account balance — the accounting equation requires this to be zero.
     */
    private function trialBalanceSum(): float
    {
        $total = 0.0;
        foreach ([
            $this->cash, $this->inventory, $this->receivable, $this->payable, $this->tax,
            $this->equity, $this->revenue, $this->cogs, $this->rent,
        ] as $account) {
            $total += $this->service->getAccountBalance($account);
        }
        return round($total, 2);
    }
}
