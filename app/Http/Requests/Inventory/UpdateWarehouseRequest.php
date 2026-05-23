<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->hasPermission('inventory.config'); }

    public function rules(): array
    {
        return [
            'partner_id'      => ['nullable', 'exists:contacts,id'],
            'name'            => ['required', 'string', 'max:255'],
            'reception_steps' => ['required', Rule::in(['one_step', 'two_steps', 'three_steps'])],
            'delivery_steps'  => ['required', Rule::in(['one_step', 'two_steps', 'three_steps'])],
        ];
    }
}
