<?php

namespace App\Http\Requests\Employees;

use App\Models\Employees\RequestSubtype;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestSubtypeRequest extends FormRequest
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
            'name'                 => 'sometimes|required|string|max:255',
            'type'                 => ['sometimes', 'required', Rule::in(array_keys(RequestSubtype::TYPE_LABELS))],
            'cuts_salary'          => 'boolean',
            'cuts_balance'         => 'boolean',
            'factor'               => 'nullable|numeric|min:0.01|max:99.99',
            'requires_title'       => 'boolean',
            'requires_description' => 'boolean',
            'requires_attachment'  => 'boolean',
            'active'               => 'boolean',
            'company_id'           => ['nullable', $companyRule],
        ];
    }
}
