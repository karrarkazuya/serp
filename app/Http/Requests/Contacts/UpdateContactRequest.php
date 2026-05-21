<?php

namespace App\Http\Requests\Contacts;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('contacts.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        $contactRule = Rule::exists('contacts', 'id')->where(function ($query) use ($activeCompanyIds) {
            empty($activeCompanyIds)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('company_id', $activeCompanyIds);
        });

        $contactId = $this->route('contact')?->id;

        return [
            'company_id'   => ['nullable', $companyRule],
            'parent_id'    => ['nullable', $contactRule],
            'related_contacts' => 'nullable|array',
            'related_contacts.*' => [$contactRule],
            'avatar'       => 'nullable|image|max:2048',
            'tags'         => 'nullable|array',
            'tags.*'       => 'exists:tags,id',
            'name'         => 'sometimes|required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'contact_type' => 'sometimes|required|in:individual,company',
            'email'        => 'nullable|email|max:255',
            'phone'        => ['nullable', 'string', 'max:50', Rule::unique('contacts', 'phone')->ignore($contactId)],
            'mobile'       => 'nullable|string|max:50',
            'website'      => 'nullable|url|max:255',
            'street'       => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:100',
            'state'        => 'nullable|string|max:100',
            'country'      => 'nullable|string|max:100',
            'zip'          => 'nullable|string|max:20',
            'tax_id'       => 'nullable|string|max:50',
            'job_position' => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:10000',
        ];
    }
}
