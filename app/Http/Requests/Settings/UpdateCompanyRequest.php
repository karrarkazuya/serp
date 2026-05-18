<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('companies.write');
    }

    public function rules(): array
    {
        return [
            'name'     => 'sometimes|required|string|max:255',
            'email'    => 'nullable|email|max:255',
            'phone'    => 'nullable|string|max:50',
            'mobile'   => 'nullable|string|max:50',
            'website'  => 'nullable|url|max:255',
            'street'   => 'nullable|string|max:255',
            'city'     => 'nullable|string|max:100',
            'state'    => 'nullable|string|max:100',
            'country'  => 'nullable|string|max:100',
            'zip'      => 'nullable|string|max:20',
            'tax_id'   => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'notes'    => 'nullable|string',
        ];
    }
}
