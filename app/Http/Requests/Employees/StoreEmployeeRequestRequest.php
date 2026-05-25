<?php

namespace App\Http\Requests\Employees;

use App\Models\Employees\RequestSubtype;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('attendance.requests.write')
            || $this->user()->hasPermission('attendance.self.request');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        // employee_id is NOT accepted from input — the controller always sets
        // it to the current user's own employee record. Any posted value is
        // ignored / dropped by validated().

        // Subtype must be global (null company) OR in the user's active companies.
        $subtypeRule = Rule::exists('hr_request_subtypes', 'id')->where(function ($q) use ($activeCompanyIds) {
            $q->where('active', true)->where(function ($qq) use ($activeCompanyIds) {
                $qq->whereNull('company_id');
                if (!empty($activeCompanyIds)) $qq->orWhereIn('company_id', $activeCompanyIds);
            });
        });

        return [
            'subtype_id'   => ['required', $subtypeRule],
            'type'         => ['required', Rule::in(array_keys(RequestSubtype::TYPE_LABELS))],
            'start_at'     => 'required|date',
            'end_at'       => 'required|date|after_or_equal:start_at',
            'title'        => 'nullable|string|max:255',
            'description'  => 'nullable|string|max:5000',
            // Rule 10: never bare `file`. Whitelist common attachment types
            // explicitly so request-layer rejects surface a clear error before
            // FileService's defense-in-depth boundary fires. SVG excluded on
            // purpose (stored XSS surface).
            'attachment'   => 'nullable|file|max:10240'
                . '|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,'
                . 'application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                . 'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                . 'application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,'
                . 'application/vnd.oasis.opendocument.text,application/vnd.oasis.opendocument.spreadsheet,'
                . 'text/plain,text/csv'
                . '|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,txt,csv',
        ];
    }
}
