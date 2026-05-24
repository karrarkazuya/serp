<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    use InventoryFkRules;

    public function authorize(): bool { return $this->user()->hasPermission('inventory.config'); }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        return [
            'partner_id'      => ['nullable', $this->contactInActiveCompaniesRule($activeCompanyIds)],
            'name'            => ['required', 'string', 'max:255'],
            'reception_steps' => ['required', Rule::in(['one_step', 'two_steps', 'three_steps'])],
            'delivery_steps'  => ['required', Rule::in(['one_step', 'two_steps', 'three_steps'])],
        ];
    }
}
