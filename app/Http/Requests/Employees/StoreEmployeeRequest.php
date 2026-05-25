<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employees.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        $deptRule         = Rule::exists('hr_departments', 'id')->whereIn('company_id', $activeCompanyIds);
        $jobRule          = Rule::exists('hr_jobs', 'id')->whereIn('company_id', $activeCompanyIds);
        // Manager / coach / approver chains must stay inside the actor's active
        // companies, otherwise employees can be wired into cross-tenant hierarchies.
        $empRule          = Rule::exists('hr_employees', 'id')->whereIn('company_id', $activeCompanyIds);
        $contactRule      = Rule::exists('contacts', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('company_id', $activeCompanyIds);
        });
        // Employee categories are global — the table has no company_id column.
        $categoryRule     = Rule::exists('hr_employee_categories', 'id');

        return [
            'name'              => 'required|string|max:255',
            'name_ar'           => 'nullable|string|max:255',
            'name_en'           => 'nullable|string|max:255',
            'family_name'       => 'nullable|string|max:255',
            'mother_name'       => 'nullable|string|max:255',
            'employee_code'     => 'nullable|string|max:50|unique:hr_employees,employee_code',
            'first_name'        => 'nullable|string|max:100',
            'last_name'         => 'nullable|string|max:100',
            'avatar'            => 'nullable|file|max:2048|mimetypes:image/jpeg,image/png,image/gif,image/webp|mimes:jpg,jpeg,png,gif,webp',
            'barcode'           => 'nullable|string|max:100|unique:hr_employees,barcode',
            'pin_code'          => 'nullable|string|max:20',
            'notes'             => 'nullable|string',

            'work_email'        => 'nullable|email|max:255',
            'work_phone'        => 'nullable|string|max:50',
            'work_mobile'       => 'nullable|string|max:50',
            'job_title'                  => 'nullable|string|max:255',
            'scientific_title'           => 'nullable|string|max:255',
            'company_id'        => ['nullable', $companyRule],
            'department_id'     => ['nullable', $deptRule],
            'job_id'            => ['nullable', $jobRule],
            'work_location_id'  => 'nullable|exists:hr_work_locations,id',
            'resource_calendar_id' => 'nullable|exists:hr_resource_calendars,id',
            'timezone'          => 'nullable|string|max:100',
            'parent_id'         => ['nullable', $empRule],
            'coach_id'          => ['nullable', $empRule],
            'expense_manager_id'    => ['nullable', $empRule],
            'attendance_manager_id' => ['nullable', $empRule],
            'user_id'           => 'nullable|exists:users,id',
            'contact_id'        => ['nullable', $contactRule],

            'private_email'     => 'nullable|email|max:255',
            'private_phone'     => 'nullable|string|max:50',
            'private_mobile'    => 'nullable|string|max:50',
            'private_address'   => 'nullable|string',
            'km_home_work'      => 'nullable|integer|min:0',
            'private_car_plate' => 'nullable|string|max:50',
            'country'           => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:100',
            'city'              => 'nullable|string|max:100',
            'zip'               => 'nullable|string|max:20',
            'nationality'       => 'nullable|string|max:100',
            'identification_id' => 'nullable|string|max:100',
            'passport_id'       => 'nullable|string|max:100',
            'ssnid'             => 'nullable|string|max:100',
            'gender'            => 'nullable|in:male,female,other',
            'birthday'          => 'nullable|date|before:today',
            'place_of_birth'    => 'nullable|string|max:100',
            'country_of_birth'  => 'nullable|string|max:100',
            'marital_status'    => 'nullable|in:single,married,cohabitant,widower,divorced',
            'spouse_name'       => 'nullable|string|max:255',
            'spouse_birthdate'  => 'nullable|date',
            'children'          => 'nullable|integer|min:0',
            'certificate_level' => 'nullable|in:none,graduate,bachelor,master,doctor,other',
            'study_field'       => 'nullable|string|max:255',
            'study_school'      => 'nullable|string|max:255',
            'visa_no'           => 'nullable|string|max:100',
            'work_permit_no'    => 'nullable|string|max:100',
            'visa_expire'       => 'nullable|date',
            'work_permit_expiration_date' => 'nullable|date',

            'employment_status' => 'nullable|in:draft,active,probation,suspended,resigned,terminated',
            'hire_date'         => 'nullable|date',
            'first_contract_date' => 'nullable|date',
            'end_date'          => 'nullable|date',
            'departure_date'    => 'nullable|date',
            'departure_reason_id' => 'nullable|exists:hr_departure_reasons,id',
            'departure_description' => 'nullable|string',
            'probation_start_date' => 'nullable|date',
            'probation_end_date'   => 'nullable|date|after_or_equal:probation_start_date',

            'wage'              => 'nullable|numeric|min:0',
            'payment_method'    => 'nullable|in:cash,bank_transfer,cheque',

            'emergency_contact' => 'nullable|string|max:255',
            'emergency_phone'   => 'nullable|string|max:50',
            'emergency_relation' => 'nullable|string|max:100',

            'categories'        => 'nullable|array',
            'categories.*'      => $categoryRule,
        ];
    }
}
