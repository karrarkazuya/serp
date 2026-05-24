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
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 1 accounting tests:
 *   - balanced/unbalanced math
 *   - posting reserves a sequence and locks state
 *   - sequence increments per journal
 *   - reversal flips debit and credit
 *   - posted-state guards
 *   - account balance reflects only posted lines
 *   - multi-company isolation
 */
class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $service;
    private Company $company;
    private User $user;

    private Account $cash;
    private Account $revenue;
    private Account $payable;
    private AccountJournal $miscJournal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AccountingService::class);

        // AuditableObserver writes created_by=0 (system user) when no auth is set.
        // Bootstrap the system user up-front so FK constraints don't reject inserts.
        // Raw insert is used because SQLite autoincrement can override id=0 in Eloquent.
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
            'name'     => 'Test Accountant',
            'email'    => 'accountant@test.local',
            'password' => bcrypt('secret'),
            'active'   => true,
        ]);
        Auth::login($this->user);

        $this->company = Company::create([
            'name'   => 'Test Co',
            'email'  => 'biz@test.local',
            'active' => true,
        ]);

        $this->cash    = $this->service->createAccount($this->payload('1000', 'Cash', 'asset_cash'));
        $this->revenue = $this->service->createAccount($this->payload('4000', 'Sales Revenue', 'income'));
        $this->payable = $this->service->createAccount($this->payload('2000', 'Accounts Payable', 'liability_payable'));

        $this->miscJournal = $this->resetSeededJournal('MISC', 'Miscellaneous', 'general', 'MISC/');
    }

    private function payload(string $code, string $name, string $type, ?int $companyId = null): array
    {
        return [
            'company_id'   => $companyId ?? $this->company->id,
            'code'         => $code,
            'name'         => $name,
            'account_type' => $type,
            'active'       => true,
        ];
    }

    /** @test */
    public function test_it_creates_a_balanced_draft_move(): void
    {
        $move = $this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Cash in',  'debit'  => 100, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Revenue',  'debit'  => 0,   'credit' => 100],
        ]);

        $this->assertSame('draft', $move->state);
        $this->assertNull($move->name);
        $this->assertCount(2, $move->lines);
        $this->assertTrue($this->service->isBalanced($move));

        $balance = $this->service->computeMoveBalance($move);
        $this->assertSame(100.0, $balance['debit']);
        $this->assertSame(100.0, $balance['credit']);
        $this->assertSame(0.0,   $balance['difference']);
    }

    /** @test */
    public function test_it_refuses_to_post_an_unbalanced_move(): void
    {
        $move = $this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Cash in', 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Revenue', 'debit' => 0,   'credit' => 90],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Journal entry is not balanced');

        $this->service->postMove($move);
    }

    /** @test */
    public function test_it_posts_a_balanced_move_and_assigns_a_sequence(): void
    {
        $move = $this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Cash in', 'debit' => 250, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Revenue', 'debit' => 0,   'credit' => 250],
        ]);

        $posted = $this->service->postMove($move);

        $this->assertSame('posted', $posted->state);
        $this->assertNotNull($posted->name);
        $this->assertStringStartsWith('MISC/', $posted->name);
        $this->assertStringContainsString('/' . date('Y') . '/', $posted->name);
        $this->assertStringEndsWith('0001', $posted->name);
        $this->assertSame(250.0, (float) $posted->amount_total);

        foreach ($posted->lines as $line) {
            $this->assertSame('posted', $line->state);
        }
    }

    /** @test */
    public function test_it_increments_the_sequence_per_journal(): void
    {
        $first = $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'A', 'debit' => 10, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'A', 'debit' => 0,  'credit' => 10],
        ]));

        $second = $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'B', 'debit' => 20, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'B', 'debit' => 0,  'credit' => 20],
        ]));

        $year = date('Y');
        $this->assertSame("MISC/{$year}/0001", $first->name);
        $this->assertSame("MISC/{$year}/0002", $second->name);
        $this->assertSame(3, (int) $this->miscJournal->fresh()->sequence_next_number);
    }

    /** @test */
    public function test_it_rejects_a_line_with_both_debit_and_credit(): void
    {
        $move = $this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Bad',  'debit' => 50, 'credit' => 50],
            ['account_id' => $this->revenue->id, 'name' => 'Good', 'debit' => 0,  'credit' => 0],
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->postMove($move);
    }

    /** @test */
    public function test_it_rejects_an_entry_with_zero_amount_lines(): void
    {
        $move = AccountMove::create([
            'company_id' => $this->company->id,
            'journal_id' => $this->miscJournal->id,
            'date'       => Carbon::today(),
            'state'      => 'draft',
            'move_type'  => 'entry',
        ]);
        $move->lines()->create([
            'company_id' => $this->company->id,
            'journal_id' => $this->miscJournal->id,
            'account_id' => $this->cash->id,
            'name'       => 'Empty',
            'date'       => Carbon::today(),
            'state'      => 'draft',
            'debit'      => 0,
            'credit'     => 0,
        ]);
        $move->lines()->create([
            'company_id' => $this->company->id,
            'journal_id' => $this->miscJournal->id,
            'account_id' => $this->revenue->id,
            'name'       => 'Empty',
            'date'       => Carbon::today(),
            'state'      => 'draft',
            'debit'      => 0,
            'credit'     => 0,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->postMove($move->fresh('lines'));
    }

    /** @test */
    public function test_it_creates_a_reversal_with_flipped_debit_and_credit(): void
    {
        $posted = $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Sale',    'debit' => 500, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Revenue', 'debit' => 0,   'credit' => 500],
        ]));

        // O5 (Odoo parity): reverseMove drafts the reversal for review; the
        // caller is responsible for posting it. Post here so the rest of the
        // test (immutable-line and balance assertions) sees the final state.
        $reversal = $this->service->reverseMove($posted, Carbon::today()->addDay());
        $this->assertSame('draft', $reversal->state, 'Reversal must be drafted, not auto-posted');

        $reversal = $this->service->postMove($reversal);
        $this->assertSame('posted', $reversal->state);
        $this->assertSame($posted->id, $reversal->reversed_move_id);
        $this->assertCount(2, $reversal->lines);

        $byAccount = $reversal->lines->keyBy('account_id');
        $this->assertSame(0.0,   (float) $byAccount[$this->cash->id]->debit);
        $this->assertSame(500.0, (float) $byAccount[$this->cash->id]->credit);
        $this->assertSame(500.0, (float) $byAccount[$this->revenue->id]->debit);
        $this->assertSame(0.0,   (float) $byAccount[$this->revenue->id]->credit);

        $this->assertTrue($this->service->isBalanced($reversal));
    }

    /** @test */
    public function test_it_refuses_to_reverse_a_draft_move(): void
    {
        $draft = $this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'X', 'debit' => 10, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'X', 'debit' => 0,  'credit' => 10],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only posted entries can be reversed.');

        $this->service->reverseMove($draft);
    }

    /** @test */
    public function test_it_keeps_the_sequence_when_resetting_a_posted_move_to_draft(): void
    {
        $posted = $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Y', 'debit' => 30, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Y', 'debit' => 0,  'credit' => 30],
        ]));

        $name = $posted->name;
        $reset = $this->service->resetMoveToDraft($posted);

        $this->assertSame('draft', $reset->state);
        $this->assertSame($name, $reset->name, 'Sequence number must be preserved to avoid gaps.');
        $this->assertNull($reset->posted_at);
    }

    /** @test */
    public function test_it_refuses_to_delete_a_posted_move(): void
    {
        $posted = $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Z', 'debit' => 1, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Z', 'debit' => 0, 'credit' => 1],
        ]));

        $this->expectException(RuntimeException::class);
        $this->service->deleteMove($posted);
    }

    /** @test */
    public function test_it_refuses_to_edit_a_posted_move(): void
    {
        $posted = $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'A', 'debit' => 1, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'A', 'debit' => 0, 'credit' => 1],
        ]));

        $this->expectException(RuntimeException::class);
        $this->service->updateMove($posted, ['ref' => 'changed'], [
            ['account_id' => $this->cash->id,    'name' => 'A', 'debit' => 2, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'A', 'debit' => 0, 'credit' => 2],
        ]);
    }

    /** @test */
    public function test_it_computes_account_balance_from_posted_lines_only(): void
    {
        $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Receipt 1', 'debit' => 100, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Receipt 1', 'debit' => 0,   'credit' => 100],
        ]));
        $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Receipt 2', 'debit' => 250, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Receipt 2', 'debit' => 0,   'credit' => 250],
        ]));

        // A draft entry that should NOT influence balances
        $this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Pending', 'debit' => 999, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Pending', 'debit' => 0,   'credit' => 999],
        ]);

        $this->assertSame(350.0,  $this->service->getAccountBalance($this->cash));
        $this->assertSame(-350.0, $this->service->getAccountBalance($this->revenue));
    }

    /** @test */
    public function test_it_rounds_to_two_decimals_for_balance_comparison(): void
    {
        // Cents that exceed 2dp must round before comparison.
        $move = $this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Cents', 'debit' => 100.001, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Cents', 'debit' => 0,       'credit' => 100.004],
        ]);

        $this->assertTrue($this->service->isBalanced($move));
        $posted = $this->service->postMove($move);
        $this->assertSame('posted', $posted->state);
    }

    /** @test */
    public function test_it_rejects_lines_that_belong_to_a_different_company(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'active' => true]);
        $otherAccount = $this->service->createAccount($this->payload('9999', 'Other Cash', 'asset_cash', $otherCompany->id));

        $move = $this->makeMove([
            ['account_id' => $this->cash->id,     'name' => 'X', 'debit' => 10, 'credit' => 0],
            ['account_id' => $otherAccount->id,   'name' => 'X', 'debit' => 0,  'credit' => 10],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('different company');

        $this->service->postMove($move);
    }

    /** @test */
    public function test_it_blocks_deleting_an_account_with_journal_entries(): void
    {
        $this->service->postMove($this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Bind', 'debit' => 5, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Bind', 'debit' => 0, 'credit' => 5],
        ]));

        $this->expectException(RuntimeException::class);
        $this->service->deleteAccount($this->cash);
    }

    /** @test */
    public function test_it_blocks_deleting_a_journal_with_entries(): void
    {
        $this->makeMove([
            ['account_id' => $this->cash->id,    'name' => 'Bind', 'debit' => 5, 'credit' => 0],
            ['account_id' => $this->revenue->id, 'name' => 'Bind', 'debit' => 0, 'credit' => 5],
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->deleteJournal($this->miscJournal);
    }

    /** @test */
    public function test_archived_account_is_excluded_from_active_scope(): void
    {
        $this->service->archiveAccount($this->payable);

        $activeIds = Account::active()->pluck('id')->all();
        $this->assertContains($this->cash->id,    $activeIds);
        $this->assertContains($this->revenue->id, $activeIds);
        $this->assertNotContains($this->payable->id, $activeIds);
    }

    /** @test */
    public function test_it_resolves_internal_type_from_account_type(): void
    {
        $this->assertSame('liquidity',  $this->cash->internal_type);
        $this->assertSame('payable',    $this->payable->internal_type);
        $this->assertSame('other',      $this->revenue->internal_type);

        $receivable = $this->service->createAccount($this->payload('1200', 'AR', 'asset_receivable'));
        $this->assertSame('receivable', $receivable->internal_type);
    }

    // ─────────────────────────────────────────────────────────────────────
    // helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeMove(array $lines, ?int $journalId = null, ?Carbon $date = null): AccountMove
    {
        return $this->service->createMove([
            'company_id' => $this->company->id,
            'journal_id' => $journalId ?? $this->miscJournal->id,
            'date'       => ($date ?? Carbon::today())->toDateString(),
            'move_type'  => 'entry',
            'currency'   => 'USD',
        ], $lines);
    }

    private function resetSeededJournal(string $code, string $name, string $type, string $prefix): AccountJournal
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
}
