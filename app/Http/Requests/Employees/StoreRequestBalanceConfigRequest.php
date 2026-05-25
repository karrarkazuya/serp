<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestBalanceConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('attendance.requests.config');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule = Rule::exists('companies', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds) ? $q->whereRaw('1 = 0') : $q->whereIn('id', $activeCompanyIds);
        });

        return [
            'company_id'               => ['required', $companyRule],
            'leave_days_per_month'     => 'required|numeric|min:0|max:31',
            'leave_days_max'           => 'required|numeric|min:0|max:366|gte:leave_days_per_month',
            'time_off_hours_per_month' => 'required|numeric|min:0|max:744',
        ];
    }
}
