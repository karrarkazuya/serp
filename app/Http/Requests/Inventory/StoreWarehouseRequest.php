<?php

namespace App\Http\Requests\Inventory;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->hasPermission('inventory.config'); }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        return [
            'company_id'      => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'partner_id'      => ['nullable', 'exists:contacts,id'],
            'name'            => ['required', 'string', 'max:255'],
            'short_name'      => ['required', 'string', 'max:8'],
            'reception_steps' => ['required', Rule::in(['one_step', 'two_steps', 'three_steps'])],
            'delivery_steps'  => ['required', Rule::in(['one_step', 'two_steps', 'three_steps'])],
        ];
    }
}
