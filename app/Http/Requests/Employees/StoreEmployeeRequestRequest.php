<?php

namespace App\Http\Requests\Employees;

use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeBalance;
use App\Models\Employees\EmployeeRequest;
use App\Models\Employees\RequestSubtype;
use App\Services\Company\CompanyContextService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
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
            // Bound start_at / end_at to ±1 year. Requests beyond that window
            // are almost always typos (year 2099, 1999) and break balance /
            // scheduling lookups. The duration cap below also bounds the gap.
            'start_at'     => 'required|date|after_or_equal:-1 year|before_or_equal:+1 year',
            'end_at'       => 'required|date|after_or_equal:start_at|before_or_equal:+1 year',
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

    /**
     * Cross-field checks that need parsed values + DB lookups:
     *   - Duration cap (mirrors EmployeeRequestService::create — leave ≤ 365d,
     *     time-off / overtime ≤ 48h). Catches the case before the service
     *     throws, so the user gets a labeled field error.
     *   - Balance pre-check for balance-cutting leave / time-off subtypes:
     *     refuse a request whose duration exceeds the employee's CURRENT
     *     balance minus the duration of their already-pending requests of
     *     the same type. The final source-of-truth check still runs at HR
     *     approval (assertSufficientBalance) — this is the friendly upfront
     *     gate so the employee doesn't submit something doomed to reject.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($v->errors()->any()) {
                return; // primary rules failed — don't run cross-field checks on garbage
            }

            $data    = $v->getData();
            $type    = $data['type']    ?? null;
            $startAt = $data['start_at'] ?? null;
            $endAt   = $data['end_at']   ?? null;

            if (!$type || !$startAt || !$endAt) return;

            try {
                $start = Carbon::parse($startAt);
                $end   = Carbon::parse($endAt);
            } catch (\Throwable) {
                return; // already caught by 'date' rule
            }

            // ── Duration cap (mirrors EmployeeRequestService::create) ──
            if ($type === RequestSubtype::TYPE_LEAVE) {
                $days = max(1, $start->diffInDays($end->copy()->addSecond()));
                if ($days > 365) {
                    $v->errors()->add('end_at', __('employees.request_leave_max_days'));
                    return;
                }
            } else { // time_off / overtime
                $hours = round($start->diffInMinutes($end) / 60, 2);
                if ($hours > 48) {
                    $v->errors()->add('end_at', __('employees.request_hours_max'));
                    return;
                }
            }

            // ── Balance pre-check ──
            // Overtime doesn't deduct balance; skip. Skip if subtype lookup
            // fails (already caught by primary rule).
            if ($type === RequestSubtype::TYPE_OVERTIME) return;

            $subtype = RequestSubtype::find($data['subtype_id'] ?? null);
            if (!$subtype || !$subtype->cuts_balance) return;

            $employee = Employee::where('user_id', $this->user()->id)->first();
            if (!$employee) return; // controller redirects with a friendly error

            $balance = EmployeeBalance::where('employee_id', $employee->id)->first();
            if (!$balance) {
                $v->errors()->add('subtype_id', __('employees.request_no_balance_configured'));
                return;
            }

            // Sum already-pending balance-cutting requests of the same type so
            // an employee can't submit five 1-day leaves with a 3-day balance.
            $pendingDurationField = $type === RequestSubtype::TYPE_LEAVE ? 'duration_days' : 'duration_hours';
            $pending = EmployeeRequest::where('employee_id', $employee->id)
                ->where('type', $type)
                ->where('state', EmployeeRequest::STATE_PENDING)
                ->whereHas('subtype', fn ($q) => $q->where('cuts_balance', true))
                ->sum($pendingDurationField);

            if ($type === RequestSubtype::TYPE_LEAVE) {
                $requested = max(1, $start->diffInDays($end->copy()->addSecond()));
                $available = max(0, (float) $balance->leave_days_balance - (float) $pending);
                if ($requested > $available + 0.0001) {
                    $v->errors()->add('end_at', __('employees.request_exceeds_leave_balance', [
                        'requested' => $requested,
                        'available' => rtrim(rtrim(number_format($available, 2), '0'), '.'),
                    ]));
                }
            } else { // TIME_OFF
                $requested = round($start->diffInMinutes($end) / 60, 2);
                $available = max(0, (float) $balance->time_off_hours_balance - (float) $pending);
                if ($requested > $available + 0.0001) {
                    $v->errors()->add('end_at', __('employees.request_exceeds_timeoff_balance', [
                        'requested' => $requested,
                        'available' => rtrim(rtrim(number_format($available, 2), '0'), '.'),
                    ]));
                }
            }
        });
    }
}
