<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAccountingSettingsRequest;
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

        $companies = Company::whereIn('id', $activeCompanyIds)
            ->orderBy('name')
            ->get(['id', 'name', 'currency', 'accounting_period_lock_date', 'accounting_fiscal_year_lock_date']);

        return view('accounting.settings.index', compact('companies'));
    }

    public function write(StoreAccountingSettingsRequest $request, Company $company)
    {
        $this->authorize('update', $company);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($company->id, $activeCompanyIds), 403);

        $data = $request->validated();

        // Allow clearing a lock date by submitting an empty string
        $data['accounting_period_lock_date']      = $data['accounting_period_lock_date'] ?: null;
        $data['accounting_fiscal_year_lock_date']  = $data['accounting_fiscal_year_lock_date'] ?: null;

        DB::transaction(fn () => $company->update($data));

        return redirect()->route('accounting.settings')->with('success', "Lock dates updated for {$company->name}.");
    }
}
