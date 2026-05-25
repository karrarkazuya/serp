<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyPlannedPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('planned_schedules.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        $scheduleRule = Rule::exists('hr_resource_calendars', 'id')->where(function ($q) use ($activeCompanyIds) {
            $q->whereNull('company_id');
            if (!empty($activeCompanyIds)) {
                $q->orWhereIn('company_id', $activeCompanyIds);
            }
        });

        return [
            'pattern'   => ['required', 'array', 'min:1', 'max:30'],
            'pattern.*' => ['required', $scheduleRule],
        ];
    }
}
