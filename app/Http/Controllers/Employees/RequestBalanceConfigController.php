<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreRequestBalanceConfigRequest;
use App\Models\Employees\RequestBalanceConfig;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestBalanceConfigController extends Controller
{
    public function __construct(private readonly CompanyContextService $companyContext) {}

    /**
     * Single-page settings: only meaningful when one company is in scope.
     * If the user has multiple companies active, we ask them to switch.
     */
    public function show(Request $_request)
    {
        abort_unless($this->user()->hasPermission('attendance.requests.config'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        if (count($activeCompanyIds) !== 1) {
            return view('employees.request-balance-config.show', [
                'tooManyCompanies' => true,
                'config' => null,
                'companyId' => null,
            ]);
        }
        $companyId = $activeCompanyIds[0];
        $config = RequestBalanceConfig::where('company_id', $companyId)->first();
        return view('employees.request-balance-config.show', compact('config', 'companyId') + ['tooManyCompanies' => false]);
    }

    public function save(StoreRequestBalanceConfigRequest $request)
    {
        $data = $request->validated();
        DB::transaction(function () use ($data) {
            $existing = RequestBalanceConfig::where('company_id', $data['company_id'])->first();
            if ($existing) {
                $existing->update($data);
            } else {
                RequestBalanceConfig::create($data);
            }
        });
        return redirect()->route('employees.request-balance-config.show')
            ->with('success', __('employees.balance_config_saved'));
    }

    private function user()
    {
        return request()->user();
    }
}
