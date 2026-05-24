<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAccountingSettingsRequest;
use App\Models\Accounting\Currency;
use App\Models\Settings\Company;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingSettingsController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $companies = Company::with(['allowedCurrencies', 'incomeCurrencyExchangeAccount', 'expenseCurrencyExchangeAccount'])
            ->whereIn('id', $activeCompanyIds)
            ->orderBy('name')
            ->get();

        // MC3: the Multi-Currency panel needs the full active-currency list
        // for the allowed-currencies multi-select.
        $currencies = Currency::active()->orderBy('code')->get();

        return view('accounting.settings.index', compact('companies', 'currencies'));
    }

    public function write(StoreAccountingSettingsRequest $request, Company $company)
    {
        $this->authorize('update', $company);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($company->id, $activeCompanyIds), 403);

        $data = $request->validated();

        // Allow clearing a lock date / FK by submitting an empty string
        $data['accounting_period_lock_date']         = $data['accounting_period_lock_date']         ?: null;
        $data['accounting_fiscal_year_lock_date']    = $data['accounting_fiscal_year_lock_date']    ?: null;
        $data['income_currency_exchange_account_id'] = $data['income_currency_exchange_account_id'] ?: null;
        $data['expense_currency_exchange_account_id']= $data['expense_currency_exchange_account_id']?: null;

        // Allowed currencies = M2M; pull out before model->update()
        $allowedCurrencyIds = $data['allowed_currency_ids'] ?? null;
        unset($data['allowed_currency_ids']);

        DB::transaction(function () use ($company, $data, $allowedCurrencyIds) {
            $company->update($data);
            if ($allowedCurrencyIds !== null) {
                $company->allowedCurrencies()->sync(array_map('intval', $allowedCurrencyIds));
            }
        });

        return redirect()->route('accounting.settings')->with('success', "Settings updated for {$company->name}.");
    }
}
