<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMoveLine;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingHttpTest extends TestCase
{
    use RefreshDatabase;

    private AccountingService $accounting;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->accounting = app(AccountingService::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_new_company_receives_uas_accounts_and_standard_journals(): void
    {
        $company = $this->createCompany('UAS Test Co');

        $this->assertSame(586, Account::where('company_id', $company->id)->count());
        $this->assertSame(6, AccountJournal::where('company_id', $company->id)->count());

        $assets = Account::where('company_id', $company->id)->where('code', '1')->firstOrFail();
        $bank = Account::where('company_id', $company->id)->where('code', '183')->firstOrFail();
        $supplier = Account::where('company_id', $company->id)->where('code', '261')->firstOrFail();

        $this->assertSame('الموجودات', $assets->name);
        $this->assertSame('Assets', $assets->name_en);
        $this->assertSame('asset_current', $assets->account_type);
        $this->assertSame('asset_cash', $bank->account_type);
        $this->assertSame('liquidity', $bank->internal_type);
        $this->assertTrue($bank->reconcile);
        $this->assertSame('liability_payable', $supplier->account_type);
        $this->assertSame('payable', $supplier->internal_type);

        $journalCodes = AccountJournal::where('company_id', $company->id)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $this->assertSame(['BANK', 'BILL', 'CASH', 'EXCH', 'INV', 'MISC'], $journalCodes);
        $this->assertNotNull(AccountJournal::where('company_id', $company->id)->where('code', 'INV')->firstOrFail()->default_account_id);
        $this->assertNotNull(AccountJournal::where('company_id', $company->id)->where('code', 'BANK')->firstOrFail()->default_account_id);
    }

    public function test_admin_can_open_implemented_accounting_pages(): void
    {
        $company = $this->createCompany('Accounting Pages Co');
        $this->postMove($company, 'Visible accounting page move', 125);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.dashboard'))
            ->assertOk()
            ->assertSee('Accounting')
            ->assertSee('Journal Items');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.accounts.index'))
            ->assertOk()
            ->assertSee('Chart of Accounts')
            ->assertSee('الموجودات');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.journals.index'))
            ->assertOk()
            ->assertSee('Journals')
            ->assertSee('MISC');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.moves.index'))
            ->assertOk()
            ->assertSee('Journal Entries')
            ->assertSee('125.00');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.items.index'))
            ->assertOk()
            ->assertSee('Journal Items')
            ->assertSee('Visible accounting page move')
            ->assertSee('125.00');
    }

    public function test_journal_items_are_filtered_by_active_company_context(): void
    {
        $visibleCompany = $this->createCompany('Visible Co');
        $hiddenCompany = $this->createCompany('Hidden Co');

        $this->postMove($visibleCompany, 'Visible line only', 300);
        $this->postMove($hiddenCompany, 'Hidden line only', 900);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$visibleCompany->id]])
            ->get(route('accounting.items.index'))
            ->assertOk()
            ->assertSee('Visible line only')
            ->assertSee('300.00')
            ->assertDontSee('Hidden line only')
            ->assertDontSee('900.00');
    }

    public function test_journal_items_quick_filters_limit_by_line_state(): void
    {
        $company = $this->createCompany('State Filter Co');

        $posted = $this->postMove($company, 'Posted item line', 75);
        $draft = $this->createMove($company, 'Draft item line', 45);

        $this->assertSame('posted', $posted->state);
        $this->assertSame('draft', $draft->state);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.items.index', ['state' => 'posted']))
            ->assertOk()
            ->assertSee('Posted item line')
            ->assertDontSee('Draft item line');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.items.index', ['state' => 'draft']))
            ->assertOk()
            ->assertSee('Draft item line')
            ->assertDontSee('Posted item line');
    }

    public function test_user_without_accounting_permission_cannot_access_accounting_pages(): void
    {
        $company = $this->createCompany('Forbidden Co');
        $user = User::where('email', 'user@example.com')->firstOrFail();
        $user->companies()->syncWithoutDetaching([$company->id]);
        $user->update(['company_id' => $company->id]);

        $this->actingAs($user)
            ->withSession(['active_company_ids' => [$company->id]])
            ->get(route('accounting.items.index'))
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

    private function postMove(Company $company, string $label, float $amount)
    {
        return $this->accounting->postMove($this->createMove($company, $label, $amount));
    }

    private function createMove(Company $company, string $label, float $amount)
    {
        $journal = AccountJournal::where('company_id', $company->id)->where('code', 'MISC')->firstOrFail();
        $debitAccount = Account::where('company_id', $company->id)->where('code', '181')->firstOrFail();
        $creditAccount = Account::where('company_id', $company->id)->where('code', '421')->firstOrFail();

        return $this->accounting->createMove([
            'company_id' => $company->id,
            'journal_id' => $journal->id,
            'date' => now()->toDateString(),
            'move_type' => 'entry',
            'currency' => 'USD',
        ], [
            ['account_id' => $debitAccount->id, 'name' => $label, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $creditAccount->id, 'name' => $label, 'debit' => 0, 'credit' => $amount],
        ]);
    }
}
